# `blocs/entete_front.php` — la barre du site public

> Inclus par `layout_front.php`.

Une barre **horizontale claire**, volontairement différente de la barre latérale sombre du back-office.

## Pourquoi deux habillages différents

| | Front-office | Back-office |
|---|---|---|
| Usage | vitrine, consultée quelques minutes | outil, utilisé toute la journée |
| Priorité | confort de lecture | densité d'information |

La différence visuelle a un second bénéfice, pratique celui-là : pendant une démonstration, on voit **immédiatement** de quel côté du site on se trouve.

## Souligner la page courante

```php
$actif = ($chemin === $vue) ? 'text-body fw-semibold' : 'text-body-secondary';
```

On compare au **chemin de la vue** (`front/accueil`), pas à `$_SERVER['REQUEST_URI']`.

Pourquoi : l'adresse réelle peut contenir des paramètres — `?lang=it`, `?ville=Paris` — qui feraient échouer une comparaison de chaînes. Le chemin de vue, lui, ne change pas.

## Le sélecteur de langue

Chaque lien renvoie sur la **même page** avec `?lang=xx`. On change de langue sans quitter la page où l'on est — sinon, changer de langue depuis une fiche renverrait à l'accueil, ce qui est très désagréable.

## Le lien « Mon espace »

```php
$urlEspace = Auth::urlEspace($config);
```

L'adresse dépend du rôle (`/back`, `/mon-espace/commercant`, `/mon-espace/benevole`). C'est `Auth` qui le sait : **le gabarit n'a pas à connaître les rôles**. S'il les connaissait, ajouter un rôle demanderait de modifier l'affichage.

Quand personne n'est connecté, `urlEspace()` renvoie `null` et on affiche « Connexion » à la place.

## Fichiers liés

- [../layout_front.php.md](../layout_front.php.md) — le gabarit qui l'inclut
- [../../middleware/Auth.php.md](../../middleware/Auth.php.md) — `urlEspace()`
- [../../middleware/Langue.php.md](../../middleware/Langue.php.md) — `actuelle()` et `disponibles()`
