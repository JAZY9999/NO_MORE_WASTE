# db/commercantsRepository.go — les requêtes SQL pour les commerçants

> ⏱️ **Lecture : ~14 min** · 850 mots

## C'est quoi ce fichier ?

Comme `db/utilisateursRepository.go`, c'est le seul fichier autorisé à écrire du SQL pour la table `commercants`. Il contient cinq fonctions : créer un commerçant, en récupérer un par son id ou par le compte qui lui est rattaché, lister tous les commerçants, et **le modifier**.

## Fonction 1 : CreateCommercant

```go
func CreateCommercant(c models.Commercant) (int, error) {
    var id int
    err := Conn.QueryRow(
        `INSERT INTO commercants
           (raison_sociale, siret, adresse, ville, pays, email, telephone, contact_nom, utilisateur_id)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9) RETURNING id`,
        c.RaisonSociale, c.Siret, c.Adresse, c.Ville, c.Pays, c.Email, c.Telephone, c.ContactNom,
        c.UtilisateurId,
    ).Scan(&id)
    if err != nil {
        return 0, fmt.Errorf("CreateCommercant : %w", err)
    }
    return id, nil
}
```

### La nouveauté par rapport à CreateUtilisateur : `RETURNING id`

Dans `db/utilisateursRepository.go.md`, `CreateUtilisateur` utilisait `Conn.Exec(...)` pour un simple `INSERT`, sans avoir besoin de savoir quel id Postgres a attribué à la nouvelle ligne. Ici, on a BESOIN de connaître l'id généré tout de suite après la création (pour le renvoyer au client, qui pourra ensuite faire des requêtes du genre `GET /commercants/{id}`).

`RETURNING id` est une fonctionnalité spéciale de Postgres : elle demande à la base de RENVOYER une colonne juste après l'insertion, comme si c'était un `SELECT`. Du coup, au lieu d'utiliser `Conn.Exec(...)` (qui ne renvoie rien à lire), on utilise `Conn.QueryRow(...).Scan(&id)` — exactement comme pour lire un résultat normal, sauf que la "ligne lue" est en fait le résultat de l'insertion.

### `utilisateur_id`, ajouté en portant l'espace client

Il manquait à l'origine. Conséquence concrète : une boutique créée par l'API n'était rattachée à **aucun** compte, et son propriétaire ne pouvait pas ouvrir son espace client — une exigence du sujet. La seule façon de faire la liaison était une requête SQL à la main.

La colonne est `UNIQUE` : rattacher un compte déjà pris répond **409**, grâce au code `23505` traité dans `utils.ErreurServeur` — rien à vérifier manuellement ici.

### Pourquoi 9 `$1` à `$9` et pas de raccourci

Chaque `$N` correspond, dans l'ordre, à chaque argument donné après la requête (`c.RaisonSociale` → `$1`, `c.Siret` → `$2`, etc.). Il faut que le nombre de `$N` corresponde exactement au nombre d'arguments fournis, sinon Go renvoie une erreur au moment de l'exécution.

## Fonction 2 : UpdateCommercant — la route qui manquait

```go
func UpdateCommercant(id int, c models.Commercant) error {
    _, err := Conn.Exec(
        `UPDATE commercants
         SET raison_sociale = $1, siret = $2, adresse = $3, ville = $4, pays = $5,
             email = $6, telephone = $7, contact_nom = $8, utilisateur_id = $9
         WHERE id = $10`,
        c.RaisonSociale, c.Siret, c.Adresse, c.Ville, c.Pays,
        c.Email, c.Telephone, c.ContactNom, c.UtilisateurId, id,
    )
    ...
}
```

### Un remplacement complet, une fusion faite en amont

Cette fonction écrit **tous** les champs à chaque appel — contrairement à ce qu'on pourrait attendre d'une "mise à jour partielle". Ce n'est pas une contradiction : le handler `ModifierCommercant` (voir `app/commercants.go.md`) relit d'abord la fiche existante et **fusionne** les champs envoyés avec ceux déjà en base, avant d'appeler cette fonction.

Quand la requête arrive ici, la structure contient donc déjà l'état final voulu — il n'y a plus qu'à l'écrire. Une requête SQL construite dynamiquement selon les champs présents serait plus longue et plus difficile à lire, pour ne rien apporter de plus.

C'est `utilisateur_id` qui rend possible de rattacher une boutique **déjà créée** au compte de son propriétaire. Sans cette fonction, le rattachement n'était possible qu'à la création — et une boutique enregistrée sans compte restait orpheline pour toujours.

## Fonction 3 : GetCommercantById

