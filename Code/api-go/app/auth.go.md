# app/auth.go — inscription, connexion, profil

> ⏱️ **Lecture : ~20 min** · 920 mots, 84 lignes de code

## C'est quoi ce fichier ?

Il contient 3 fonctions "handler" (des fonctions appelées directement quand une route web est visitée) : `Register` (s'inscrire), `Login` (se connecter), `Me` (voir mon propre profil). C'est le cœur de la Phase 1 de la todo.

## Le flux complet d'une requête (à savoir réexpliquer)

Quelqu'un tape une adresse dans Postman ou un navigateur → nginx reçoit la requête → nginx la transmet à l'API Go → `app.go` regarde quelle route correspond → la fonction handler ici s'exécute → elle appelle des fonctions du dossier `db/` pour lire/écrire en base → elle construit une réponse → cette réponse repart jusqu'au client, en passant à nouveau par nginx.

## Fonction utilitaire : verifierIdentifiants

```go
func verifierIdentifiants(dto models.Identifiants) []string {
    var messagesErreur []string
    if !strings.Contains(dto.Email, "@") {
        messagesErreur = append(messagesErreur, "Email invalide")
    }
    if len(dto.Password) < 5 || len(dto.Password) > 50 {
        messagesErreur = append(messagesErreur, "Le mot de passe doit contenir entre 5 et 50 caracteres")
    }
    return messagesErreur
}
```

Cette fonction commence par une minuscule (`verifierIdentifiants`, pas `VerifierIdentifiants`) — en Go, c'est une convention importante : un nom qui commence par une minuscule n'est utilisable QUE dans ce même fichier/package (`app`), pas depuis l'extérieur. C'est une fonction "privée", un détail interne d'implémentation.

- `[]string` : le type "liste de textes" (slice de string).
- `strings.Contains(dto.Email, "@")` : vérifie si le texte contient le caractère `@` — une vérification très basique (pas une vraie validation d'email complète, volontairement simple ici).
- `append(messagesErreur, "...")` : ajoute un élément à la fin d'une liste. En Go, les listes (`slice`) ne changent pas de taille "sur place" comme dans d'autres langages — `append` retourne une NOUVELLE liste (éventuellement agrandie), qu'on doit réaffecter à la variable, d'où `messagesErreur = append(messagesErreur, ...)`.
- La fonction retourne la liste des messages d'erreur trouvés — vide (`[]string{}` ou `nil`) si tout est correct.

## Fonction 1 : Register (s'inscrire)

```go
func Register(w http.ResponseWriter, r *http.Request) {
    var identifiants models.Identifiants
    err := json.NewDecoder(r.Body).Decode(&identifiants)
    if err != nil {
        http.Error(w, "JSON invalide", http.StatusBadRequest)
        return
    }
    ...
```

### Lire le corps de la requête (le "body")
`r.Body` contient les données envoyées par le client (par exemple `{"email": "...", "mot_de_passe": "..."}`). `json.NewDecoder(r.Body).Decode(&identifiants)` lit ce texte JSON et le transforme automatiquement en une vraie struct Go (`models.Identifiants`, voir `models/utilisateur.go.md`). Le `&identifiants` (encore un pointeur) permet à `Decode` d'écrire directement dans notre variable, plutôt que de nous retourner une copie.

Si le JSON envoyé est mal formé (par exemple si le client a oublié une accolade), `err` ne sera pas `nil`, et on répond `400 Bad Request` avec le message "JSON invalide".

```go
    messagesErreur := verifierIdentifiants(identifiants)
    if len(messagesErreur) > 0 {
        errFormated, _ := json.Marshal(messagesErreur)
        w.Header().Set("Content-Type", "application/json")
        http.Error(w, string(errFormated), http.StatusBadRequest)
        return
    }
```
On vérifie les identifiants avec la fonction du dessus. `len(messagesErreur)` donne la longueur de la liste (`len` = "length"). S'il y a au moins une erreur, on la transforme en JSON (`json.Marshal`, l'inverse de `Decode` — ça transforme une valeur Go EN texte JSON) et on répond `400 Bad Request` avec ce message.

```go
    utilisateurExistant, err := db.GetUtilisateurByEmail(identifiants.Email)
    if err != nil {
        http.Error(w, "Erreur de recuperation de l'utilisateur", http.StatusInternalServerError)
        return
    }
    if utilisateurExistant != nil {
        http.Error(w, "Email deja utilise", http.StatusConflict)
        return
    }
```
On vérifie qu'aucun utilisateur n'existe déjà avec cet email (voir `db/utilisateursRepository.go.md`). Rappel : `GetUtilisateurByEmail` retourne `nil, nil` si personne n'est trouvé — donc `if utilisateurExistant != nil` veut dire "si on A TROUVÉ quelqu'un" (donc erreur, email déjà pris → `409 Conflict`).

```go
    hashed, err := bcrypt.GenerateFromPassword([]byte(identifiants.MotDePasse), bcrypt.DefaultCost)
```
C'est l'étape la plus importante niveau sécurité : on transforme le mot de passe en clair en une chaîne illisible (un "hash") grâce à `bcrypt`. Cette transformation est à SENS UNIQUE : impossible de retrouver le mot de passe d'origine à partir du hash, même en connaissant l'algorithme utilisé. C'est pour ça qu'on peut stocker ce hash en base sans risque (si la base fuite un jour, les mots de passe restent protégés).

```go
    err = db.CreateUtilisateur(identifiants.Email, string(hashed), "adherent")
```
On enregistre le nouvel utilisateur en base, avec le rôle `"adherent"` codé en dur — c'est le rôle par défaut pour toute personne qui s'inscrit elle-même via cette route publique. `string(hashed)` reconvertit le hash (qui est un `[]byte`) en texte, pour le stocker comme les autres champs texte.

```go
    w.WriteHeader(http.StatusCreated)
```
Si tout s'est bien passé, on renvoie le code `201 Created` (convention HTTP standard : "la ressource demandée a bien été créée").

## Fonction 2 : Login (se connecter)

```go
func Login(w http.ResponseWriter, r *http.Request) {
    var identifiants models.Identifiants
    err := json.NewDecoder(r.Body).Decode(&identifiants)
    ...
    utilisateurExistant, err := db.GetUtilisateurByEmail(identifiants.Email)
    ...
    if utilisateurExistant == nil {
        http.Error(w, "Non autorise", http.StatusUnauthorized)
        return
    }
    if bcrypt.CompareHashAndPassword([]byte(utilisateurExistant.MotDePasseHash), []byte(identifiants.MotDePasse)) != nil {
        http.Error(w, "Non autorise", http.StatusUnauthorized)
        return
    }
```

Même début que `Register` (lire le JSON, chercher l'utilisateur). Puis deux vérifications, avec VOLONTAIREMENT le même message d'erreur ("Non autorise") dans les deux cas :
- Si l'email n'existe pas (`utilisateurExistant == nil`)
- Si le mot de passe ne correspond pas (`bcrypt.CompareHashAndPassword` re-hache le mot de passe reçu et compare avec le hash stocké — cette fonction retourne `nil` si ça correspond, une erreur sinon)

**Pourquoi le même message dans les deux cas ?** C'est un choix de sécurité volontaire : si on répondait "email inconnu" d'un côté et "mot de passe incorrect" de l'autre, un attaquant pourrait deviner quels emails existent réellement dans la base, juste en testant plein d'adresses. En donnant toujours la même réponse vague, on ne donne aucun indice supplémentaire.

```go
    token, err := utils.GenerateJWT(utilisateurExistant.Email, utilisateurExistant.Role)
    ...
    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(map[string]string{"token": token})
}
```

Si tout est bon, on génère un token JWT (voir `utils/jwt.go.md`) et on le renvoie au format JSON. `map[string]string{"token": token}` crée un petit dictionnaire à la volée avec une seule clé `"token"`. `json.NewEncoder(w).Encode(...)` transforme ça en JSON et l'écrit directement dans la réponse HTTP (`w`).

## Fonction 3 : Me (voir mon profil)

```go
func Me(w http.ResponseWriter, r *http.Request) {
    tokenString := r.Header.Get("Authorization")
    email, _, err := utils.VerifyJWT(tokenString)
    if err != nil {
        http.Error(w, "Jeton invalide", http.StatusUnauthorized)
        return
    }

    utilisateurExistant, err := db.GetUtilisateurByEmail(email)
    ...
    if utilisateurExistant == nil {
        http.Error(w, "Utilisateur introuvable", http.StatusNotFound)
        return
    }

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(utilisateurExistant)
}
```

1. On récupère le token depuis le header `Authorization` de la requête.
2. On le vérifie avec `VerifyJWT`, qui retourne l'email, le rôle (ignoré ici avec `_`, on n'en a pas besoin dans cette fonction), et une éventuelle erreur.
3. On cherche l'utilisateur correspondant en base et on le renvoie tel quel en JSON — le mot de passe haché n'apparaîtra JAMAIS dans cette réponse, grâce à l'annotation `json:"-"` vue dans `models/utilisateur.go.md`.

## Piège à connaître

Aucun de ces trois handlers ne vérifie de rôle particulier — c'est normal, ce sont des routes VOLONTAIREMENT publiques (n'importe qui peut s'inscrire, se connecter, consulter son propre profil). Une route qui doit être réservée à certains rôles (comme le staff) doit en plus appeler `utils.RequireRole` (voir `utils/guard.go.md` et l'exemple concret dans `app/admin.go.md`).
