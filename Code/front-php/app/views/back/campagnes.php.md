# `campagnes.php` — les campagnes d'emailing

> Vue rendue par `CampagnesController::liste()`.

Liste à gauche, formulaire de création à droite.

## Créer n'envoie rien — et l'écran le dit

```php
<?= Langue::t('campagnes.creer_n_envoie_pas') ?>
```

> « Créer une campagne n'envoie aucun email. L'envoi se déclenche depuis sa fiche, après avoir vu la liste des destinataires. »

Sans cette phrase, quelqu'un pourrait hésiter à cliquer sur « Créer » de peur d'envoyer cinquante emails par accident — ou, pire, cliquer en croyant que rien ne partira alors que si.

**Une action irréversible mérite qu'on dise clairement où elle se trouve.**

## Les critères résumés en une phrase lisible

```php
$criteres[] = Langue::t('adhesions.statut_' . $c['critere_statut_adhesion']);
```

La colonne « Cible » ne montre pas `expiree` mais « Expirée ». Et quand aucun critère n'est posé, elle affiche « Tous les commerçants » plutôt qu'une cellule vide — une case vide laisserait croire à un bug.

Une campagne se relit alors sans ouvrir sa fiche.

## Le menu des villes plutôt qu'un champ libre

Seules les villes où un partenaire est réellement installé sont proposées. Un champ texte permettrait de taper « Marseile » — la campagne serait créée sans erreur, et n'aurait aucun destinataire.

Le pays, lui, reste un champ libre : la liste des pays présents serait souvent d'un seul élément, et un menu à une entrée n'aide personne.

## L'aide sur la variable

```php
<?= Langue::t('campagnes.variable_aide') ?>
```

> « `{{raison_sociale}}` est remplacé par le nom de chaque commerçant. »

C'est la seule variable que l'API sait substituer. Le dire sous le champ évite deux erreurs : ignorer qu'elle existe, ou en inventer d'autres qui resteraient telles quelles dans l'email envoyé.

➡️ **Explication complète : [../../controllers/back/CampagnesController.php.md](../../Controllers/Back/CampagnesController.php.md)**
