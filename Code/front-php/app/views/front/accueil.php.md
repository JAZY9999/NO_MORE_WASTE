# `accueil.php` — la page d'accueil publique

> Vue rendue par `AccueilController::index()`. Visible sans connexion.

Un bandeau de présentation, un « comment ça marche » en trois étapes, deux cartes vers les services et la candidature bénévole, et une liste « pourquoi nous rejoindre ».

## Aucune image — et c'est délibéré

Le projet n'utilise que Bootstrap et ses icônes (`bi-*`), jamais de photo hébergée ni de CSS écrit à la main. Le relief visuel de cette page vient donc de trois choses seulement : la **taille** des icônes, la **hiérarchie** des titres, et l'**espacement**.

```php
<div class="display-4 text-success mb-3"><i class="bi bi-basket3"></i></div>
```

`display-4` sur une icône (au lieu d'un simple `fs-2`) est ce qui remplace une photo comme point d'accroche visuel — sans rien à héberger, sans droit d'auteur à vérifier, sans lien externe qui puisse casser.

## Les icônes des trois étapes ne sont pas choisies au hasard

```
bi-basket3  → Collecter     (la même icône que le module Collectes du back-office)
bi-boxes    → Trier/Stocker (la même que le module Stocks)
bi-truck    → Distribuer    (la même que le module Tournées)
```

Un visiteur qui consulte l'accueil puis, plus tard, découvre le back-office (ou l'inverse) retrouve les mêmes symboles pour les mêmes concepts. **Le même concept porte la même icône partout dans le site** — un principe de cohérence simple, qui ne coûte rien de plus qu'un choix attentif au moment d'écrire chaque écran.

## Ce qu'il faut savoir lire dans les classes Bootstrap

| Classe | Effet |
|---|---|
| `p-5` | marge intérieure large |
| `rounded-3` | coins arrondis |
| `display-5` / `display-4` | très gros titre ou icône, réservé aux points d'accroche |
| `row-cols-1 row-cols-md-2` / `row-cols-md-3` | **une** colonne sur téléphone, plusieurs à partir d'un écran moyen |
| `g-3` / `g-4` | espacement entre les colonnes |
| `h-100` | toutes les cartes d'une rangée ont la même hauteur |
| `border-start border-3 border-success` | un bandeau de couleur à gauche, sans image ni fond plein |

`row-cols-1 row-cols-md-3` est ce qui rend la page utilisable sur téléphone **sans une ligne de CSS écrite à la main** : les trois étapes s'empilent verticalement sur petit écran, côte à côte sur un écran large.

## Pas de chiffres inventés

La maquette initiale montrait des statistiques d'impact. Elles ont été volontairement écartées : un site de démonstration académique n'a pas de vrais chiffres à afficher, et en fabriquer donnerait l'impression trompeuse de données réelles. La section « pourquoi nous rejoindre » utilise donc une **liste d'arguments**, jamais un nombre inventé.

C'est le même réflexe que dans l'espace commerçant, où trois chiffres justes ont été préférés à quatre dont un approximatif.

## Tout le texte passe par `Langue::t()`

Aucune phrase n'est écrite en dur, pas même la mission ou les textes des trois étapes. C'est ce qui permet à la page de basculer en anglais, italien ou portugais.

Un texte oublié se repère facilement : il reste en français quand on change de langue.

## Fichiers liés

- [../../Controllers/Front/AccueilController.php.md](../../Controllers/Front/AccueilController.php.md)
- [../../Middleware/Langue.php.md](../../Middleware/Langue.php.md) — le système de traduction
- [../layout_front.php.md](../layout_front.php.md) — le gabarit
- [espace_commercant.php.md](espace_commercant.php.md) — le même principe des « chiffres justes plutôt qu'inventés »
