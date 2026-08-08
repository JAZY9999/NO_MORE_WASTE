# `beneficiaires.php` — les destinataires des tournées

> Vue rendue par `BeneficiairesController::liste()`.

Liste à gauche (8 colonnes sur 12), formulaire de création à droite (4 colonnes). Sur écran étroit, `col-xl-*` fait passer les deux l'un sous l'autre.

## Pourquoi tout sur une seule page

Un bénéficiaire tient en cinq champs. Un écran de détail séparé obligerait à naviguer pour lire trois lignes, et un formulaire sur une page à part ferait perdre de vue la liste qu'on est en train de compléter.

Même choix que pour les emplacements de stock.

## La couleur distingue les deux natures

```php
$couleurs = [
    'association_caritative' => 'info',
    'particulier_detresse'   => 'warning',
];
```

Une association et un particulier en détresse ne se traitent pas pareil — quantités, fréquence, discrétion. La pastille permet de les distinguer sans lire le libellé.

## La clé de traduction suit la valeur de la base

```php
<?= Langue::t('beneficiaires.type_' . $type) ?>
```

`$type` vaut exactement ce que contient la colonne : `association_caritative` ou `particulier_detresse`.

C'est ce qui a révélé un défaut : la vue des tournées utilisait des clés **abrégées** (`type_association`), et cet écran affichait donc la clé brute. Les deux vues partagent maintenant la même convention.

## Le tiret cadratin plutôt qu'une case vide

```php
<?= $adresse !== '' ? Vue::e($adresse) : '<span class="text-body-tertiary">&mdash;</span>' ?>
```

Une cellule vide laisse penser à un bug d'affichage. Un `—` gris dit « cette information n'a pas été saisie » — c'est une réponse, pas une absence de réponse.

Le bloc contact fait la même chose, mais seulement si **les deux** champs sont vides : afficher un téléphone sans nom reste utile.

## La phrase du bas

> « Chaque arrêt d'une tournée de distribution désigne un bénéficiaire : sans eux, aucune tournée ne peut être planifiée. »

Elle explique **à quoi sert cet écran** dans la chaîne. Sans elle, un utilisateur qui découvre le back-office ne voit qu'un carnet d'adresses de plus.

➡️ **Explication complète : [../../controllers/back/BeneficiairesController.php.md](../../controllers/back/BeneficiairesController.php.md)**
