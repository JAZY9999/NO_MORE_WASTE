# `campagne_detail.php` — la fiche d'une campagne

> Vue rendue par `CampagnesController::detail()`. **C'est l'écran qu'on ouvre avant d'envoyer quoi que ce soit.**

## L'ordre de la page est une protection

1. le contenu de l'email ;
2. la portée — combien recevront réellement ;
3. **la liste exacte des destinataires**, nom par nom ;
4. le bouton d'envoi, tout en bas.

Il faut avoir fait défiler la liste complète pour atteindre le bouton. Ce n'est pas une contrainte gratuite : **un envoi d'emails ne s'annule pas**, et la seule protection possible est de voir qui va le recevoir.

## Deux chiffres, pas un

```php
$sansEmail = $total - $avecEmail;
```

L'API ignore silencieusement les destinataires sans adresse. La fiche affiche donc combien recevront vraiment **et** combien seront ignorés :

> « 3 destinataire(s) n'ont pas d'adresse email et ne recevront rien. »

Sans cette mention, on croirait avoir touché toute sa cible. C'est aussi ce qui donne une raison concrète de compléter les fiches commerçants.

## Le bouton disparaît quand il n'y a personne à toucher

```php
<?php if ($avecEmail > 0): ?>
```

Zéro destinataire avec adresse → **aucun bouton d'envoi**. Cliquer n'aurait rien fait, et on aurait cherché pourquoi.

À la place, l'état vide explique ce qui cloche : *« Vérifiez les critères : une ville sans partenaire ou un statut d'adhésion trop restrictif donnent une liste vide. »*

Même principe que le bouton « Valider » désactivé chez les bénévoles : **rendre l'erreur impossible plutôt que la signaler après coup.**

## `white-space: pre-wrap`

```html
<div style="font-size:.86rem; white-space:pre-wrap"><?= Vue::e($campagne['corps_email'] ?? '') ?></div>
```

Le corps de l'email est du **texte brut**. En HTML, les retours à la ligne sont ignorés par défaut : tout le message tiendrait sur une seule ligne, et on ne relirait pas ce qui sera réellement envoyé.

`pre-wrap` conserve les retours à la ligne **et** coupe les lignes trop longues — contrairement à `pre`, qui provoquerait un défilement horizontal.

Noter que le contenu passe quand même par `Vue::e()` : le message est saisi par un humain, donc suspect comme n'importe quelle donnée.

## La case à cocher n'est pas décorative

```html
<input type="checkbox" name="confirmation" value="oui" required>
```

Le `required` bloque côté navigateur, mais le contrôleur revérifie :

```php
if (($_POST['confirmation'] ?? '') !== 'oui') { … }
```

**Un POST forgé sans la case ne déclenche rien.** Les deux servent, seule la seconde protège.

La bordure rouge du bloc (`border-danger-subtle`) et le bouton `btn-danger` disent la même chose que le texte : cette action est différente des autres.

➡️ **Explication complète : [../../controllers/back/CampagnesController.php.md](../../controllers/back/CampagnesController.php.md)**
