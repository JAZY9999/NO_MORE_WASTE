# `AccueilController.php` — la page d'accueil publique

> Le seul contrôleur du projet **sans garde**.

```php
public function index(): void
{
    Vue::afficher('front/accueil', ['config' => $this->config], Langue::t('nav.accueil'));
}
```

Aucun appel à `Auth::` : la page d'accueil est visible par tout le monde, connecté ou non. C'est voulu — le sujet demande un site public présentant l'association et ses services.

## Trois arguments, pas quatre

L'appel ne passe que **trois arguments**. Le quatrième, `$options`, est facultatif : le gabarit front ne dessine pas de bandeau, il n'y a donc rien à lui décrire.

Cette rétrocompatibilité n'est pas un détail. Quand `Vue::afficher()` a reçu son quatrième paramètre pendant le portage, la preuve que le changement était sans risque a été que les cinq appels existants continuaient de fonctionner **sans être modifiés**. Un paramètre facultatif ajouté en dernière position ne casse rien ; ajouté au milieu, il aurait fallu rouvrir tous les appels.

## Fichiers liés

- [../../views/front/accueil.php.md](../../views/front/accueil.php.md) — la vue
- [../../Vue.php.md](../../Vue.php.md) — le quatrième paramètre `$options`
