# models/emplacement.go — où se trouve un produit physiquement

> ⏱️ **Lecture : ~5 min** · 159 mots, 8 lignes de code

## C'est quoi ce fichier ?

Une struct simple : `EmplacementStock`, qui représente un endroit précis dans un entrepôt où on peut ranger des produits.

## Le code

```go
type EmplacementStock struct {
    Id       int     `json:"id"`
    Entrepot string  `json:"entrepot"`
    Zone     *string `json:"zone"`
    Rayon    *string `json:"rayon"`
    Etagere  *string `json:"etagere"`
}
```

Seul `Entrepot` est obligatoire (`string` normal) — les autres champs (`Zone`, `Rayon`, `Etagere`) sont optionnels (pointeurs `*string`, même raisonnement que pour tous les autres champs facultatifs du projet, voir `models/utilisateur.go.md`). L'idée : on peut savoir juste "dans quel entrepôt" est un produit, sans forcément préciser la zone/rayon/étagère exacts si l'organisation du stock n'est pas encore aussi détaillée.

## Pourquoi une hiérarchie simple en texte plutôt qu'une vraie hiérarchie de tables

Le sujet demande que les produits soient "stockés et retrouvables très rapidement" — pas de système d'entreposage complexe avec plusieurs niveaux de tables imbriquées. Quatre champs texte simples (entrepôt > zone > rayon > étagère) suffisent à décrire "où" se trouve un produit de façon lisible, sans sur-ingénierie pour un projet étudiant.
