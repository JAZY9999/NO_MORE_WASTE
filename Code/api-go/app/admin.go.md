# app/admin.go — exemple concret de route protégée par rôle

> ⏱️ **Lecture : ~5 min** · 453 mots, 16 lignes de code

## C'est quoi ce fichier ?

Une seule route, `GET /admin/ping`, qui ne sert à rien de "métier" — elle existe uniquement pour PROUVER que le système de vérification de rôle (`utils.RequireRole`, voir `utils/guard.go.md`) fonctionne vraiment. C'est le point d'appui technique de la Phase 1.2, avant de réutiliser exactement le même principe sur de vraies routes plus tard (par exemple : la liste des commerçants, réservée au staff).

## Le code, en détail

```go
func AdminPing(w http.ResponseWriter, r *http.Request) {
    email, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
    if !ok {
        return
    }

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(map[string]string{"message": "acces autorise", "email": email})
}
```

### La ligne clé
```go
email, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
```
Cette seule ligne fait tout le travail de sécurité : elle vérifie que la requête contient un token JWT valide, ET que le rôle contenu dans ce token est `"admin_back"` OU `"staff_back"`. C'est une fonction "variadique" (voir `utils/guard.go.md`) : on aurait pu lui passer un seul rôle, ou trois, la syntaxe reste la même.

### Le garde
```go
if !ok {
    return
}
```
`!ok` se lit "pas ok" — c'est-à-dire "si l'accès n'a PAS été autorisé". Dans ce cas, `RequireRole` a déjà écrit une réponse d'erreur (401 ou 403) dans `w`, donc on `return` immédiatement, SANS écrire quoi que ce soit d'autre. Si on oubliait ce `return`, le code continuerait et essaierait d'écrire une deuxième réponse HTTP, ce qui provoque un bug (voir le "piège à connaître" dans `utils/guard.go.md`).

### La réponse si tout va bien
Si `ok` est `true`, on continue : on précise que la réponse sera au format JSON (`w.Header().Set("Content-Type", "application/json")`), puis on encode et on écrit un petit message de confirmation avec l'email de la personne connectée.

## Comment tester ça soi-même (les 3 scénarios qu'on doit savoir refaire)

1. **Sans aucun token** : `curl http://localhost:8080/api/admin/ping/` → doit répondre `401 Unauthorized` avec le message "Jeton invalide".
2. **Avec un token d'un compte `adherent`** (se connecter d'abord via `POST /auth/login`, récupérer le token, puis l'envoyer dans le header `Authorization`) → doit répondre `403 Forbidden` avec le message "Acces interdit" (le rôle `adherent` n'est pas dans la liste autorisée).
3. **Avec un token d'un compte `staff_back`** → doit répondre `200 OK` avec `{"message":"acces autorise","email":"..."}`.

## Piège à connaître

Pour l'instant, il n'existe AUCUNE route publique permettant de créer un compte `staff_back` ou `admin_back` — la seule route d'inscription (`POST /auth/register`, voir `app/auth.go.md`) attribue toujours le rôle `"adherent"`. C'est volontaire : on ne veut surtout pas que n'importe qui puisse s'auto-attribuer un rôle privilégié en s'inscrivant tout seul ! Le compte de test `staff@nomorewaste.fr` utilisé pour valider ce fichier a été inséré directement en base par une commande SQL manuelle (via `docker exec ... psql ...`), pas via l'API. Une vraie façon de créer des comptes staff (par exemple un endpoint réservé aux admins existants, ou un script de "seed" au démarrage) reste à définir dans une phase ultérieure du projet.
