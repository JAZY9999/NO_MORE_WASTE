# `layout_front.php` — le gabarit du site public

> Choisi **automatiquement** par `Vue::afficher()` quand le chemin de la vue commence par `front/`. Son pendant est `layout_back.php`.

## Le choix du gabarit, en une ligne

```php
$gabarit = str_starts_with($chemin, 'back/') ? 'layout_back' : 'layout_front';
```

Aucun paramètre à passer, donc **impossible de l'oublier**. Un paramètre explicite aurait eu un mode de panne silencieux : un contrôleur back qui oublie de le préciser rendrait sa page dans le mauvais habillage, sans la moindre erreur PHP. Exactement le bug qu'on ne découvre que devant le jury.

La convention `back/` / `front/` existait déjà trois fois dans le projet — dossiers de vues, fichiers de routes, préfixe d'URL. On s'appuie dessus plutôt que d'en inventer une quatrième.

## Le parti pris : aéré

Beaucoup d'espace (`padding-top:3rem`), typographie large.

**Ce gabarit ne dessine aucun titre.** Chaque page publique compose le sien : une page d'accueil a un titre de héros, un formulaire de connexion a un titre de carte. Il n'y a rien de commun à factoriser — contrairement au back-office, où les vingt écrans ont tous la même forme de bandeau.

## Le pied de page collé en bas

```html
<body class="d-flex flex-column min-vh-100">
    <main class="flex-grow-1">
```

`min-vh-100` : le corps fait au moins la hauteur de l'écran. `flex-grow-1` sur le contenu : il occupe tout ce qui reste. Résultat, le pied de page tombe en bas **même sur une page courte** — sans quoi il flotterait au milieu de l'écran.

## Le JavaScript en fin de page

```html
<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
```

Nécessaire au menu déroulant des langues et au bouton de fermeture des alertes. Placé en fin de page pour que le contenu s'affiche sans attendre son chargement.

C'est le **seul** JavaScript du projet, et il vient de Bootstrap : aucune ligne n'a été écrite à la main.

## Fichiers liés

- [../Vue.php.md](../Vue.php.md) — le choix du gabarit et le rôle de `$options`
- [layout_back.php.md](layout_back.php.md) — l'autre gabarit
- [blocs/entete_front.php.md](blocs/entete_front.php.md) et [blocs/messages.php.md](blocs/messages.php.md) — ce qu'il inclut
