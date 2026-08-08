# `commercant_detail.php` — la fiche d'un partenaire

> Vue rendue par `CommercantsController::detail()`.

## L'ordre suit les questions

1. **Est-il à jour ?** — l'adhésion. Elle est en premier parce qu'elle conditionne tout le reste : un partenaire dont l'adhésion a expiré ne devrait plus être collecté.
2. **Peut-il se connecter ?** — le compte rattaché.
3. **Comment le joindre ?** — les coordonnées.
4. **Qu'a-t-on fait avec lui ?** — l'historique de collectes.

## L'adhésion affichée est la plus récente

```php
if ($courante === null || ($a['date_fin'] ?? '') > ($courante['date_fin'] ?? '')) {
```

Un partenaire fidèle en accumule plusieurs. Prendre la première du tableau annoncerait une adhésion expirée à quelqu'un parfaitement en règle.

Même règle que dans l'espace client — la même information, vue de l'autre côté.

## L'avertissement quand le compte manque

```php
<?php if ($compteActuel === 0): ?>
    … « Sans compte rattaché, ce commerçant ne peut pas ouvrir
        son espace client ni demander de collecte en ligne. »
```

L'encadré ne dit pas seulement qu'un champ est vide : il dit **ce que ça empêche**. Sans lui, personne ne ferait le lien entre une case non remplie et un commerçant bloqué devant son espace client.

## Trois formulaires, trois actions

Tous pointent vers `POST /back/commercants/@id` et se distinguent par un champ caché `action` : `modifier`, `rattacher`, `creer_adhesion`.

C'est le motif déjà utilisé par la fiche d'un bénévole et l'écran des traductions — un seul point d'entrée plutôt qu'une explosion de routes pour un seul écran.

## Le raccourci d'échappement

```php
$val = function (string $champ) use ($commercant): string {
    return Vue::e($commercant[$champ] ?? '');
};
```

Neuf champs de formulaire à préremplir. Écrire `Vue::e($commercant['x'] ?? '')` neuf fois invite à en oublier un — et un `Vue::e()` oublié est une faille XSS.

La fonction anonyme échappe **et** gère l'absence, en un seul endroit. Le même motif sert dans le formulaire de candidature.

## Les clés de traduction sont partagées

`beneficiaires.adresse`, `beneficiaires.ville`, `candidature.email`… Ces libellés existent déjà et disent exactement la même chose.

En créer des doublons (`commercants.adresse`) multiplierait les traductions à maintenir — quatre langues par clé — sans rien apporter. **Un libellé identique mérite une clé unique**, quel que soit l'écran qui l'affiche.

➡️ **Explication complète : [../../controllers/back/CommercantsController.php.md](../../controllers/back/CommercantsController.php.md)**
