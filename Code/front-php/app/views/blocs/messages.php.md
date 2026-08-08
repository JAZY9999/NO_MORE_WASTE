# `blocs/messages.php` — les messages à usage unique

> Inclus par **les deux** gabarits, `layout_back.php` et `layout_front.php`.

## À quoi ça sert

Afficher « Enregistré. » ou « Statut invalide. » **une seule fois**, après une action.

## Pourquoi passer par la session

Un message est presque toujours déposé juste avant une redirection :

```php
$_SESSION['message_succes'] = "Enregistré.";
Auth::rediriger('/back/commercants');
```

La redirection fait repartir le navigateur sur une **autre requête**. Une variable PHP ordinaire aurait disparu entre les deux : PHP oublie tout à la fin de chaque requête. La session, elle, survit — c'est précisément son rôle.

## Pourquoi on les efface après lecture

```php
unset($_SESSION['message_erreur'], $_SESSION['message_succes']);
```

Sans cette ligne, le message resterait dans la session et s'afficherait sur **toutes les pages suivantes**, indéfiniment. On lirait « Produit ajouté » en consultant les traductions une heure plus tard.

C'est ce qu'on appelle un *flash message* : lu une fois, puis détruit.

## Pourquoi un fichier séparé

Les deux gabarits en ont besoin. Recopier le bloc obligerait à corriger deux fois le moindre détail d'affichage — et on en oublierait un. C'est exactement le genre de duplication qui produit un back-office et un front-office qui ne se ressemblent plus au bout de trois mois.

## Fichiers liés

- [../layout_back.php.md](../layout_back.php.md) et [../layout_front.php.md](../layout_front.php.md) — les deux gabarits qui l'incluent
- [../../middleware/Auth.php.md](../../middleware/Auth.php.md) — `rediriger()`, qui dépose ces messages
