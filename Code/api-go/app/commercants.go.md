# app/commercants.go — gérer les commerçants (Phase 2 du sujet)

> ⏱️ **Lecture : ~24 min** · 1 100 mots

## C'est quoi ce fichier ?

Les handlers de la Phase 2 : créer un commerçant, lister tous les commerçants, récupérer un commerçant précis par son id, **le modifier**, et créer une adhésion. Ce sont des routes réservées au staff/back-office — le sujet dit "gérer les adhésions des commerçants" du point de vue de l'association, pas du commerçant lui-même.

> 🔄 **`ModifierCommercant` a été ajouté pendant le portage du front (phase 11).** Son absence avait une conséquence concrète, détaillée plus bas : une boutique enregistrée sans compte ne pouvait plus **jamais** être rattachée à son propriétaire.

## Fonction 1 : CreerCommercant

```go
func CreerCommercant(w http.ResponseWriter, r *http.Request) {
    _, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
    if !ok {
        return
    }

    var c models.Commercant
    err := json.NewDecoder(r.Body).Decode(&c)
    if err != nil {
        http.Error(w, "JSON invalide", http.StatusBadRequest)
        return
    }

    if c.RaisonSociale == "" {
        http.Error(w, "La raison sociale est obligatoire", http.StatusBadRequest)
        return
    }

    id, err := db.CreateCommercant(c)
    if err != nil {
        http.Error(w, "Erreur de creation du commercant", http.StatusInternalServerError)
        return
    }

    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(http.StatusCreated)
    json.NewEncoder(w).Encode(map[string]int{"id": id})
}
```

### La protection par rôle
```go
_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
if !ok {
    return
}
```
Exactement le même principe que dans `app/admin.go.md` : seuls les comptes `admin_back` ou `staff_back` peuvent créer un commerçant. Ici on ignore l'email retourné (`_`) car on n'en a pas besoin dans cette fonction.

### Lire le JSON envoyé
```go
var c models.Commercant
err := json.NewDecoder(r.Body).Decode(&c)
```
Même principe que dans `app/auth.go.md` : on lit le corps JSON de la requête et on le transforme directement en struct Go `Commercant`.

### Une vérification simple avant d'enregistrer
```go
if c.RaisonSociale == "" {
    http.Error(w, "La raison sociale est obligatoire", http.StatusBadRequest)
    return
}
```
`RaisonSociale` est un `string` normal (pas un pointeur, voir `models/commercant.go.md`), donc si le client ne l'a pas envoyée, sa valeur par défaut est simplement une chaîne vide `""`. On vérifie ça avant d'aller plus loin, pour éviter d'enregistrer un commerçant sans nom en base (la colonne SQL est `NOT NULL` de toute façon, donc sans cette vérification, l'erreur remonterait quand même, mais de façon moins claire, directement depuis Postgres).

### Répondre avec l'id créé
```go
w.Header().Set("Content-Type", "application/json")
w.WriteHeader(http.StatusCreated)
json.NewEncoder(w).Encode(map[string]int{"id": id})
```
On répond `201 Created` (comme pour `Register` dans `app/auth.go.md`), avec un petit JSON `{"id": 5}` par exemple, pour que le client sache quel identifiant a été attribué au nouveau commerçant.

## Fonction 2 : ListerCommercants

```go
func ListerCommercants(w http.ResponseWriter, r *http.Request) {
    _, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
    if !ok {
        return
    }

    commercants, err := db.ListCommercants()
    ...
    json.NewEncoder(w).Encode(commercants)
}
```
Rien de nouveau ici par rapport aux autres handlers : on protège la route, on appelle la fonction du repository qui retourne une LISTE de commerçants (voir `db/commercantsRepository.go.md`), et on l'encode directement en JSON — `encoding/json` sait très bien transformer un slice (`[]models.Commercant`) en un tableau JSON `[...]` automatiquement.

## Fonction 3 : ObtenirCommercant

```go
func ObtenirCommercant(w http.ResponseWriter, r *http.Request) {
    _, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
    if !ok {
        return
    }

    id, err := strconv.Atoi(r.PathValue("id"))
    if err != nil {
        http.Error(w, "Id invalide", http.StatusBadRequest)
        return
    }
    ...
}
```

### Récupérer un morceau variable de l'URL
La route est déclarée dans `app.go` ainsi : `http.HandleFunc("GET /commercants/{id}", app.ObtenirCommercant)`. Le `{id}` dans le motif de route veut dire "accepte n'importe quelle valeur ici, et donne-moi un nom pour la récupérer plus tard" (voir `app.go.md` pour le rappel sur le routage).

`r.PathValue("id")` récupère cette valeur — mais ATTENTION, elle arrive toujours sous forme de texte (`string`), même si l'utilisateur a tapé un nombre dans l'URL (par exemple `/commercants/5`). `strconv.Atoi(...)` ("ASCII to integer") convertit ce texte en vrai nombre entier (`int`). Si quelqu'un tape une URL bizarre comme `/commercants/abc`, la conversion échoue (`err != nil`), et on répond `400 Bad Request`.

### La suite
Une fois l'id bien récupéré et converti, on appelle `db.GetCommercantById(id)`, on vérifie si le résultat est `nil` (commerçant introuvable → `404 Not Found`), sinon on le renvoie en JSON — exactement le même schéma que dans `Me` (voir `app/auth.go.md`).

## Piège à connaître : l'ordre des routes dans app.go

```go
http.HandleFunc("POST /commercants/{$}", app.CreerCommercant)
http.HandleFunc("GET /commercants/{$}", app.ListerCommercants)
http.HandleFunc("GET /commercants/{id}", app.ObtenirCommercant)
```

