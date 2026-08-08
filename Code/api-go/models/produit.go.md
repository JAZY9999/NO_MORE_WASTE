# models/produit.go — un produit rapporté au siège

> ⏱️ **Lecture : ~5 min** · 307 mots, 13 lignes de code

## C'est quoi ce fichier ?

La struct `Produit`, qui représente chaque objet/aliment ramené par une collecte (Phase 4, pas encore codée) et stocké dans un entrepôt.

## Le code

```go
type Produit struct {
    Id            int      `json:"id"`
    CodeBarre     string   `json:"code_barre"`
    Libelle       string   `json:"libelle"`
    Categorie     *string  `json:"categorie"`
    Dlc           *string  `json:"dlc"`
    CollecteId    *int     `json:"collecte_id"`
    PoidsKg       *float64 `json:"poids_kg"`
    Quantite      int      `json:"quantite"`
    EmplacementId *int     `json:"emplacement_id"`
    Statut        string   `json:"statut"`
}
```

### `CodeBarre` et `Libelle` sont obligatoires, le reste est optionnel
Même logique que partout ailleurs dans le projet : les champs `string`/`int` normaux sont ceux qui DOIVENT être fournis, les pointeurs (`*string`, `*int`, `*float64`) sont ceux qui PEUVENT être vides.

### Pourquoi `CollecteId` est un pointeur `*int` (nouveau cas : une clé étrangère optionnelle)
Contrairement à `CommercantId` sur `Adhesion` (qui est un `int` normal, toujours obligatoire, voir `models/adhesion.go.md`), ici `CollecteId` est optionnel. Raison : la Phase 4 (gestion des collectes) n'est pas encore codée dans le projet — un produit doit pouvoir exister SANS être encore rattaché à une collecte précise. Une fois la Phase 4 codée, on pourra créer des produits en les rattachant directement à une collecte via ce champ, mais ce n'est pas obligatoire dès maintenant.

### Pourquoi `PoidsKg` est un `*float64` et pas un `*int`
Un poids peut avoir des décimales (par exemple 2.5 kg) — `float64` est le type Go pour les nombres à virgule (flottants), utilisé ici car la colonne SQL correspondante (`NUMERIC(10, 3)`, voir `schema.sql`) accepte des décimales.

## Piège à connaître

`Quantite` et `Statut` sont des `int`/`string` normaux (pas des pointeurs), donc TOUJOURS présents dans la struct — mais ce ne sont pas des champs "obligatoires à fournir" au sens strict : si le client ne les envoie pas dans le JSON, ils prennent simplement leur valeur zéro (`0` pour `Quantite`, `""` pour `Statut`). C'est le handler (`CreerProduit`, voir `app/produits.go.md`) qui se charge de leur donner une valeur par défaut sensée (`1` et `"en_stock"`) si le client ne les a pas précisés — la struct elle-même ne fait pas cette vérification.
