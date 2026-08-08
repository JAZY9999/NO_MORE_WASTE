# utils/guard.go — vérifier qu'on a le droit d'accéder à une route

> ⏱️ **Lecture : ~10 min** · 673 mots, 26 lignes de code

## C'est quoi ce fichier ?

Il contient UNE seule fonction, `RequireRole` ("exiger un rôle"), dont le travail est : "est-ce que la personne qui fait cette requête est bien connectée, ET a-t-elle un rôle autorisé pour cette action précise ?". C'est la réponse à la Phase 1.2 de la todo : "Middleware / guard qui restreint l'accès selon le rôle".

## Le mot "guard" (garde)

En programmation, un "guard" est un bout de code placé au tout début d'une fonction, dont le seul travail est de vérifier une condition et d'arrêter l'exécution immédiatement si elle n'est pas remplie — un peu comme un vigile à l'entrée d'un bâtiment qui vérifie le badge avant de laisser entrer.

## Pourquoi ce n'est pas un vrai "middleware" au sens classique (et pourquoi c'est un choix volontaire)

Dans beaucoup de frameworks web, un "middleware" est une fonction qui s'intercale AUTOMATIQUEMENT avant un handler, sans que le handler ait besoin de le savoir ni de rien écrire lui-même. Ici, on a fait un choix différent, plus simple, cohérent avec le support de cours de base : chaque handler qui doit être protégé appelle lui-même `RequireRole` en une ligne, tout au début de sa fonction. Ce n'est pas automatique, mais c'est très lisible : en ouvrant n'importe quel handler, on voit immédiatement s'il est protégé et par quels rôles précisément, sans devoir chercher ailleurs dans le projet.

## La fonction, en détail

```go
func RequireRole(w http.ResponseWriter, r *http.Request, rolesAutorises ...string) (string, bool) {
```

### `rolesAutorises ...string`
Les trois petits points (`...`) avant `string` indiquent un paramètre "variadique" : on peut appeler cette fonction avec 0, 1, ou plusieurs rôles à la fois : `RequireRole(w, r)`, `RequireRole(w, r, "admin_back")`, ou `RequireRole(w, r, "admin_back", "staff_back")` — Go rassemble automatiquement tout ce qu'on lui donne dans une liste (un "slice", l'équivalent Go d'un tableau redimensionnable) appelée `rolesAutorises` à l'intérieur de la fonction.

### Ce que retourne la fonction : `(string, bool)`
Deux valeurs : l'email de la personne connectée (utile si le handler appelant a besoin de savoir qui fait la requête), et un booléen `ok` qui dit si l'accès est autorisé (`true`) ou non (`false`).

## Ligne par ligne

```go
tokenString := r.Header.Get("Authorization")
email, role, err := VerifyJWT(tokenString)
if err != nil {
    http.Error(w, "Jeton invalide", http.StatusUnauthorized)
    return "", false
}
```

1. On récupère le token depuis le header `Authorization` de la requête (`r.Header.Get(...)`).
2. On le vérifie avec `VerifyJWT` (voir `jwt.go.md`), qui retourne l'email, le rôle, et une éventuelle erreur.
3. Si le token est absent, expiré, ou invalide (`err != nil`), on envoie directement une erreur HTTP `401 Unauthorized` au client (`http.Error` écrit à la fois le message et le code d'erreur dans la réponse) et on retourne `false` — le handler appelant doit alors s'arrêter immédiatement (voir plus bas, "piège à connaître").

```go
for _, roleAutorise := range rolesAutorises {
    if role == roleAutorise {
        return email, true
    }
}
```

Une boucle `for ... range` parcourt chaque élément de la liste `rolesAutorises`. Le `_` ignore l'index (la position dans la liste, qu'on ne s'en sert pas ici), et `roleAutorise` contient à chaque tour la valeur suivante. Si le rôle de l'utilisateur connecté correspond à l'UN des rôles autorisés, on retourne tout de suite `email, true` — l'accès est validé, pas besoin de continuer à vérifier le reste de la liste.

```go
http.Error(w, "Acces interdit", http.StatusForbidden)
return "", false
```

Si on arrive jusqu'ici, c'est qu'aucun rôle ne correspondait : on renvoie une erreur `403 Forbidden` ("interdit" — différent de 401 "non authentifié" : ici la personne EST bien connectée, mais elle n'a juste pas le bon rôle) et on retourne `false`.

## Comment un handler utilise cette fonction (voir `app/admin.go.md` pour un exemple réel)

```go
func MonHandler(w http.ResponseWriter, r *http.Request) {
    email, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
    if !ok {
        return
    }
    // ... la suite du code ne s'exécute que si l'accès est autorisé
}
```

## Piège à connaître (très important, ça peut casser le programme si on l'oublie)

Le `return` juste après `if !ok` est OBLIGATOIRE. Voici pourquoi : `RequireRole` a déjà écrit une réponse d'erreur complète dans `w` (le 401 ou le 403) avant de retourner `false`. Si le handler continuait quand même son exécution malgré `ok == false`, il essaierait d'écrire une DEUXIÈME réponse HTTP pour la même requête — ce qui provoque une erreur Go du genre "superfluous response.WriteHeader call" (en gros : "tu as déjà répondu, tu ne peux pas répondre une deuxième fois") et un comportement incohérent renvoyé au client.