`GET /commercants/{$}` (avec `{$}`, "l'URL s'arrête pile là") et `GET /commercants/{id}` (avec juste un nom de variable) ne se marchent pas dessus : `/commercants/` (rien après) matche la première, `/commercants/5` matche la deuxième. Si on avait écrit `GET /commercants` (sans le `{$}` final) pour la liste, ça aurait accepté N'IMPORTE QUELLE URL commençant par `/commercants`, y compris `/commercants/5`, ce qui aurait créé un conflit avec la route de détail — voir le support de cours de base pour ce piège classique des routes Go 1.22+.

## Fonction 4 : ModifierCommercant — la route qui manquait

`PUT /commercants/{id}`, ajoutée en portant la fiche commerçant du back-office.

### Le trou qu'elle comble

Une boutique créée **sans** compte de connexion ne pouvait jamais être rattachée à son propriétaire après coup. Celui-ci se connectait, et son espace client répondait "aucune boutique rattachée à votre compte" — sans le moindre recours depuis l'application. Il fallait une requête SQL à la main.

### Mise à jour PARTIELLE, pas un remplacement

```go
var dto struct {
    RaisonSociale *string `json:"raison_sociale"`
    Siret         *string `json:"siret"`
    ...
}
```

Des **pointeurs**, pas des `string`. La distinction est le cœur de la fonction :

| Ce que le client envoie | Ce que ça veut dire |
|---|---|
| champ absent du JSON | *ne touche pas à cette valeur* |
| `"siret": ""` | *vide ce champ* |

Sans cette distinction, un `PUT` classique remplacerait l'objet entier. Le formulaire de rattachement de compte, qui n'envoie que `utilisateur_id`, effacerait alors silencieusement le SIRET, l'adresse, tout le reste. C'est le piège classique des routes `PUT`, et il ne se voit qu'après coup — quand une donnée a disparu sans que personne sache quand.

La fonction relit donc la fiche existante, puis n'écrase que ce qui est réellement fourni :

```go
modifie := *existant
if dto.Siret != nil {
    modifie.Siret = dto.Siret
}
// ... un `if` par champ
db.UpdateCommercant(id, modifie)
```

### Le `0` qui détache un compte

```go
if *dto.UtilisateurId == 0 {
    modifie.UtilisateurId = nil
}
```

Un menu déroulant HTML ne peut pas envoyer `null`. `0` est la convention pour "aucun compte" — aucun identifiant ne vaut zéro, la valeur est sans ambiguïté.

### Ce qui protège des doublons

`utilisateur_id` est `UNIQUE` en base. Rattacher un compte déjà pris à une autre boutique remonte en **409**, grâce au code `23505` traité dans `utils.ErreurServeur` — aucune vérification manuelle à écrire ici.

## Fonction 5 : CreerAdhesion (créer une adhésion pour un commerçant précis)

```go
func CreerAdhesion(w http.ResponseWriter, r *http.Request) {
    _, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
    if !ok {
        return
    }

    commercantId, err := strconv.Atoi(r.PathValue("id"))
    ...
    commercant, err := db.GetCommercantById(commercantId)
    ...
    if commercant == nil {
        http.Error(w, "Commercant introuvable", http.StatusNotFound)
        return
    }

    var a models.Adhesion
    err = json.NewDecoder(r.Body).Decode(&a)
    ...
    if a.DateDebut == "" || a.DateFin == "" || a.Statut == "" {
        http.Error(w, "date_debut, date_fin et statut sont obligatoires", http.StatusBadRequest)
        return
    }

    a.CommercantId = commercantId

    id, err := db.CreateAdhesion(a)
    ...
}
```

### La route dans app.go
```go
http.HandleFunc("POST /commercants/{id}/adhesions", app.CreerAdhesion)
```
C'est une route "imbriquée" : `{id}` capture l'identifiant du commerçant, et on crée une adhésion RATTACHÉE à ce commerçant précis — convention REST vue en cours ("contrat d'api en convention REST.pdf") : `/ressource/{id}/sous-ressource` désigne l'ensemble des sous-ressources liées à une ressource précise.

### Pourquoi on vérifie D'ABORD que le commerçant existe
```go
commercant, err := db.GetCommercantById(commercantId)
...
if commercant == nil {
    http.Error(w, "Commercant introuvable", http.StatusNotFound)
    return
}
```
Avant même de lire le corps de la requête, on vérifie que le commerçant `{id}` existe vraiment. Sans cette vérification, on pourrait créer une adhésion "orpheline" rattachée à un commerçant inexistant — la contrainte `REFERENCES commercants(id)` dans `schema.sql` empêcherait techniquement Postgres de l'accepter (elle renverrait une erreur SQL), mais autant le détecter proprement AVANT et répondre `404 Not Found` avec un message clair, plutôt que de laisser Postgres renvoyer une erreur SQL brute qu'on transformerait en `500 Internal Server Error` peu informatif.

### `a.CommercantId = commercantId`
Après avoir lu le JSON envoyé par le client (qui contient `date_debut`, `date_fin`, `statut`, `montant_cotisation`), on écrase volontairement le champ `CommercantId` avec l'id venant de l'URL. Pourquoi ? Pour empêcher un client malveillant (ou juste maladroit) d'envoyer un `commercant_id` différent dans le JSON du body — on fait confiance UNIQUEMENT à ce qui est dans l'URL pour déterminer le commerçant concerné, jamais à ce que le client prétend dans le body.
