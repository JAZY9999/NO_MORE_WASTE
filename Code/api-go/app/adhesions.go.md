# app/adhesions.go — modifier/renouveler une adhésion

> ⏱️ **Lecture : ~8 min** · 550 mots

## C'est quoi ce fichier ?

Deux handlers : `ListerAdhesions` (`GET /adhesions/`) et `ModifierAdhesion` (`PUT /adhesions/{id}`), qui répond à `PUT /adhesions/{id}` — cette dernière sert à renouveler une adhésion (changer sa `date_fin`, son `statut`, son `montant_cotisation`).

> ⚠️ **Le rappel automatique de renouvellement est codé, testé, et tourne déjà** — dans `utils/scheduler.go` et `app/rappels.go`, pas ici. Ce fichier-ci ne fait que créer/lister/modifier les adhésions elles-mêmes. Une version antérieure de ce document disait le contraire ; voir `app/rappels.go.md` pour le mécanisme réel.

## ListerAdhesions — la route qui manquait

> 🔄 Ajoutée en portant l'écran des adhésions du back-office (phase 11).

```go
var statutsAdhesion = []string{"active", "expiree", "resiliee", "en_attente"}

func ListerAdhesions(w http.ResponseWriter, r *http.Request) {
    _, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
    ...
    var statut *string
    if valeur := r.URL.Query().Get("statut"); valeur != "" {
        // liste blanche avant d'atteindre la base
        ...
    }
    adhesions, err := db.ListAdhesions(statut)
    ...
}
```

### Le trou qu'elle comble

Avant cette route, **aucun endpoint ne listait toutes les adhésions**. Seule `GET /adhesions/a-renouveler/` existait (voir `app/rappels.go.md`), et elle ne renvoie que celles qui tombent à J-30 ou J-7 exactement. Le back-office ne pouvait donc pas répondre à des questions simples : combien d'adhésions sont actives ? Lesquelles ont expiré ?

### La liste blanche du statut

```go
for _, s := range statutsAdhesion {
    if valeur == s { valide = true; break }
}
if !valide {
    http.Error(w, "Statut invalide", http.StatusBadRequest)
    return
}
```

Un `?statut=pirate` répond **400**, pas une liste vide. Une liste vide silencieuse ferait croire qu'aucune adhésion ne correspond, alors que le vrai problème est que le paramètre est mal orthographié — la même distinction qu'ailleurs dans le projet entre "aucun résultat" et "requête invalide".

## ModifierAdhesion

## Pourquoi PUT et pas PATCH

D'après le support de cours HTTP ("http.pdf") : `PUT` sert à "envoyer une entité pour remplacement" — on renvoie TOUTES les informations de l'adhésion (date_debut, date_fin, statut, montant_cotisation), qui remplacent entièrement les anciennes valeurs. `PATCH` serait plus adapté si on voulait modifier un seul champ à la fois (par exemple juste le statut) sans avoir à renvoyer tout le reste — mais ce n'est pas ce qu'on fait ici.

## Le code, en détail

```go
func ModifierAdhesion(w http.ResponseWriter, r *http.Request) {
    _, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
    if !ok {
        return
    }

    id, err := strconv.Atoi(r.PathValue("id"))
    ...

    existing, err := db.GetAdhesionById(id)
    ...
    if existing == nil {
        http.Error(w, "Adhesion introuvable", http.StatusNotFound)
        return
    }

    var a models.Adhesion
    err = json.NewDecoder(r.Body).Decode(&a)
    ...
    if a.DateDebut == "" || a.DateFin == "" || a.Statut == "" {
        http.Error(w, "date_debut, date_fin et statut sont obligatoires", http.StatusBadRequest)
        return
    }

    err = db.UpdateAdhesion(id, a)
    ...
    w.WriteHeader(http.StatusNoContent)
}
```

### Pourquoi on vérifie D'ABORD que l'adhésion existe (`GetAdhesionById`)
Exactement le même raisonnement que pour `CreerAdhesion` dans `app/commercants.go.md` : `db.UpdateAdhesion` (voir `db/adhesionsRepository.go.md`) ne renvoie pas d'erreur si l'id n'existe pas — elle modifierait simplement 0 ligne, silencieusement. En vérifiant d'abord avec `GetAdhesionById`, on peut répondre clairement `404 Not Found` si l'adhésion n'existe pas, plutôt que de laisser passer une modification qui n'aurait en fait rien modifié du tout.

### Le code de retour : `204 No Content`
```go
w.WriteHeader(http.StatusNoContent)
```
D'après le cours HTTP : "204 : succès, mais aucune information retournée au client — par convention, c'est ce qu'on veut répondre à DELETE/PUT/PATCH". C'est pour ça qu'on n'appelle pas `json.NewEncoder(w).Encode(...)` après — il n'y a volontairement rien à renvoyer dans le corps de la réponse, juste le code de statut qui confirme que la modification a réussi.

## Comment tester le vrai "renouvellement" métier

1. Créer une adhésion avec `date_fin` dans 5 jours (`POST /commercants/{id}/adhesions`).
2. Appeler `PUT /adhesions/{id}` en renvoyant les mêmes infos mais avec une nouvelle `date_fin` plus loin dans le futur (par exemple +1 an) — c'est ça, le "renouvellement" manuel du staff mentionné dans le sujet.

## Le rappel automatique, pour de vrai

Il est ailleurs, et il tourne :

- `utils/scheduler.go` : la goroutine quotidienne, avec ses trois délais (J-30, J-7, 180 jours pour les anciens adhérents).
- `app/rappels.go` : `GET /adhesions/a-renouveler/`, `POST /adhesions/{id}/relancer` (manuel), `GET /adhesions/{id}/historique-rappels`, `POST /admin/jobs/rappels-adhesions/` (déclenchement manuel pour la démonstration).

C'est là qu'il faut chercher pour expliquer le mécanisme, pas dans ce fichier.

## Fichiers liés

- [../db/adhesionsRepository.go.md](../db/adhesionsRepository.go.md) — `ListAdhesions`, avec le filtre facultatif
- [rappels.go.md](rappels.go.md) — le rappel automatique, ses délais, son historique
- [../../front-php/app/controllers/back/AdhesionsController.php.md](../../front-php/app/controllers/back/AdhesionsController.php.md) — l'écran qui a fait apparaître le trou
