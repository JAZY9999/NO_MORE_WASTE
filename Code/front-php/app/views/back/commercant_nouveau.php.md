# `commercant_nouveau.php` — enregistrer un partenaire

> Vue rendue par `CommercantsController::formulaireCreation()`.

## Un seul champ obligatoire

La raison sociale. C'est aussi le seul que la base exige (`NOT NULL`).

Un partenaire s'enregistre souvent **au téléphone**, avec trois informations. Rendre le SIRET ou l'adresse obligatoires bloquerait l'enregistrement au moment précis où il faut aller vite — et tout se complète ensuite depuis la fiche.

## Le rattachement de compte peut attendre

```php
<option value="0"><?= Langue::t('commercants.aucun_compte') ?></option>
```

Deux situations réelles :

- le commerçant s'est déjà inscrit en ligne → on le rattache tout de suite ;
- il n'a pas encore de compte → on laisse « aucun compte », et on rattachera plus tard depuis la fiche.

La deuxième situation était **sans issue** avant l'ajout de `PUT /commercants/{id}` : la boutique restait orpheline pour toujours. C'est pour ça que le libellé d'aide précise « il peut aussi être rattaché plus tard ».

## La saisie survit à une erreur

```php
$val = function (string $champ) use ($saisie): string {
    return Vue::e($saisie[$champ] ?? '');
};
```

Neuf champs. Sans ce mécanisme, une raison sociale oubliée obligerait à retaper les huit autres.

Le contrôleur range `$_POST` en session avant de rediriger, puis l'efface après lecture — sinon le formulaire se préremplirait indéfiniment, y compris pour un nouveau partenaire.

## L'aide sous le champ email

> « L'adresse email sert aux rappels de renouvellement : sans elle, aucune relance n'est possible. »

Ce n'est pas une politesse. L'API **refuse** la relance (400) quand l'email manque, et l'écran des adhésions cache alors le bouton. Le dire ici évite de découvrir le problème un an plus tard, au moment du renouvellement.

➡️ **Explication complète : [../../controllers/back/CommercantsController.php.md](../../Controllers/Back/CommercantsController.php.md)**
