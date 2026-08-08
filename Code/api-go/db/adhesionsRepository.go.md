# db/adhesionsRepository.go — les requêtes SQL pour les adhésions

> ⏱️ **Lecture : ~9 min** · 550 mots

## C'est quoi ce fichier ?

Le repository de la table `adhesions` : créer une adhésion, en récupérer une par son id, la modifier, lister celles d'un commerçant, et **lister toutes les adhésions**.

## Fonction 1 : CreateAdhesion

```go
func CreateAdhesion(a models.Adhesion) (int, error) {
    var id int
    err := Conn.QueryRow(
        "INSERT INTO adhesions (commercant_id, date_debut, date_fin, statut, montant_cotisation) VALUES ($1, $2, $3, $4, $5) RETURNING id",
        a.CommercantId, a.DateDebut, a.DateFin, a.Statut, a.MontantCotisation,
    ).Scan(&id)
    ...
}
```

Rien de nouveau ici par rapport à `db/commercantsRepository.go.md` : même principe de `RETURNING id` pour récupérer l'identifiant généré tout de suite après l'insertion.

## Fonction 2 : GetAdhesionById

Identique dans l'esprit à `GetCommercantById` (voir `db/commercantsRepository.go.md`) : `QueryRow` + `Scan`, avec le cas `sql.ErrNoRows` géré pour distinguer "aucune adhésion trouvée" (retourne `nil, nil`) d'une vraie erreur technique.

## Fonction 3 : UpdateAdhesion (la nouveauté : un `UPDATE` SQL)

```go
func UpdateAdhesion(id int, a models.Adhesion) error {
    _, err := Conn.Exec(
        "UPDATE adhesions SET date_debut = $1, date_fin = $2, statut = $3, montant_cotisation = $4 WHERE id = $5",
        a.DateDebut, a.DateFin, a.Statut, a.MontantCotisation, id,
    )
    ...
}
```

Premier `UPDATE` du projet : `Conn.Exec(...)` sert ici, comme pour un `INSERT`, à envoyer une requête qui MODIFIE la base sans qu'on ait besoin de lire un résultat (pas de `RETURNING` ici, on n'a pas besoin de récupérer une nouvelle valeur).

**Point important sur le `WHERE`** : la clause `WHERE id = $5` est ce qui garantit qu'on modifie UNIQUEMENT la ligne demandée, pas toutes les adhésions de la table. L'id à modifier est passé en dernier paramètre (`$5`), après les 4 valeurs à mettre à jour — l'ordre des `$N` doit toujours correspondre exactement à l'ordre des arguments donnés après la chaîne SQL.

## Piège à connaître

Cette fonction ne vérifie PAS elle-même que l'id existe avant de faire l'`UPDATE` — si on lui donne un id qui n'existe pas, la requête SQL "réussit" quand même techniquement (elle modifie 0 ligne, sans erreur). C'est pour ça que le handler appelant (`ModifierAdhesion`, voir `app/adhesions.go.md`) vérifie D'ABORD avec `GetAdhesionById` que l'adhésion existe, avant d'appeler `UpdateAdhesion` — sinon on répondrait `204 No Content` (succès) à quelqu'un qui a essayé de modifier une ressource qui n'existe pas, ce qui serait trompeur.

## Fonction 4 : ListAdhesionsByCommercant

Toutes les adhésions d'**un** commerçant, triées par `date_fin DESC` — la plus récente en premier, car c'est elle qui dit si le partenaire est à jour. Utilisée par l'espace client et par la fiche commerçant du back-office.

## Fonction 5 : ListAdhesions — la route qui manquait

> 🔄 Ajoutée en portant l'écran des adhésions du back-office (phase 11).

```go
func ListAdhesions(statut *string) ([]models.AdhesionDetaillee, error) {
    requete := `
        SELECT a.id, a.commercant_id, c.raison_sociale, c.email,
               a.date_debut, a.date_fin, a.statut, a.montant_cotisation,
               (a.date_fin - CURRENT_DATE) AS jours_restants
        FROM adhesions a
        JOIN commercants c ON c.id = a.commercant_id`

    var arguments []interface{}
    if statut != nil {
        arguments = append(arguments, *statut)
        requete += " WHERE a.statut = $1"
    }

    requete += " ORDER BY a.date_fin"
    ...
}
```

### Le trou qu'elle comble

Avant cette fonction, **aucune route ne listait toutes les adhésions**. Seule `ListAdhesionsARenouveler(joursAvant)` existait, et elle ne renvoie que celles qui tombent à J-30 ou J-7 **exactement**. Le back-office ne pouvait donc pas voir ce qu'il est censé gérer : combien d'adhésions sont actives, lesquelles ont expiré.

### Un filtre facultatif, construit comme dans `ListPlanning`

La **structure** de la requête change selon que `statut` est fourni ou non, mais la **valeur** passe toujours par `$1` — jamais concaténée directement dans la chaîne. C'est ce qui protège de l'injection SQL, même quand la requête est construite dynamiquement.

Le tri est par `date_fin` : la plus proche échéance en premier, celle sur laquelle il faut agir. Un tri par identifiant n'aurait aucun sens métier ici.

### `make(...)` plutôt que `var`

```go
resultats := make([]models.AdhesionDetaillee, 0)
```

`var resultats []models.AdhesionDetaillee` donnerait une slice `nil` si aucune ligne ne correspond, et Go l'encoderait en JSON comme `null` plutôt que `[]`. Le front devrait alors rattraper ce cas avec un `?? []` — `make(..., 0)` évite le problème à la source, en garantissant toujours un tableau, même vide.
