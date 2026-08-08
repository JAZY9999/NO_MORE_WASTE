# `services.php` — le catalogue public

> Vue rendue par `ServicesPublicsController::liste()`. Consultable **sans connexion**.

## Une liste, pas une grille de cartes

Les descriptions de services ont des longueurs très différentes. Une grille obligerait soit à tronquer les textes, soit à laisser des trous entre les cartes courtes et les longues.

Une liste supporte n'importe quelle longueur sans rien casser — et c'est ce qui se lit le mieux sur téléphone.

## Le filtre n'apparaît que s'il sert

```php
<?php if (count($typesPresents) > 1): ?>
```

Proposer de filtrer une liste homogène n'aide personne. Et seules les catégories **réellement présentes** sont proposées : une pastille « Gardiennage » qui ne renvoie jamais rien donne l'impression d'un site cassé.

## Le nombre de créneaux décide du clic

C'est l'information de droite. Un service sans créneau à venir n'a rien à proposer aujourd'hui, et on l'écrit (« aucun créneau ») plutôt que d'afficher un « 0 » sec.

## Toute la ligne est cliquable

```html
<a href="/services/…" class="d-flex justify-content-between …">
```

Le lien enveloppe la ligne entière, pas seulement le titre. Une cible large est plus facile à atteindre, surtout au doigt sur téléphone.

➡️ **Explication complète : [../../controllers/front/ServicesPublicsController.php.md](../../controllers/front/ServicesPublicsController.php.md)**
