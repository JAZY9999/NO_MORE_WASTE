# `services.php` — les services et leurs créneaux

> Vue rendue par `ServicesController::liste()`.

Trois zones, de haut en bas :

1. **Le planning quotidien** — téléchargement du CSV, envoi par e-mail ;
2. **Le tableau des créneaux**, tous services confondus, triés par date ;
3. **Deux formulaires de création** côte à côte : un service, un créneau.

## Une seule colonne fait tout le travail : « Bénévole affecté »

Elle a deux états :

| État | Ce qui s'affiche |
|---|---|
| déjà affecté | le nom du bénévole |
| pas encore | un menu + un bouton **Affecter**, et `requiert : cuisinier` si le service exige une compétence |

Le menu ne contient que des bénévoles **validés** — le contrôleur ne charge que ceux-là. La compétence, elle, est vérifiée par l'API ; l'indication « requiert : … » est là pour que le refus soit compréhensible plutôt que mystérieux.

## Le type de service est un menu, pas un champ libre

```php
<select name="type" required>
    <?php foreach ($types as $t): ?>
        <option value="<?= Vue::e($t) ?>"><?= Langue::t('services.type_' . $t) ?></option>
```

La base n'accepte que **sept** valeurs (contrainte `CHECK`). Un champ libre transformait une faute de frappe en erreur serveur — c'est le défaut trouvé en testant l'écran.

Noter la construction de la clé : `'services.type_' . $t`. Les sept libellés sont traduits comme le reste ; seule la valeur envoyée (`cours_cuisine`) reste technique.

## La division protégée

```php
$taux = $capacite > 0 ? min(100, (int) (100 * $inscrits / $capacite)) : 0;
```

Deux protections dans une ligne :

- `$capacite > 0` : une capacité à zéro ferait planter la page (division par zéro) ;
- `min(100, …)` : la barre ne dépasse jamais sa largeur, même si des inscriptions ont été enregistrées au-delà de la capacité.

La couleur suit la même règle : rouge quand c'est complet, vert sinon.

## Deux formulaires, deux méthodes différentes pour le planning

```html
<form method="get"  action="/back/plannings">  <!-- télécharger le CSV -->
<form method="post" action="/back/plannings">  <!-- envoyer les e-mails -->
```

Ce n'est pas une coquetterie. Un GET est une **lecture** : le rejouer ne coûte rien. Un POST **agit**. Si l'envoi était un GET, rafraîchir la page renverrait tous les e-mails.

## L'état vide dit ce qui manque

```php
<?php if (empty($creneaux)): ?>
```

Plutôt qu'un tableau à zéro ligne, on explique : *« Créez un service, puis ajoutez-lui des créneaux. »* Et le formulaire de créneau, à droite, se remplace lui aussi par *« Créez d'abord un service »* tant qu'aucun n'existe — parce qu'un créneau appartient toujours à un service.

**Un écran vide doit dire quoi faire ensuite**, pas seulement qu'il est vide.

## Fichiers liés

- [../../controllers/back/ServicesController.php.md](../../Controllers/Back/ServicesController.php.md) — l'explication complète du module
- [benevoles.php.md](benevoles.php.md) — d'où vient le statut « validé » exigé pour affecter
- [../blocs/entete_back.php.md](../blocs/entete_back.php.md) — le bandeau
