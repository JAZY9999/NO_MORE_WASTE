# `adhesions.php` — les adhésions et le rappel automatique

> Vue rendue par `AdhesionsController::liste()`.

## L'ordre de la page n'est pas neutre

Le **job automatique est en tête**, avant les chiffres et avant la liste.

C'est le point le plus cité du sujet, et il tournait jusqu'ici sans que rien ne le montre. Le placer en bas de page reviendrait à cacher ce qu'on cherche justement à démontrer.

## Les délais sont interpolés, pas écrits en dur dans le texte

```php
<?= sprintf(
    Langue::t('adhesions.job_delais'),
    (int) $delais['j30'], (int) $delais['j7'], (int) $delais['ex_abonne']
) ?>
```

La clé de traduction contient `%d` aux trois endroits, dans les quatre langues :

> « Relance à J-**%d**, J-**%d**, et les anciens adhérents après **%d** jours. »

Écrire « J-30 » directement dans la traduction obligerait à modifier quatre fichiers le jour où le délai change — et on en oublierait un. Là, la phrase suit la constante.

## L'aveu affiché à l'écran

```php
<?= Langue::t('adhesions.delais_en_dur') ?>
```

Un encadré dit que ces délais sont écrits dans le code et qu'en changer un demande un redéploiement.

C'est un défaut du projet, et il est **assumé à voix haute** plutôt que caché. Un jury qui le découvre seul y verra un oubli ; annoncé, c'est une limite connue avec sa piste de correction.

## Les compteurs sont une boucle

```php
$cartes = [
    ['cle' => 'actives',      'libelle' => '…', 'couleur' => ''],
    ['cle' => 'a_renouveler', 'libelle' => '…', 'couleur' => 'text-warning-emphasis'],
    ['cle' => 'expirees',     'libelle' => '…', 'couleur' => 'text-danger-emphasis'],
];
```

Un tableau plutôt que trois blocs recopiés : ajouter un compteur demande une ligne, pas dix.

La couleur ne s'applique que si la valeur est positive — « 0 expirée » en rouge attirerait l'œil sur une bonne nouvelle.

## L'urgence se lit à la couleur

```php
if ($jours < 0)                          { $urgence = 'text-danger-emphasis'; }
elseif ($jours <= (int) $delais['j30'])  { $urgence = 'text-warning-emphasis'; }
else                                     { $urgence = 'text-body-tertiary'; }
```

Rouge : l'échéance est passée. Orange : le premier rappel est déjà parti. Gris : rien à faire.

Le seuil orange est **le même** que celui du job — l'écran et les emails s'alignent.

## « dans » ou « depuis »

```php
<?php if ($jours < 0): ?>
    <?= Langue::t('adhesions.depuis') ?> <?= abs($jours) ?>
<?php else: ?>
    <?= Langue::t('adhesions.dans') ?> <?= $jours ?>
```

`abs()` : afficher « dans -12 jours » serait illisible. Deux mots différents, deux situations différentes.

➡️ **Explication complète : [../../controllers/back/AdhesionsController.php.md](../../controllers/back/AdhesionsController.php.md)**
