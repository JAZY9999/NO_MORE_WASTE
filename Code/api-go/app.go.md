# app.go — le point de départ du programme

> ⏱️ **Lecture : ~5 min** · 651 mots, 14 lignes de code

## C'est quoi ce fichier ?

Quand on démarre un programme en Go, il faut toujours une fonction qui s'appelle `main`. C'est comme un bouton "Play" : Go cherche cette fonction et l'exécute en premier, dans le fichier qui a `package main` tout en haut. C'est ce fichier.

Ce fichier fait deux choses, dans l'ordre :
1. Il se connecte à la base de données.
2. Il dit à Go "quand quelqu'un visite telle adresse web, avec telle méthode, appelle telle fonction pour lui répondre" — c'est ce qu'on appelle le **routage** (routing).

## Notions Go utilisées ici (explications de base)

### `package main`
En Go, chaque fichier appartient à un "package" (un groupe de fichiers liés). Le package qui s'appelle `main` est spécial : c'est le seul qui peut contenir la fonction `main()` et donc démarrer un programme exécutable.

### `import (...)`
Ça sert à dire "je vais utiliser du code qui vient d'ailleurs". Ici on importe :
- `"fmt"` : outils pour afficher du texte dans le terminal ou formater des messages.
- `"net/http"` : le package fourni par Go pour tout ce qui touche au web (serveur HTTP).
- `"nomorewaste/app"`, `"nomorewaste/config"`, `"nomorewaste/db"` : ce sont NOS propres dossiers de code (voir plus bas), pas des librairies téléchargées.

### `func main() { ... }`
La fonction qui démarre tout. Le mot `func` veut juste dire "voici une fonction".

## Ligne par ligne, ce qui se passe au démarrage

```go
db.Conn = db.NewDB()
```
Ça appelle la fonction `NewDB()` qui se trouve dans le dossier `db/` (voir `db/db.go.md`), qui ouvre la connexion vers Postgres. Le résultat est stocké dans une variable globale `Conn`, un peu comme une prise électrique qu'on branche une fois et qu'on utilise partout ensuite dans le programme.

```go
http.HandleFunc("GET /{$}", healthCheck)
```
Cette ligne dit : "si quelqu'un fait une requête web de type `GET` (= 'donne-moi une page') sur l'adresse `/` exactement, appelle la fonction `healthCheck`". Le `{$}` est une astuce Go qui veut dire "l'adresse doit s'arrêter pile là" — sans ça, `/n-importe-quoi` matcherait aussi.

Les lignes suivantes font pareil pour les autres adresses :
- `POST /auth/register/{$}` → appelle `app.Register` (inscription)
- `POST /auth/login/{$}` → appelle `app.Login` (connexion)
- `GET /auth/me/{$}` → appelle `app.Me` (voir mon profil)
- `GET /admin/ping/{$}` → appelle `app.AdminPing` (route de test réservée au staff)

`app.Register` veut dire : "la fonction `Register` qui se trouve dans le package `app`" (dossier `app/`, fichier `auth.go`).

```go
http.ListenAndServe(":"+config.ApiPort(), nil)
```
Cette ligne démarre vraiment le serveur : à partir de maintenant, le programme reste allumé et écoute les requêtes qui arrivent sur le port choisi (8080 par défaut). Tant que cette ligne tourne, le programme ne s'arrête pas tout seul.

## La fonction healthCheck

```go
func healthCheck(w http.ResponseWriter, r *http.Request) {
    err := db.Conn.Ping()
    if err != nil {
        panic(err)
    }
    fmt.Fprintf(w, "NO MORE WASTE api - ok")
}
```

Toute fonction appelée par une route web en Go doit avoir exactement cette forme : deux paramètres, `w` (pour écrire la réponse) et `r` (qui contient les infos de la requête reçue).

- `db.Conn.Ping()` : vérifie que la base de données répond toujours (comme un "es-tu là ?").
- `if err != nil { panic(err) }` : en Go, presque toutes les fonctions qui peuvent échouer renvoient une valeur `err` (erreur). Si `err` n'est pas `nil` (c'est-à-dire s'il y a eu une vraie erreur), on utilise `panic` pour arrêter le programme brutalement avec un message d'erreur — ici c'est volontairement extrême, car si la base ne répond plus, il n'y a plus grand-chose à faire.
- `fmt.Fprintf(w, "...")` : écrit du texte dans la réponse envoyée au client (celui qui a fait la requête).

## Piège à connaître

Le fichier s'appelle `app.go` mais son package est `main`, pas `app` — ce n'est pas une erreur ! En Go, le NOM DU FICHIER n'a aucun rapport obligatoire avec le nom du package qu'il déclare. Seul le mot après `package` compte.

## Pour ajouter une nouvelle route plus tard

Ajouter une ligne `http.HandleFunc("METHODE /chemin/{$}", app.MaFonction)` ici, et écrire `MaFonction` dans un fichier du dossier `app/`.
