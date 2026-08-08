# `utilisateurs.php` — les comptes et leurs rôles

> Vue rendue par `UtilisateursController::liste()`. **Réservée à `admin_back`.**

## La couleur dit le niveau de pouvoir

```php
$couleurs = [
    'admin_back' => 'danger',    // rouge
    'staff_back' => 'primary',   // bleu
    'adherent'   => 'success',   // vert
    'benevole'   => 'info',      // cyan
];
```

Rouge pour les administrateurs, volontairement : c'est le rôle qui peut tout faire, y compris créer d'autres administrateurs. La liste doit permettre de les repérer d'un coup d'œil, et de vérifier qu'ils ne sont pas trop nombreux.

La même couleur habille la pastille d'initiales, ce qui rend le rôle lisible deux fois par ligne.

## Les libellés décrivent ce que le rôle permet

`admin_back` → **Administrateur**. `staff_back` → **Personnel**.

Le nom technique ne dit rien à qui découvre l'application. Il reste dans la valeur envoyée (`<option value="admin_back">`), pas dans ce qu'on lit.

## « vous »

```php
$estMoi = ($email === $moi);
```

Une petite pastille marque le compte connecté. C'est utile avant une action risquée — et ça évite de chercher qui l'on est dans une liste de vingt lignes.

## Les initiales, avec `mb_*`

```php
$initiales = mb_strtoupper(mb_substr($email, 0, 2));
```

`mb_substr` et non `substr` : en UTF-8, un caractère accentué occupe **deux octets**. `substr` couperait « ét… » en plein milieu du `é` et afficherait un losange noir.

Même précaution que dans la barre latérale — c'est le même besoin, résolu pareil.

## L'aveu affiché

```php
<?= Langue::t('utilisateurs.limites') ?>
```

> « Changer un rôle, réinitialiser un mot de passe ou désactiver un compte demanderait des routes que l'API n'expose pas encore. »

La maquette montrait ces trois actions dans un menu. Les coder comme des boutons morts aurait été **pire** que de ne rien afficher : on cherche, on clique, rien ne se passe, on croit à une panne.

## Le rôle présélectionné est le moins puissant

```php
<option value="<?= Vue::e($r) ?>" <?= $r === 'adherent' ? 'selected' : '' ?>>
```

La liste va du plus puissant au moins puissant, mais c'est `adherent` qui est sélectionné. Un clic distrait sur « Créer » ne fabrique pas un administrateur.

`minlength="8"` sur le mot de passe double la vérification serveur — le navigateur bloque avant l'envoi, le contrôleur bloque quand même.

➡️ **Explication complète : [../../controllers/back/UtilisateursController.php.md](../../controllers/back/UtilisateursController.php.md)**
