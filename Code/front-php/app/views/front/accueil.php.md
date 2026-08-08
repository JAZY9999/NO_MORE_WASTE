# `accueil.php` — la page d'accueil publique

> Vue rendue par `AccueilController::index()`. Visible sans connexion.

Un bandeau de présentation, puis des cartes vers les services et la candidature bénévole.

## Ce qu'il faut savoir lire dans les classes Bootstrap

| Classe | Effet |
|---|---|
| `p-5` | marge intérieure large |
| `rounded-3` | coins arrondis |
| `display-5` | très gros titre, réservé aux bandeaux d'accroche |
| `row-cols-1 row-cols-md-2` | **une** colonne sur téléphone, **deux** à partir d'un écran moyen |
| `g-3` | espacement entre les colonnes |
| `h-100` | toutes les cartes d'une rangée ont la même hauteur |

`row-cols-1 row-cols-md-2` est ce qui rend la page utilisable sur téléphone **sans une ligne de CSS écrite à la main**. Le `md` veut dire « à partir de 768 px de large ».

`h-100` mérite un mot : sans lui, deux cartes côte à côte dont les textes n'ont pas la même longueur auraient des hauteurs différentes, et la rangée paraîtrait bancale.

## Tout le texte passe par `Langue::t()`

Aucune phrase n'est écrite en dur, pas même le slogan. C'est ce qui permet à la page de basculer en anglais, italien ou portugais.

Un texte oublié se repère facilement : il reste en français quand on change de langue.

## Fichiers liés

- [../../controllers/front/AccueilController.php.md](../../controllers/front/AccueilController.php.md)
- [../../middleware/Langue.php.md](../../middleware/Langue.php.md) — le système de traduction
- [../layout_front.php.md](../layout_front.php.md) — le gabarit
