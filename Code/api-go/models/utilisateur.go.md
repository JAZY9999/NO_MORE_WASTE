# models/utilisateur.go — la forme des données

> ⏱️ **Lecture : ~10 min** · 934 mots, 21 lignes de code

## C'est quoi ce fichier ?

Ce fichier ne contient AUCUNE action, aucune logique. Il définit juste "à quoi ressemble" un utilisateur et des identifiants de connexion, sous forme de deux `struct` (structures).

## Notions Go utilisées ici

### Qu'est-ce qu'une `struct` ?
Une struct est un "moule" qui regroupe plusieurs informations liées sous un seul nom. Un peu comme une fiche de renseignements avec des cases : nom, prénom, âge... En Go, on la définit ainsi :

```go
type Utilisateur struct {
    Id             int        `json:"id"`
    Email          string     `json:"email"`
    MotDePasseHash string     `json:"-"`
    Role           string     `json:"role"`
    Nom            *string    `json:"nom"`
    Prenom         *string    `json:"prenom"`
    DateNaissance  *time.Time `json:"date_naissance"`
    Telephone      *string    `json:"telephone"`
    Actif          bool       `json:"actif"`
}
```

- `type NomDeLaStruct struct { ... }` : la syntaxe pour créer une struct.
- Chaque ligne à l'intérieur est un "champ" (field) : un nom, suivi de son TYPE (`int` pour un nombre entier, `string` pour du texte, `bool` pour vrai/faux, `time.Time` pour une date).
- Une fois cette struct définie, on peut créer une "instance" (un exemplaire rempli) : `u := Utilisateur{Email: "test@mail.fr", Role: "adherent"}`.

### Les annotations entre backticks : `` `json:"id"` ``
Ce texte entre backticks (accent grave) s'appelle une "tag" en Go. Elle ne fait rien toute seule — elle sert d'INSTRUCTION pour d'autres packages qui savent la lire, ici `encoding/json`. Quand on transforme une struct Go en JSON (le format de données envoyé au front), Go utilise normalement le nom exact du champ Go (`Email`, avec majuscule). Grâce à `json:"email"`, on lui dit "dans le JSON, écris plutôt `email` en minuscule" — plus propre côté client web.

### Le cas spécial `json:"-"`
```go
MotDePasseHash string `json:"-"`
```
Le tiret veut dire "n'inclus JAMAIS ce champ dans le JSON, quoi qu'il arrive". C'est une sécurité importante : même si on encode accidentellement toute la struct `Utilisateur` en JSON pour la renvoyer au client (ce qu'on fait dans `Me`, voir `app/auth.go.md`), le mot de passe haché ne sortira jamais de l'API par erreur.

## Pourquoi Nom, Prenom, DateNaissance, Telephone sont des POINTEURS (`*string`, `*time.Time`)

Ces quatre informations sont facultatives : quelqu'un peut s'inscrire (`POST /auth/register`) sans les renseigner, donc elles peuvent être VIDES ("NULL") en base de données. Le problème, c'est qu'un `string` normal en Go ne peut jamais représenter "l'absence de valeur" — une chaîne vide `""` n'est pas la même chose que "on ne sait pas". C'est là qu'interviennent les pointeurs (voir aussi `db/db.go.md` pour une première explication des pointeurs) :

- `*string` veut dire "soit `nil` (rien du tout, littéralement aucune adresse), soit un pointeur vers un vrai texte quelque part en mémoire".
- Quand la base de données renvoie NULL pour la colonne `telephone`, Go met le champ `Telephone` à `nil`.
- Quand on transforme ça en JSON, un pointeur `nil` s'écrit naturellement comme `null` — exactement le résultat qu'on veut, sans code supplémentaire.

**Ce qu'on a essayé avant, et pourquoi on a changé** : au départ, ces champs utilisaient le type `sql.NullString`/`sql.NullTime` (fournis par le package `database/sql`, souvent recommandés pour gérer les valeurs NULL). Le problème découvert en testant : ce type s'encode en JSON comme `{"String":"","Valid":false}` au lieu de simplement `null` — pas du tout ce qu'on veut envoyer à un front qui attend du JSON propre. Les pointeurs Go natifs (`*string`, `*time.Time`) donnent directement le bon résultat sans manipulation supplémentaire, et `database/sql` sait très bien les gérer automatiquement quand on fait `Scan(&u.Telephone)`.

## La deuxième struct : Identifiants

```go
type Identifiants struct {
    Email      string `json:"email"`
    MotDePasse string `json:"mot_de_passe"`
}
```

Celle-ci représente uniquement la FORME du JSON envoyé par le client au moment de se connecter ou s'inscrire, du genre `{"email": "test@mail.fr", "mot_de_passe": "monmotdepasse"}`. Cette struct n'est **jamais enregistrée telle quelle en base** — elle sert uniquement de "boîte de réception" temporaire, avant transformation (notamment le hachage du mot de passe, voir `app/auth.go.md`).

### ⚠️ Cette struct a été renommée en Phase 10 (consolidation)

Elle s'appelait `Credentials`, avec un champ JSON `password`. C'était **la seule exception anglaise** de tout le projet : partout ailleurs les champs sont en français (`raison_sociale`, `date_debut`, `code_barre`, `montant_cotisation`…).

Le renommage a été fait au moment de la consolidation de l'API, avant d'écrire le front PHP. La raison du timing est importante à comprendre : **changer le nom d'un champ JSON casse le contrat de l'API**. Tant qu'aucun client ne consomme l'API, ça ne coûte rien ; une fois le front écrit, il aurait fallu modifier les deux en même temps. C'est le bon moment pour ce genre de correction.

Concrètement, l'appel de connexion est maintenant :

```json
{"email": "staff2@nomorewaste.fr", "mot_de_passe": "motdepasse123"}
```

Si tu envoies encore `"password"`, Go ne trouve pas le champ, le mot de passe reste vide, et tu obtiens `« Le mot de passe doit contenir entre 5 et 50 caracteres »` — un message trompeur qui ne dit pas que le nom du champ est faux. C'est exactement le piège rencontré pendant les tests.

## Pourquoi deux structs différentes et pas une seule ?

`Utilisateur` représente ce qui existe VRAIMENT en base de données (avec un mot de passe déjà haché, illisible). `Identifiants` représente ce que le client ENVOIE (avec un mot de passe en clair, de passage). Les mélanger reviendrait à risquer d'enregistrer un mot de passe non haché en base — c'est pour ça qu'on garde ces deux notions bien séparées, même si elles se ressemblent.

## Piège à connaître (utile pour le live coding)

Un `*string` en Go ne peut PAS être utilisé directement comme un `string` normal — on ne peut pas écrire `if u.Telephone == "0600000000"`, car `u.Telephone` est une ADRESSE, pas le texte lui-même. Pour lire la vraie valeur, il faut d'abord vérifier que le pointeur n'est pas `nil`, puis "déréférencer" avec une étoile : `if u.Telephone != nil && *u.Telephone == "0600000000"`. Oublier cette vérification et déréférencer un pointeur `nil` directement (`*u.Telephone` sans vérifier avant) fait planter le programme avec une erreur "nil pointer dereference".
