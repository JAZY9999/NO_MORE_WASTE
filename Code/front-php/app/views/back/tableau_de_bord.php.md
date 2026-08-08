# `tableau_de_bord.php` — l'accueil du back-office

> Vue rendue par `TableauDeBordController::index()`.

La première page qu'un membre du staff voit après s'être connecté : des cartes de raccourci vers les modules.

## Une seule source pour le titre

**Cette vue ne dessine pas son titre.** Le bandeau — titre et sous-titre « Bienvenue, … » — est produit par `blocs/entete_back.php` à partir de `$options`.

Au moment du portage, cette vue dessinait encore son propre `<h1>`. Une fois le nouveau gabarit branché, la page affichait donc **deux titres** l'un sous l'autre. Le bloc a été retiré et l'information déplacée dans `$options`.

Deux autres écrans avaient le même défaut (`back/commercants.php`, `back/traductions.php`). Les corriger a servi de **preuve exécutable** que le contrat `$options` tenait avant d'y faire passer les vingt écrans suivants.

## Fichiers liés

- [../../controllers/back/TableauDeBordController.php.md](../../controllers/back/TableauDeBordController.php.md)
- [../blocs/entete_back.php.md](../blocs/entete_back.php.md) — qui dessine le bandeau
