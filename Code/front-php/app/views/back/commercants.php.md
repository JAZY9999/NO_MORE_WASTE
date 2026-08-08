# `app/views/back/commercants.php` — le tableau des commerçants

> ⏱️ **Lecture : ~5 min** · 476 mots, 19 lignes de code

> La vue affichée par [CommercantsController](../../controllers/back/CommercantsController.php.md) (item 2.4).

## Ce qu'une vue doit faire — et ne pas faire

Une vue **met en forme**, rien d'autre. Pas d'appel à l'API, pas de calcul métier, pas de vérification de droits : tout ça est déjà fait par le contrôleur.

Elle reçoit trois variables :

| Variable | Contenu |
|---|---|
| `$commercants` | la liste (déjà filtrée) |
| `$villeFiltre` | la ville sélectionnée, ou `''` |
| `$villes` | toutes les villes, pour le menu déroulant |

```php
$commercants = $commercants ?? [];
```

Cette ligne de repli en tête de fichier évite une erreur fatale si la vue est appelée sans données. Ça ne devrait pas arriver, mais une page blanche est bien plus pénible à diagnostiquer qu'un tableau vide.

## Le filtre qui s'envoie tout seul

```php
<form method="get" action="/back/commercants" class="filtres">
    <select id="ville" name="ville" onchange="this.form.submit()">
```

`onchange="this.form.submit()"` envoie le formulaire dès qu'on change de ville — pas besoin de bouton « Filtrer ». C'est le seul JavaScript de tout le projet, et il tient sur une ligne.

**Pourquoi `method="get"`** et pas `post` ? Parce que le filtre se retrouve alors dans l'adresse :

```
/back/commercants?ville=Naples
```

Résultat : la page filtrée est **partageable** (on peut envoyer le lien à un collègue), **rechargeable** (F5 ne redemande rien), et **ajoutable aux favoris**. Avec POST, on perdrait tout ça.

La règle générale : **GET pour consulter, POST pour modifier**. Un filtre ne change rien en base, donc GET.

C'est aussi pour ça que le `<select>` conserve sa valeur après envoi :

```php
<option value="<?= Vue::e($ville) ?>" <?= $ville === $villeFiltre ? 'selected' : '' ?>>
```

Sans l'attribut `selected`, le menu reviendrait sur « — » après chaque filtrage alors que la liste, elle, resterait filtrée. L'utilisateur ne comprendrait plus ce qu'il regarde.

## Le cas de la liste vide

```php
<?php if (empty($commercants)): ?>
    <p class="vide"><?= Langue::t('commercants.aucun') ?></p>
<?php else: ?>
    <table>...</table>
<?php endif; ?>
```

Un tableau avec des en-têtes mais aucune ligne donne l'impression que la page est cassée. Un message explicite (« Aucun commerçant enregistré ») indique que tout fonctionne, mais qu'il n'y a rien à montrer.

Ce cas arrive vraiment : au premier démarrage, ou avec un filtre sans résultat.

## L'échappement, sur chaque champ

```php
<td><?= Vue::e($c['raison_sociale'] ?? '') ?></td>
```

Deux protections sur la même ligne :

- **`Vue::e()`** neutralise le HTML contenu dans la donnée — sans quoi un commerçant nommé `<script>...</script>` ferait exécuter ce script par le navigateur de celui qui consulte la page (faille **XSS**, détaillée dans [Vue.php.md](../../Vue.php.md)).
- **`?? ''`** évite un avertissement PHP si le champ est absent de la réponse de l'API.

Aucune donnée venant de l'API n'est affichée sans passer par `Vue::e()`. Sans exception.

## Tous les libellés sont traduits

```php
<th><?= Langue::t('commercants.raison_sociale') ?></th>
```

Y compris les en-têtes de colonnes. Le tableau s'affiche donc entièrement en italien ou en portugais — c'est ce que veut dire « site multilingue » dans le sujet : le back-office aussi, pas seulement la partie publique.

## Fichiers liés

- [../../controllers/back/CommercantsController.php.md](../../controllers/back/CommercantsController.php.md) — d'où viennent les données
- [../../Vue.php.md](../../Vue.php.md) — `Vue::e()` et la protection XSS
- [../layout.php.md](../layout_back.php.md) — le gabarit qui entoure cette vue
