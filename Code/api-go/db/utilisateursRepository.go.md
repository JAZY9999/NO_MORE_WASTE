# db/utilisateursRepository.go — les requêtes SQL pour les utilisateurs

> ⏱️ **Lecture : ~10 min** · 931 mots, 36 lignes de code

## C'est quoi ce fichier ?

C'est le SEUL fichier du projet qui a le droit d'écrire du vrai SQL pour manipuler la table `utilisateurs`. Le reste du code (dans le dossier `app/`) ne doit jamais écrire de requête SQL directement — il appelle juste les fonctions de ce fichier. Ça permet, par exemple, de changer complètement la façon d'interroger la base sans toucher au reste du programme.

Un fichier de ce genre s'appelle un "repository" (dépôt) : il représente l'endroit central où on va chercher/ranger un type de donnée.

## Fonction 1 : GetUtilisateurByEmail

```go
func GetUtilisateurByEmail(email string) (*models.Utilisateur, error) {
    var u models.Utilisateur
    row := Conn.QueryRow("SELECT id, email, mot_de_passe_hash, role, nom, prenom, date_naissance, telephone, actif FROM utilisateurs WHERE email = $1", email)
    err := row.Scan(&u.Id, &u.Email, &u.MotDePasseHash, &u.Role, &u.Nom, &u.Prenom, &u.DateNaissance, &u.Telephone, &u.Actif)
    if err == sql.ErrNoRows {
        return nil, nil
    }
    if err != nil {
        return nil, fmt.Errorf("GetUtilisateurByEmail (email=%v) : %w", email, err)
    }
    return &u, nil
}
```

