# db/campagnesRepository.go — construire une requête avec des critères optionnels, en toute sécurité

> ⏱️ **Lecture : ~10 min** · 681 mots, 26 lignes de code

## C'est quoi ce fichier ?

Le repository des campagnes : créer une campagne, la récupérer, la lister, et surtout **`ResoudreDestinatairesCampagne`** — la fonction qui transforme les critères d'une campagne (ville, pays, statut, ancienneté) en une vraie liste de commerçants qui correspondent.

## Les fonctions simples : CreateCampagne, GetCampagneById, ListCampagnes

Rien de nouveau ici par rapport à `db/commercantsRepository.go.md` et `db/adhesionsRepository.go.md` : `INSERT ... RETURNING id`, `QueryRow` + `Scan` avec gestion de `sql.ErrNoRows`, `Query` + boucle `for rows.Next()`.

## ResoudreDestinatairesCampagne : le cœur du système de segmentation

C'est la fonction la plus délicate du projet, parce qu'elle doit construire une requête SQL **dynamique** — le nombre de conditions dans le `WHERE` dépend de quels critères sont réellement définis sur la campagne. Voici comment on fait ça de façon sûre.

```go
func ResoudreDestinatairesCampagne(c models.Campagne) ([]models.DestinataireCampagne, error) {
    requete := `
        SELECT DISTINCT c.id, c.raison_sociale, c.email
        FROM commercants c
        LEFT JOIN adhesions a ON a.commercant_id = c.id
        WHERE 1=1
    `
    var arguments []interface{}
    numeroParametre := 1

    if c.CritereVille != nil {
        requete += fmt.Sprintf(" AND c.ville = $%d", numeroParametre)
        arguments = append(arguments, *c.CritereVille)
        numeroParametre++
    }
    ...
}
```

### `WHERE 1=1`
Une astuce classique : `1=1` est toujours vrai, donc cette ligne ne filtre rien du tout par elle-même. Elle sert juste de point de départ pour pouvoir ajouter chaque critère avec `AND ...` ensuite, sans avoir à gérer le cas particulier "est-ce que c'est le premier critère (auquel cas il faudrait écrire `WHERE` au lieu de `AND`) ou pas ?".

### Pour chaque critère : "si défini, alors on ajoute une condition"
```go
if c.CritereVille != nil {
    requete += fmt.Sprintf(" AND c.ville = $%d", numeroParametre)
    arguments = append(arguments, *c.CritereVille)
    numeroParametre++
}
```
Trois choses se passent à chaque critère rempli :
1. On ajoute un morceau de texte SQL à la requête (`" AND c.ville = $2"` par exemple), mais avec un symbole `$N`, jamais la vraie valeur collée directement dans le texte.
2. On ajoute la VALEUR RÉELLE (`*c.CritereVille`, la valeur pointée) dans une liste séparée (`arguments`).
3. On incrémente le compteur `numeroParametre`, pour que le prochain critère utilise `$3`, puis `$4`, etc. — dans l'ordre exact où les valeurs seront données à la fin.

### Pourquoi c'est sûr contre l'injection SQL (le point le plus important)

Ce qui est construit dynamiquement, c'est UNIQUEMENT la STRUCTURE de la requête (quelles colonnes sont testées, combien de conditions il y a) — jamais les VALEURS elles-mêmes. Les valeurs (`*c.CritereVille`, etc.) ne sont jamais collées dans le texte SQL : elles voyagent séparément, dans la liste `arguments`, et c'est Postgres/le driver Go qui les insère de façon sécurisée à la place des `$N` au moment de l'exécution. Un utilisateur ne peut donc jamais "injecter" du faux SQL via une valeur de critère, même s'il essayait de mettre du code SQL dans le champ ville — ce serait juste traité comme une chaîne de caractères à chercher, jamais exécuté.

C'est très différent d'un "query builder" totalement libre (où l'utilisateur choisirait lui-même la COLONNE à filtrer, en texte libre) : ici, les colonnes possibles (`c.ville`, `c.pays`, `a.statut`, calcul sur `a.date_fin`) sont toutes FIXÉES À L'AVANCE dans le code Go — le staff ne peut choisir QUE parmi ces 4 critères prédéfinis, jamais inventer une nouvelle condition. C'est le choix "critères fixes combinables" plutôt que "constructeur de requête libre", qui simplifie énormément la sécurisation.

### `Conn.Query(requete, arguments...)`
Une fois tous les critères parcourus, on exécute la requête complète avec `arguments...` (les trois petits points étalent la liste `arguments` en autant d'arguments séparés qu'il y a d'éléments dedans — nécessaire car `Query` accepte un nombre variable d'arguments, pas une liste).

### `LEFT JOIN` plutôt qu'un `JOIN` simple
```sql
LEFT JOIN adhesions a ON a.commercant_id = c.id
```
Un `JOIN` normal exigerait qu'un commerçant ait AU MOINS une adhésion pour apparaître dans le résultat. Un `LEFT JOIN` garde TOUS les commerçants, même ceux qui n'ont encore aucune adhésion (dans ce cas, les colonnes venant de `adhesions` seraient `NULL`) — utile si on veut un jour faire une campagne qui ne filtre QUE sur la ville, sans se soucier du statut d'adhésion.

### `SELECT DISTINCT`
Comme un commerçant peut avoir PLUSIEURS adhésions au fil du temps (renouvellements successifs), le `JOIN` pourrait faire apparaître le même commerçant plusieurs fois dans le résultat brut. `DISTINCT` élimine les doublons, pour qu'un commerçant ne reçoive jamais deux fois le même email de campagne.

## EnregistrerCampagneEnvoi

Un simple `INSERT` dans `campagne_envois`, appelé après chaque envoi réussi (voir `app/campagnes.go.md`) — garde une trace de qui a reçu quelle campagne et quand.
