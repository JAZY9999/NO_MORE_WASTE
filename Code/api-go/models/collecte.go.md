# models/collecte.go — une collecte de produits

> ⏱️ **Lecture : ~5 min** · 257 mots, 11 lignes de code

## C'est quoi ce fichier ?

La struct `Collecte`, qui représente une tournée de récupération d'invendus chez un commerçant OU chez un particulier.

## Le code

```go
type Collecte struct {
    Id                 int     `json:"id"`
    CommercantId       *int    `json:"commercant_id"`
    ParticulierNom     *string `json:"particulier_nom"`
    ParticulierAdresse *string `json:"particulier_adresse"`
    BenevoleId         *int    `json:"benevole_id"`
    DatePrevue         *string `json:"date_prevue"`
    DateRealisee       *string `json:"date_realisee"`
    Statut             string  `json:"statut"`
}
```

## Pourquoi CommercantId ET ParticulierNom sont TOUS LES DEUX des pointeurs optionnels

C'est un cas particulier qu'on n'avait pas encore rencontré : une collecte a lieu SOIT chez un commerçant (`CommercantId` rempli, `ParticulierNom` vide), SOIT chez un particulier (l'inverse) — jamais les deux en même temps, mais jamais aucun des deux non plus (il en faut au moins un). Le sujet dit : "récolter tous les jours les invendus commerciaux, **ou les produits atteignant la date limite de consommation chez les particuliers**". Comme la struct Go elle-même ne peut pas exprimer facilement une règle "l'un ou l'autre mais pas aucun des deux", c'est le handler (`CreerCollecte`, voir `app/collectes.go.md`) qui vérifie cette règle avec un simple `if`.

## BenevoleId : le chauffeur qui effectue la collecte

Optionnel pour l'instant : la collecte peut être créée avant qu'un bénévole (Phase 6, pas encore codée) ne soit affecté comme chauffeur. Une fois la Phase 6 codée, ce champ pourra être rempli via `ModifierStatutCollecte` (voir `app/collectes.go.md`).

## DatePrevue et DateRealisee en `*string`

Même choix que pour `Adhesion.DateDebut`/`DateFin` (voir `models/adhesion.go.md`) : des dates transmises comme du texte simple, converties automatiquement par Postgres. Ici, en plus, ce sont des pointeurs (optionnels) car `DatePrevue` peut ne pas être connue à la création, et `DateRealisee` ne sera remplie qu'une fois la collecte effectivement terminée (voir `db.UpdateStatutCollecte` dans `db/collectesRepository.go.md`, qui la remplit automatiquement).
