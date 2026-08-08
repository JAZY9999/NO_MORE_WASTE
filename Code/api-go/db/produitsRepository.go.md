# db/produitsRepository.go — retrouver un produit très rapidement

> ⏱️ **Lecture : ~5 min** · 439 mots, 28 lignes de code

## C'est quoi ce fichier ?

Le repository des produits en stock. C'est ici que se trouve la fonction qui répond directement à l'exigence du sujet : "chaque produit rapporté au siège devra être référencé (code barre), stocké et retrouvable très rapidement".

## Fonction 1 : CreateProduit

Même principe que `CreateCommercant`/`CreateAdhesion` (`INSERT ... RETURNING id`), avec 9 colonnes cette fois — rien de nouveau dans la logique, juste plus de champs à passer.

## Fonction 2 : GetProduitById

Classique : `QueryRow` + `Scan` par id.

## Fonction 3 : GetProduitByCodeBarre — LA fonction clé de cette phase

```go
func GetProduitByCodeBarre(codeBarre string) (*models.Produit, error) {
    var p models.Produit
    row := Conn.QueryRow(
        `SELECT id, code_barre, libelle, categorie, dlc, collecte_id, poids_kg, quantite, emplacement_id, statut
         FROM produits WHERE code_barre = $1`,
        codeBarre,
    )
    ...
}
```

Rien de compliqué dans le CODE lui-même — c'est une simple recherche par égalité (`WHERE code_barre = $1`), exactement comme une recherche par id. Ce qui rend cette recherche "très rapide" comme demandé par le sujet, ce n'est PAS le code Go, mais une ligne du fichier `schema.sql` :

```sql
CREATE INDEX idx_produits_code_barre ON produits(code_barre);
```

### C'est quoi un index, et pourquoi ça rend la recherche rapide

Sans index, pour trouver un produit par son code-barre, Postgres devrait regarder ligne par ligne TOUTE la table `produits` jusqu'à trouver celle qui correspond (ou conclure qu'aucune ne correspond, après avoir tout regardé) — ça s'appelle un "scan complet", et c'est lent quand la table contient beaucoup de lignes. Un index, c'est une structure de données annexe que Postgres maintient automatiquement, un peu comme l'index alphabétique à la fin d'un livre : au lieu de lire tout le livre pour trouver une info, on consulte l'index qui indique directement la bonne page. Grâce à `idx_produits_code_barre`, Postgres peut retrouver un produit par son code-barre quasi instantanément, même avec des millions de produits en stock.

La colonne `code_barre` est aussi déclarée `UNIQUE` dans `schema.sql` — ça garantit qu'il ne peut jamais exister deux produits avec le même code-barre (Postgres refuserait l'insertion), en plus de bénéficier automatiquement d'un index pour cette contrainte d'unicité.

## Fonction 4 : ListProduits (filtre combiné optionnel)

```go
func ListProduits(categorie *string, statut *string) ([]models.Produit, error) {
    requete := "SELECT ... FROM produits WHERE 1=1"
    var arguments []interface{}
    numeroParametre := 1

    if categorie != nil {
        requete += fmt.Sprintf(" AND categorie = $%d", numeroParametre)
        arguments = append(arguments, *categorie)
        numeroParametre++
    }
    if statut != nil {
        ...
    }
    ...
}
```

Même technique que `ResoudreDestinatairesCampagne` (voir `db/campagnesRepository.go.md`) : on construit la requête avec des critères optionnels, chacun ajouté seulement s'il est fourni (pointeur non-`nil`), toujours via des paramètres `$N` jamais du texte collé directement — donc pas de risque d'injection SQL ici non plus. Permet des recherches comme "tous les produits en stock" (`statut=en_stock`), "tous les produits de catégorie boulangerie" (`categorie=boulangerie`), ou la combinaison des deux.

## Fonction 5 : UpdateProduitEmplacementEtStatut

Un `UPDATE` simple (même principe que `UpdateAdhesion`, voir `db/adhesionsRepository.go.md`) qui change à la fois l'emplacement et le statut d'un produit en une seule requête — utilisé quand on déplace physiquement un produit dans l'entrepôt ou qu'on change son état (par exemple de `en_stock` à `distribue`).
