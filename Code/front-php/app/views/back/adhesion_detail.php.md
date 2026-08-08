# `adhesion_detail.php` — une adhésion et l'historique de ses rappels

> Vue rendue par `AdhesionsController::detail()`.

## L'historique est la preuve

C'est **l'écran qu'on ouvre en démonstration**. Le sujet demande un « rappel automatique de renouvellement » ; dire qu'il existe ne suffit pas.

Le tableau montre, pour chaque rappel parti : **quel type**, **quand**, **à quelle adresse**. C'est la trace exécutable du mécanisme.

Il ne sert pas qu'à l'affichage : côté API, `RappelDejaEnvoye()` consulte cette même table pour ne jamais envoyer deux fois le même type de rappel. **Le tableau est la mémoire du système**, et l'encadré du bas le dit.

## Un type inconnu s'affiche quand même

```php
<?= isset($typesRappel[$type]) ? Langue::t($typesRappel[$type]) : Vue::e($type) ?>
```

Quatre types sont prévus : `j30`, `j7`, `ex_abonne`, `manuel`. Si un cinquième apparaissait un jour côté API, la ligne afficherait sa valeur brute plutôt que **rien**.

Une case vide dans un historique fait douter de tout le tableau ; une valeur technique, elle, se comprend et se corrige.

## Le bouton n'apparaît pas quand il échouerait

```php
<?php if ($aEmail): ?>   … le bouton Relancer
<?php else: ?>           … « Ce commerçant n'a pas d'adresse email »
```

L'API refuse la relance (400) si le commerçant n'a pas d'email. Autant le dire **avant** le clic.

Même principe que le bouton « Valider » désactivé chez les bénévoles : rendre l'erreur impossible plutôt que la signaler après coup.

## L'état vide explique la suite

```php
<?= sprintf(Langue::t('adhesions.aucun_rappel_detail'),
            (int) $delais['j30'], (int) $delais['j7']) ?>
```

> « Le premier partira automatiquement **30** jours avant l'échéance, le second **7** jours avant. »

Un historique vide n'est pas une anomalie : c'est le cas normal d'une adhésion récente. La phrase dit **quand** quelque chose apparaîtra, plutôt que de laisser croire que rien ne fonctionne.

Les seuils sont interpolés, comme sur l'écran de liste — la phrase suit la constante.

➡️ **Explication complète : [../../controllers/back/AdhesionsController.php.md](../../Controllers/Back/AdhesionsController.php.md)**
