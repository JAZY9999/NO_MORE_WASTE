# `candidature.php` — devenir bénévole

> Vue rendue par `CandidatureController::formulaire()`. Page **publique**.

## Deux champs obligatoires seulement

Prénom et nom. C'est volontaire : chaque champ obligatoire supplémentaire fait abandonner des candidats en cours de saisie.

Le sujet dit « **chacun** peut s'inscrire » — la page doit être facile à franchir. L'association demandera le reste au premier contact ; elle ne rattrapera pas quelqu'un qui a fermé l'onglet.

## La saisie est conservée après une erreur

```php
$valeur = function (string $champ) use ($saisie): string {
    return Vue::e($saisie[$champ] ?? '');
};
```

Une petite fonction anonyme plutôt que sept `Vue::e($saisie['x'] ?? '')` répétés. Elle échappe **et** gère l'absence, en un seul endroit.

Sans ce mécanisme, une erreur sur un champ obligerait à retaper les six autres.

## L'encadré pour les visiteurs non connectés

Un visiteur **connecté** voit sa candidature rattachée à son compte, et son espace bénévole fonctionne dès la validation. On le lui signale : c'est la différence entre un espace qui marche tout de suite et une fiche orpheline.

On ne l'*oblige* pas à se connecter pour autant — « chacun peut s'inscrire ».

## La case à cocher

```php
<?= !empty($saisie['permis_conduire']) ? 'checked' : '' ?>
```

Une case non cochée n'est **pas envoyée du tout** par le navigateur : elle est absente, pas vide. D'où le `!empty` pour la réafficher, et le `isset` côté contrôleur pour la lire.

➡️ **Explication complète : [../../controllers/front/CandidatureController.php.md](../../controllers/front/CandidatureController.php.md)**
