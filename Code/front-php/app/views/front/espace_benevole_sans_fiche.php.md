# `espace_benevole_sans_fiche.php` — compte sans candidature

> Vue rendue par `EspaceBenevoleController::index()` quand l'API répond 404.

Le scénario réel : quelqu'un a créé un compte mais n'a jamais déposé de candidature — ou l'a déposée en **anonyme**, avant de créer son compte.

L'écran l'oriente vers le formulaire de candidature, plutôt que de le laisser devant une page vide.

C'est aussi pour réduire ce cas que la candidature rattache automatiquement la fiche au compte quand le visiteur est déjà connecté — et que le formulaire invite explicitement à se connecter d'abord.

## Un bouton, pas seulement une explication

```html
<a href="/benevoles/candidature" class="btn btn-primary">
```

L'écran ne se contente pas de dire ce qui manque : il donne le lien qui le règle. **Un écran vide doit dire quoi faire ensuite**, pas seulement qu'il est vide.

➡️ **Explication complète : [../../controllers/front/EspaceBenevoleController.php.md](../../controllers/front/EspaceBenevoleController.php.md)**
