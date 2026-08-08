# app/collectes.go — gérer les collectes et y rattacher des produits

> ⏱️ **Lecture : ~10 min** · 484 mots, 31 lignes de code

## C'est quoi ce fichier ?

Six handlers : le CRUD classique des collectes (créer, lister, obtenir, changer le statut), plus deux routes spécifiques pour gérer le lien entre une collecte et les produits qui y ont été récupérés.

## Fonction 1 : CreerCollecte — la règle "commerçant OU particulier"

```go
if c.CommercantId == nil && c.ParticulierNom == nil {
    http.Error(w, "commercant_id ou particulier_nom est obligatoire", http.StatusBadRequest)
    return
}
```

Comme expliqué dans `models/collecte.go.md`, une collecte concerne SOIT un commerçant SOIT un particulier — cette vérification empêche de créer une collecte "orpheline" qui ne serait rattachée à personne. Notez qu'on ne vérifie PAS l'inverse (empêcher de fournir les deux en même temps) — un choix de simplicité pour ce projet, le cas n'étant pas jugé assez risqué pour mériter une vérification supplémentaire.

## Fonctions 2 et 3 : ListerCollectes, ObtenirCollecte

Classiques, sur le même modèle que les autres modules (voir `app/produits.go.md` pour `ListerProduits`, très similaire pour le filtre par statut).

## Fonction 4 : ModifierStatutCollecte

```go
type statutCollecteDto struct {
    Statut     string `json:"statut"`
    BenevoleId *int   `json:"benevole_id"`
}
```

Encore un DTO dédié (voir `app/produits.go.md` pour `deplacementProduitDto`, même principe) : cette route ne peut modifier QUE le statut et le bénévole affecté, jamais les autres informations de la collecte (le nom du particulier, la date prévue, etc.).

## Fonction 5 : AjouterProduitCollecte — le point le plus important de ce fichier

```go
func AjouterProduitCollecte(w http.ResponseWriter, r *http.Request) {
    ...
    collecte, err := db.GetCollecteById(collecteId)
    ...
    if collecte == nil {
        http.Error(w, "Collecte introuvable", http.StatusNotFound)
        return
    }

    var p models.Produit
    err = json.NewDecoder(r.Body).Decode(&p)
    ...
    // memes verifications et valeurs par defaut que CreerProduit (voir app/produits.go.md)
    ...

    p.CollecteId = &collecteId

    id, err := db.CreateProduit(p)
    ...
}
```

### Pourquoi cette route et pas juste "créer un produit puis le rattacher après"
Le choix fait ici correspond au VRAI flux de travail sur le terrain : un bénévole, pendant une collecte, scanne un produit avec une douchette code-barre. Ce produit N'EXISTE PAS ENCORE en base à ce moment-là — il est créé PENDANT la collecte, directement rattaché à elle. C'est pour ça que cette route crée un NOUVEAU produit (comme `CreerProduit`, voir `app/produits.go.md`), plutôt que de simplement modifier le `collecte_id` d'un produit déjà existant.

### `p.CollecteId = &collecteId`
Exactement le même principe de sécurité que dans `CreerAdhesion` (voir `app/commercants.go.md`) : on écrase volontairement le `CollecteId` du produit avec l'id venant de l'URL, pour empêcher un client de rattacher son produit à une AUTRE collecte que celle explicitement visée par la route.

### Ce fichier réutilise des fonctions du module Produits
`db.GetProduitByCodeBarre` et `db.CreateProduit` viennent de `db/produitsRepository.go.md` (Phase 3) — pas de duplication de code, ce module Collectes s'appuie directement sur ce qui existe déjà pour la gestion des produits.

## Fonction 6 : ListerProduitsCollecte

Retourne la liste des produits déjà rattachés à une collecte précise (via `db.ListProduitsParCollecte`, voir `db/collectesRepository.go.md`) — utile pour un futur écran back-office "détail d'une collecte" qui afficherait tout ce qui a été récupéré ce jour-là.

## Piège à connaître

Toutes les routes commençant par `/collectes/{id}/...` (comme `POST /collectes/{id}/produits`) vérifient D'ABORD que la collecte existe (`db.GetCollecteById`) avant de faire quoi que ce soit d'autre — exactement le même principe déjà vu dans `app/commercants.go.md` pour `CreerAdhesion` : mieux vaut répondre `404 Not Found` clairement plutôt que de laisser la contrainte SQL `REFERENCES collectes(id)` échouer plus tard avec une erreur `500` moins parlante.
