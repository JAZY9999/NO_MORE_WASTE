# db/db.go — la connexion à la base de données

> ⏱️ **Lecture : ~10 min** · 657 mots, 26 lignes de code

## C'est quoi ce fichier ?

Ce fichier ouvre le "tuyau" de communication entre le programme Go et la base de données Postgres. C'est l'équivalent Go de ce que fait `PDO` en PHP, ou une connexion `mysqli` : sans ça, aucune autre partie du code ne peut lire ou écrire dans la base.

## Notions Go utilisées ici

### `var Conn *sql.DB`
`var` déclare une variable. `*sql.DB` veut dire "un pointeur vers une valeur de type `sql.DB`". Un **pointeur** en Go, c'est une variable qui ne contient pas directement la donnée, mais l'ADRESSE en mémoire où se trouve cette donnée — un peu comme une carte routière qui indique où trouver la maison, plutôt que de transporter la maison elle-même. Ça permet à plein d'endroits différents du code de partager LA MÊME connexion, sans en recréer une copie à chaque fois.

`Conn` est déclarée en dehors de toute fonction, donc elle est "globale" : accessible depuis n'importe quel fichier du package `db`, et même depuis `app.go` (via `db.Conn`).

### `func NewDB() *sql.DB { ... }`
Une fonction qui retourne un pointeur vers une connexion à la base. C'est cette fonction qui fait tout le travail de connexion.

### Le symbole `_` devant un import
```go
import (
    _ "github.com/lib/pq"
)
```
Le `_` (underscore) veut dire "j'importe ce package uniquement pour son EFFET DE BORD, je ne vais jamais écrire `pq.QuelqueChose()` dans mon code". `lib/pq` est le "driver" Postgres : au moment où ce package est chargé, il s'enregistre tout seul auprès du package `database/sql` pour lui dire "je sais parler à Postgres". Ensuite, tout le reste du code utilise uniquement les fonctions génériques de `database/sql`, jamais directement `pq`.

## Ligne par ligne, ce que fait NewDB()

```go
sqlInfo := fmt.Sprintf("host=%s port=%s user=%s password=%s dbname=%s sslmode=disable",
    config.DbHost(), config.DbPort(), config.DbUser(), config.DbPassword(), config.DbName())
```
`fmt.Sprintf` construit une chaîne de texte en remplaçant chaque `%s` par la valeur suivante dans la liste. Ça donne par exemple : `"host=postgres port=5432 user=nmw_user password=nmw_password dbname=nmw sslmode=disable"`. C'est la "carte d'identité" qu'on va donner à Postgres pour se connecter.

```go
conn, err := sql.Open(config.DbDriver, sqlInfo)
```
`sql.Open` PRÉPARE la connexion, mais ne vérifie pas encore vraiment qu'elle fonctionne — c'est un piège classique en Go : cette ligne réussit presque toujours, même si les informations de connexion sont fausses.

Notez `conn, err := ...` : en Go, une fonction peut retourner PLUSIEURS valeurs à la fois. Ici on récupère la connexion (`conn`) ET une possible erreur (`err`) en une seule ligne. Le `:=` veut dire "je déclare ces deux variables et je leur donne une valeur en même temps" (raccourci de `var conn ... ; conn = ...`).

```go
if err != nil {
    panic(err.Error())
}
```
Vérification classique en Go : si une erreur s'est produite pendant `sql.Open`, on arrête tout brutalement avec `panic` (un `panic` en Go, c'est comme une exception fatale qui plante le programme si rien ne l'attrape).

```go
var pingErr error
for i := 0; i < 10; i++ {
    pingErr = conn.Ping()
    if pingErr == nil {
        break
    }
    time.Sleep(2 * time.Second)
}
```
Ici on fait une VRAIE vérification, avec une boucle `for` (répéter un bloc de code plusieurs fois). `conn.Ping()` envoie un petit signal à la base pour vérifier qu'elle répond réellement. Si ça échoue, on attend 2 secondes (`time.Sleep`) et on réessaie, jusqu'à 10 fois. Le mot `break` arrête la boucle dès que ça fonctionne (`pingErr == nil` veut dire "pas d'erreur").

**Pourquoi cette boucle est indispensable dans Docker** : quand `docker compose up` démarre tous les conteneurs, ils ne démarrent pas forcément dans l'ordre parfait — le conteneur `api-go` peut très bien être lancé une fraction de seconde avant que Postgres soit vraiment prêt à accepter des connexions. Sans cette boucle de réessai, l'API planterait systématiquement au tout premier démarrage.

```go
if pingErr != nil {
    panic(pingErr.Error())
}
```
Si même après 10 tentatives (20 secondes) la base ne répond toujours pas, là on abandonne pour de vrai.

## Piège à connaître

`Conn` (utilisée partout ailleurs comme `db.Conn`) n'est pas UNE SEULE connexion technique — c'est en réalité un "pool" (une réserve) de plusieurs connexions gérées automatiquement par Go en arrière-plan. On n'a jamais besoin d'ouvrir/fermer une connexion à la main pour chaque requête SQL : on utilise juste `db.Conn.Query(...)` ou `db.Conn.Exec(...)` et Go s'occupe du reste tout seul.
