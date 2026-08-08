# `db/traductionsRepository.go` — les requêtes SQL du multilingue

> ⏱️ **Lecture : ~10 min** · 619 mots, 24 lignes de code

> **Phase 8**. Le fichier le plus intéressant du module : il contient le seul `ON CONFLICT` du projet.

## Les deux tables

```sql
langues     (code PK, libelle)
traductions (id, cle, valeur, code_langue -> langues.code)
```

### Pourquoi `code` comme clé primaire, et pas un `id` numérique

Le projet UpcycleConnect utilisait `id_langue` (un entier). Ici la clé primaire est le **code** (`'fr'`, `'en'`).

La raison : ce code est **déjà** ce qu'on manipule partout ailleurs — le nom du fichier (`fr.json`), le paramètre d'URL (`?lang=fr`), l'attribut HTML (`<html lang="fr">`). Avec un identifiant numérique, il faudrait une jointure ou une requête supplémentaire à chaque fois pour retrouver `fr` à partir de `2`.

Un identifiant numérique se justifie quand la valeur peut changer. Un code de langue, non : `fr` restera `fr`.

## La contrainte `UNIQUE (cle, code_langue)`

```sql
UNIQUE (cle, code_langue)
```

Elle interdit deux lignes `nav.accueil` en français. **Sans elle** — et UpcycleConnect ne l'avait pas — rien n'empêche le doublon, et l'affichage devient imprévisible : c'est celle que la base renvoie en premier qui gagne, sans garantie de stabilité.

Elle rend surtout possible l'`ON CONFLICT` ci-dessous.

## `EnregistrerTraduction` — le cœur du fichier

```sql
INSERT INTO traductions (cle, valeur, code_langue)
VALUES ($1, $2, $3)
ON CONFLICT (cle, code_langue)
DO UPDATE SET valeur = EXCLUDED.valeur
```

C'est un **upsert** : *update* + *insert*. « Insère ; si le couple (clé, langue) existe déjà, mets simplement à jour sa valeur. »

`EXCLUDED` est un mot-clé PostgreSQL désignant **la ligne qu'on essayait d'insérer**. `EXCLUDED.valeur` = la nouvelle valeur. C'est ce qui permet d'écrire la mise à jour sans répéter le paramètre.

### Ce que ça évite

Sans `ON CONFLICT`, importer un fichier de 63 clés imposerait, pour chacune :

```
1. SELECT — est-ce que cette clé existe déjà pour cette langue ?
2. puis INSERT ou UPDATE selon la réponse
```

Soit **126 requêtes au lieu de 63**, et surtout une **condition de course** : entre le SELECT et l'INSERT, un autre import pourrait avoir créé la ligne — l'INSERT échouerait alors sur la contrainte d'unicité.

Avec `ON CONFLICT`, tout se joue en **une seule requête atomique**. PostgreSQL garantit qu'aucune autre opération ne s'intercale.

### La conséquence utile

L'import devient **idempotent** : le relancer donne exactement le même résultat, sans jamais créer de doublon. On peut donc relancer sans se demander si l'import précédent est passé.

## `ON DELETE CASCADE`

```sql
code_langue VARCHAR(5) NOT NULL REFERENCES langues(code) ON DELETE CASCADE
```

Supprimer une langue supprime **automatiquement** toutes ses traductions. `DeleteLangue` ne fait donc qu'un `DELETE FROM langues` — PostgreSQL s'occupe du reste.

Sans cascade, il faudrait penser à supprimer les traductions d'abord, sinon la base refuserait la suppression (des lignes pointeraient vers une langue disparue). Un oubli laisserait des traductions orphanes, invisibles et impossibles à nettoyer depuis l'interface.

## `make` plutôt que `nil`

```go
langues := make([]models.Langue, 0)
```

Une slice Go déclarée sans être remplie vaut `nil`, et `encoding/json` l'encode **`null`** — pas `[]`. Le front devrait alors gérer ce cas particulier partout.

Avec `make(..., 0)`, une liste vide reste `[]` : le front peut boucler dessus sans précaution.

> C'est le piège qui oblige le contrôleur PHP à écrire `$reponse['corps'] ?? []` sur les autres modules. Ici, il est réglé à la source.

## Le filtre optionnel de `ListTraductions`

```go
if codeLangue != "" {
    requete += " WHERE code_langue = $1"
    arguments = append(arguments, codeLangue)
}
```

La **structure** de la requête change selon le filtre, mais la **valeur** passe toujours par `$1`.

C'est le point à savoir défendre : construire du SQL dynamiquement n'est pas dangereux **tant que les valeurs restent paramétrées**. Ce qui serait une faille, c'est :

```go
requete += " WHERE code_langue = '" + codeLangue + "'"  // ❌ injection SQL
```

Là, un `codeLangue` valant `' OR 1=1 --` changerait le sens de la requête. Avec `$1`, PostgreSQL traite la valeur comme une donnée, jamais comme du code.

## `CompterTraductions`

Une seule ligne (`SELECT COUNT(*)`), mais elle sert de **garde-fou** avant de régénérer les fichiers JSON : si la base est vide, le front refuse l'export au lieu d'écraser les fichiers par du vide.

Voir [`../../front-php/app/services/Traductions.php.md`](../../front-php/app/services/Traductions.php.md) pour le scénario de perte de données que ça évite.

## Fichiers liés

- [../models/traduction.go](../models/traduction.go) — les deux structs
- [../app/traductions.go](../app/traductions.go) — les 8 handlers
- [../../front-php/app/locales/LISEZ-MOI.md](../../front-php/app/locales/LISEZ-MOI.md) — pourquoi base **et** fichiers JSON
