# models/tournee.go — bénéficiaires, tournées, étapes, livraisons

> ⏱️ **Lecture : ~8 min** · 500 mots

## C'est quoi ce fichier ?

Six structs qui décrivent tout le circuit de distribution : à qui on livre (`Beneficiaire`), quelle tournée (`Tournee`), quels arrêts (`TourneeEtape`), quelle remise effective (`Livraison`), quels produits remis (`ProduitLivre`), et le récapitulatif complet pour le PDF (`RecapLivraison`).

## Beneficiaire

```go
type Beneficiaire struct {
    Id        int     `json:"id"`
    Type      string  `json:"type"`
    Nom       string  `json:"nom"`
    ...
}
```

`Type` ne peut valoir que `association_caritative` ou `particulier_detresse` — les deux catégories citées textuellement par le sujet ("associations caritatives, particuliers en détresse"). Cette liste fermée est garantie par une contrainte `CHECK` dans `schema.sql`.

## Tournee et TourneeEtape — le modèle en deux niveaux

Une **tournée** est une journée de distribution (une date, un bénévole chauffeur, un statut). Elle contient plusieurs **étapes**, une par bénéficiaire visité, avec un `Ordre` (1, 2, 3...) qui définit l'itinéraire, une heure prévue et une heure réelle.

Cette séparation en deux tables reflète la réalité décrite par le sujet : *"des tournées sont ensuite réalisées pour redistribuer partout où c'est nécessaire"* — une tournée = un camion qui fait plusieurs arrêts.

### `HeurePrevue` / `HeureReelle` : des chaînes `"HH:MM"`, pas des dates

```go
HeurePrevue *string `json:"heure_prevue"`   // "10:30", ou nil
HeureReelle *string `json:"heure_reelle"`
```

Ces colonnes sont des `TIME` en base. Lues directement dans une chaîne Go, `database/sql` les recevait comme des **dates complètes** et les formatait en `"0000-01-01T10:30:00Z"` — une heure de passage affublée d'une année zéro, qui s'affichait `0000-` côté front.

Corrigé à la source : les requêtes du repository (voir `db/tourneesRepository.go.md`) utilisent `to_char(..., 'HH24:MI')` pour renvoyer directement `"10:30"`. Le modèle ne porte aucune trace de ce détail — c'est justement l'intérêt : le format arrive déjà correct, aucun client de l'API n'a besoin de savoir qu'il fallait ignorer onze caractères.

### `LivraisonId` — ajouté en portant l'écran des tournées

```go
// LivraisonId : l'identifiant de la livraison rattachee a cet arret, ou
// nil tant qu'il n'a pas ete cloture.
LivraisonId *int `json:"livraison_id"`
```

Sans ce champ, un client qui liste les étapes d'une tournée savait qu'un arrêt était "livré", mais n'avait **aucun moyen de retrouver sa livraison** — donc aucun moyen de construire le lien vers le récapitulatif PDF, alors que ce PDF est une exigence 🟥 du sujet. Le manque a été découvert en portant l'écran des tournées, pas en lisant le code.

Alimenté par un `LEFT JOIN` côté repository — `LEFT` et non `JOIN` simple, sinon les arrêts pas encore clôturés disparaîtraient de la liste. D'où le pointeur : `nil` pour un arrêt qui n'a pas encore de livraison.

## Livraison

Une livraison correspond à **une étape effectivement réalisée** : on est passé chez le bénéficiaire et on lui a remis des produits. `PdfGenerePath` stocke l'URL du récapitulatif (voir `utils/pdf.go.md` pour l'explication de ce choix).

Distinction importante : une `TourneeEtape` peut exister sans `Livraison` (l'arrêt est planifié mais pas encore fait, ou le bénéficiaire était absent). La `Livraison` n'est créée qu'au moment de la remise réelle.

## RecapLivraison — la struct du PDF

Comme `AdhesionARenouveler` (Phase 2) ou `LignePlanning` (Phase 7), ce n'est **pas** le reflet d'une table SQL : c'est le résultat d'une requête qui joint quatre tables (`livraisons` + `tournee_etapes` + `tournees` + `beneficiaires`) plus une seconde requête pour les produits.

Elle rassemble en un seul objet tout ce qu'il faut imprimer sur le récapitulatif : qui a reçu (nom, type, adresse), quand, dans quelle tournée, et la liste complète des produits avec leurs quantités.
