# `app/views/front/connexion.php` — le formulaire de connexion

> ⏱️ **Lecture : ~5 min** · 444 mots, 16 lignes de code

> **Item 1.3** de la todo : *« page de connexion multilingue »*. C'est la page qui a été demandée explicitement pour préparer la Phase 8.

## Une page, quatre langues

Chaque libellé passe par `Langue::t` :

```php
<h1><?= Langue::t('connexion.titre') ?></h1>
<label for="email"><?= Langue::t('connexion.email') ?></label>
<button type="submit"><?= Langue::t('connexion.valider') ?></button>
```

| Langue | Titre | Bouton |
|---|---|---|
| fr | Connexion | Se connecter |
| en | Sign in | Sign in |
| it | Accedi | Accedi |
| pt | Entrar | Entrar |

Aucune duplication de page.

## Le formulaire

```html
<form method="post" action="/connexion">
```

**POST**, jamais GET : avec GET, le mot de passe apparaîtrait dans l'adresse, donc dans l'historique du navigateur, dans les logs du serveur et à l'écran. Voir [front_routes.php.md](../../routes/front_routes.php.md).

### `type="email"` et `type="password"`

```html
<input type="email" id="email" name="email" required>
<input type="password" id="mot_de_passe" name="mot_de_passe" required>
```

- `type="email"` fait vérifier le format par le navigateur et affiche le clavier adapté sur mobile.
- `type="password"` masque la saisie.
- `required` empêche l'envoi d'un champ vide.

⚠️ **Ces contrôles sont du confort, pas de la sécurité.** Ils s'exécutent dans le navigateur, que n'importe qui peut contourner (outils de développement, `curl`). La vraie validation est côté API, dans `verifierIdentifiants` (`app/auth.go`).

C'est une question de jury classique : *« ton formulaire vérifie l'email, est-ce suffisant ? »* → non, et c'est pour ça que l'API revérifie tout.

### `name="mot_de_passe"` — le nom compte

L'attribut `name` détermine la clé reçue en PHP (`$_POST['mot_de_passe']`), qui est ensuite envoyée à l'API sous ce nom. L'API attend **`mot_de_passe`** depuis l'uniformisation en français de la Phase 10 (elle attendait `password` avant).

### `for` et `id` : l'accessibilité

```html
<label for="email">...</label>
<input type="email" id="email" ...>
```

Le `for` du label doit correspondre à l'`id` du champ. Cliquer sur le libellé place alors le curseur dans le champ, et surtout les lecteurs d'écran annoncent correctement à quoi sert le champ. C'est peu coûteux et ça se remarque quand c'est absent.

## L'email conservé, le mot de passe jamais

```php
$emailSaisi = $emailSaisi ?? '';
...
<input type="email" ... value="<?= Vue::e($emailSaisi) ?>">
```

Après une erreur, l'email est réaffiché — c'est désagréable de tout retaper. Le mot de passe, lui, **n'est jamais réinjecté** : il se retrouverait dans le code source de la page et dans le cache du navigateur.

Le `Vue::e()` est indispensable ici : l'email vient de l'utilisateur, donc il pourrait contenir du HTML destiné à s'échapper de l'attribut `value`.

## Le message d'erreur

Il n'est pas affiché par cette vue : il est déposé en session par le contrôleur, puis affiché par [layout.php](../layout_back.php.md) — comme tous les messages du site, à un seul endroit.

Le texte reste volontairement vague (« Email ou mot de passe incorrect ») pour ne pas révéler quelles adresses possèdent un compte.

## Fichiers liés

- [../../controllers/front/AuthController.php.md](../../Controllers/Front/AuthController.php.md) — ce qui reçoit ce formulaire
- [../../middleware/Langue.php.md](../../Middleware/Langue.php.md) — le multilingue
- [../layout.php.md](../layout_back.php.md) — l'affichage des messages d'erreur