### Que retourne cette fonction ?
`(*models.Utilisateur, error)` : deux valeurs à la fois. La première est un POINTEUR vers un `Utilisateur` (voir `models/utilisateur.go.md` pour la struct, et `db.go.md` pour l'explication des pointeurs) — ou `nil` (rien, vide) si aucun utilisateur n'a été trouvé. La deuxième est une éventuelle erreur technique.

### Ligne par ligne

```go
row := Conn.QueryRow("SELECT id, email, mot_de_passe_hash, role, nom, prenom, date_naissance, telephone, actif FROM utilisateurs WHERE email = $1", email)
```
`Conn.QueryRow(...)` envoie une requête SQL qui doit renvoyer AU MAXIMUM une seule ligne. Le `$1` dans la requête est un "paramètre préparé" : au lieu de coller directement la variable `email` dans le texte de la requête (ce qui serait dangereux, voir plus bas), on met un symbole `$1` et on donne la vraie valeur juste après, en argument. Go/Postgres remplace `$1` par la valeur de façon sécurisée.

```go
err := row.Scan(&u.Id, &u.Email, &u.MotDePasseHash, &u.Role, &u.Nom, &u.Prenom, &u.DateNaissance, &u.Telephone, &u.Actif)
```
`Scan` copie chaque colonne retournée par la requête SQL dans les champs de notre variable `u` (de type `Utilisateur`). Notez le `&` devant chaque champ : ça veut dire "voici l'ADRESSE de cette variable" (encore un pointeur). `Scan` a besoin de connaître l'adresse pour pouvoir écrire directement dedans, plutôt que de nous retourner une copie qu'on devrait ensuite réassigner nous-mêmes.

**Important** : l'ordre des `&u.Xxx` dans `Scan` doit correspondre EXACTEMENT à l'ordre des colonnes dans le `SELECT` juste au-dessus. Si on inversait `email` et `role` dans le `SELECT` sans inverser aussi le `Scan`, on se retrouverait avec un email qui contient en fait un rôle, sans que Go ne s'en aperçoive automatiquement !

**Cas particulier des colonnes qui peuvent être vides (nom, prenom, date_naissance, telephone)** : ces champs sont déclarés comme des pointeurs (`*string`, `*time.Time`) dans la struct `Utilisateur` (voir `models/utilisateur.go.md`). `Scan` sait automatiquement gérer ce cas : si la colonne SQL est NULL, il met le pointeur Go à `nil` ; sinon, il crée une nouvelle valeur en mémoire et fait pointer notre champ vers elle. On n'a besoin d'écrire aucun code spécial pour ça, `database/sql` s'en charge tout seul du moment que le type du champ est bien un pointeur.

```go
if err == sql.ErrNoRows {
    return nil, nil
}
```
`sql.ErrNoRows` est une erreur SPÉCIALE fournie par Go qui veut dire précisément "la requête a réussi, mais elle n'a trouvé aucune ligne". Ce n'est pas un vrai problème technique (pas un bug), donc on retourne `nil, nil` (pas d'utilisateur trouvé, pas d'erreur) plutôt que de traiter ça comme une vraie erreur.

```go
if err != nil {
    return nil, fmt.Errorf("GetUtilisateurByEmail (email=%v) : %w", email, err)
}
```
Si une AUTRE erreur (une vraie, comme un problème de connexion) s'est produite, on la retransmet à l'appelant, en ajoutant un peu de contexte (`fmt.Errorf` fonctionne comme `Sprintf` mais fabrique une erreur plutôt qu'une simple chaîne de texte). Ça aide énormément à déboguer : sans ce message, on saurait juste "il y a eu une erreur", pas dans quelle fonction ni avec quel email.

### ⚠️ Pourquoi `%w` et pas `%v` (corrigé en Phase 10)

Regarde bien le verbe de formatage :

```go
fmt.Errorf("GetUtilisateurByEmail (email=%v) : %w", email, err)
```

Le premier est `%v` (pour l'email, une valeur normale), mais celui de l'erreur est **`%w`**. Ce n'est pas une faute de frappe.

- `%v` transforme l'erreur en **texte**. On garde le message, mais l'erreur d'origine est **perdue** — il ne reste qu'une phrase.
- `%w` (**w** comme *wrap*, emballer) garde l'erreur d'origine **à l'intérieur** de la nouvelle. On peut la ressortir plus tard.

À l'affichage, les deux donnent exactement la même chose. La différence n'apparaît que quand on veut **inspecter** l'erreur — par exemple pour savoir si PostgreSQL a refusé une clé étrangère, et répondre 400 au lieu de 500.

Tout le projet utilisait `%v` avec `err.Error()` au départ. Les **97 lignes** concernées ont été converties en `%w` pendant la consolidation, précisément pour rendre cette inspection possible. Voir [`utils/erreurs.go.md`](../utils/erreurs.go.md) pour ce que ça permet concrètement.

**Règle simple à retenir : quand tu enveloppes une erreur, c'est toujours `%w`.**

## Fonction 2 : CreateUtilisateur

```go
func CreateUtilisateur(email string, motDePasseHash string, role string) error {
    _, err := Conn.Exec("INSERT INTO utilisateurs (email, mot_de_passe_hash, role) VALUES ($1, $2, $3)",
        email, motDePasseHash, role)
    if err != nil {
        return fmt.Errorf("CreateUtilisateur : %w", err)
    }
    return nil
}
```

`Conn.Exec(...)` sert pour les requêtes qui MODIFIENT la base (ici un `INSERT`) sans qu'on ait besoin de lire un résultat ligne par ligne comme avec `Query`/`QueryRow`. Le `_` au début (`_, err := ...`) veut dire "je récupère cette première valeur de retour mais je ne m'en sers pas" (ici, des infos techniques sur combien de lignes ont été affectées — pas utile ici).

## Pourquoi les `$1`, `$2`, `$3` sont importants pour la sécurité

Si on avait écrit directement la requête en collant les variables dedans (par exemple avec `fmt.Sprintf("INSERT INTO utilisateurs (email) VALUES ('%s')", email)`), un utilisateur malveillant pourrait taper un email du genre `x'); DROP TABLE utilisateurs; --` et complètement casser la base de données — c'est ce qu'on appelle une "injection SQL". En utilisant `$1`, `$2`, etc., Go et Postgres traitent TOUJOURS la valeur comme une simple donnée, jamais comme du code SQL exécutable, quoi que l'utilisateur essaie d'y mettre.

## Piège à connaître

Le mot de passe passé à `CreateUtilisateur` (le paramètre `motDePasseHash`) n'est JAMAIS le mot de passe tapé par l'utilisateur — il a déjà été transformé (haché) avant d'arriver ici, dans `app/auth.go`. Ce fichier ne sait pas et ne se soucie pas que c'est un mot de passe haché : il stocke/lit juste ce qu'on lui donne, tel quel.