```go
func GetCommercantById(id int) (*models.Commercant, error) {
    var c models.Commercant
    row := Conn.QueryRow(
        "SELECT id, raison_sociale, siret, adresse, ville, pays, email, telephone, contact_nom, utilisateur_id FROM commercants WHERE id = $1",
        id,
    )
    err := row.Scan(&c.Id, &c.RaisonSociale, &c.Siret, &c.Adresse, &c.Ville, &c.Pays, &c.Email, &c.Telephone, &c.ContactNom, &c.UtilisateurId)
    if err == sql.ErrNoRows {
        return nil, nil
    }
    ...
}
```

Même logique exactement que `GetUtilisateurByEmail` (voir `db/utilisateursRepository.go.md`) : on cherche une seule ligne par son identifiant, on la copie dans la struct avec `Scan`, et si Postgres ne trouve rien (`sql.ErrNoRows`), on retourne `nil, nil` plutôt qu'une vraie erreur.

## Fonction 4 : GetCommercantByUtilisateurId — le chemin de l'espace client

C'est la fonction qui rend l'espace client possible : on part du compte connecté (issu du jeton) pour retrouver **sa** fiche. Le client ne fournit jamais d'identifiant de commerçant — sinon il suffirait d'en essayer un autre pour lire les données de quelqu'un d'autre.

Retourne `(nil, nil)` si le compte n'est rattaché à aucun commerçant : c'est le cas normal d'un adhérent inscrit qui n'a pas encore de fiche, traduit par le front en un écran dédié plutôt qu'en erreur.

## Fonction 5 : ListCommercants (la nouveauté : lire PLUSIEURS lignes)

```go
func ListCommercants() ([]models.Commercant, error) {
    rows, err := Conn.Query(
        "SELECT id, raison_sociale, siret, adresse, ville, pays, email, telephone, contact_nom, utilisateur_id FROM commercants ORDER BY id",
    )
    if err != nil {
        return nil, fmt.Errorf("ListCommercants : %w", err)
    }
    defer rows.Close()

    var commercants []models.Commercant
    for rows.Next() {
        var c models.Commercant
        err := rows.Scan(&c.Id, &c.RaisonSociale, &c.Siret, &c.Adresse, &c.Ville, &c.Pays, &c.Email, &c.Telephone, &c.ContactNom, &c.UtilisateurId)
        if err != nil {
            return nil, fmt.Errorf("ListCommercants (scan) : %w", err)
        }
        commercants = append(commercants, c)
    }
    return commercants, nil
}
```

### `Conn.Query` au lieu de `Conn.QueryRow`
`Query` (sans "Row") sert quand on s'attend à recevoir PLUSIEURS lignes, pas une seule. Le résultat est de type `*sql.Rows` (avec un "s"), qu'on doit parcourir ligne par ligne.

### `defer rows.Close()`
Le mot-clé `defer` en Go dit "exécute cette instruction juste avant que la fonction se termine, peu importe comment elle se termine (normalement, ou à cause d'une erreur)". Ici, `rows.Close()` libère les ressources techniques utilisées par la requête. On la place juste après avoir vérifié qu'il n'y a pas d'erreur, et Go se charge de l'appeler automatiquement à la fin, où que se termine la fonction — même si on a plusieurs `return` différents plus bas, on n'a pas besoin de répéter `rows.Close()` avant chacun.

### La boucle `for rows.Next() { ... }`
`rows.Next()` avance d'une ligne à chaque tour de boucle, et retourne `false` quand il n'y a plus de lignes (ce qui arrête automatiquement la boucle `for`). À l'intérieur, on crée une NOUVELLE variable `c` à chaque tour (`var c models.Commercant`), on la remplit avec `Scan`, puis on l'ajoute à la liste finale avec `append`.

**Piège à connaître** : il faut bien déclarer `var c models.Commercant` À L'INTÉRIEUR de la boucle `for`, pas avant. Si on la déclarait une seule fois avant la boucle et qu'on faisait juste `append(commercants, c)` à chaque tour, on ajouterait plusieurs fois la MÊME variable réutilisée à la liste — dans ce cas précis Go recopie la valeur donc ce n'est pas un souci flagrant, mais c'est une habitude à prendre pour éviter des bugs plus subtils avec des types plus complexes.

## Fichiers liés

- [../app/commercants.go.md](../app/commercants.go.md) — les handlers, dont `ModifierCommercant` qui fusionne avant d'appeler `UpdateCommercant`
- [../models/commercant.go.md](../models/commercant.go.md) — le champ `UtilisateurId`
- [../utils/erreurs.go.md](../utils/erreurs.go.md) — le code `23505` qui protège des doublons de compte
- [../../front-php/app/Controllers/Back/CommercantsController.php.md](../../front-php/app/Controllers/Back/CommercantsController.php.md) — l'écran qui a révélé le trou
