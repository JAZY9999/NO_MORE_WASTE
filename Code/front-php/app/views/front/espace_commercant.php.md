# `espace_commercant.php` — l'espace d'un adhérent

> Vue rendue par `EspaceCommercantController::index()`.

L'ordre de la page suit l'ordre des préoccupations :

1. **Suis-je en règle ?** — l'adhésion, en premier, parce qu'elle conditionne tout le reste : un adhérent dont l'adhésion a expiré ne peut plus être collecté.
2. **Que puis-je faire ?** — demander une collecte.
3. **Qu'ai-je fait ?** — l'historique.

## Le seuil des 30 jours

```php
$bientot = $jours >= 0 && $jours <= 30;
```

C'est exactement le moment où l'association envoie son premier rappel par email. L'écran passe à l'orange au moment précis où le mail part — le site et l'email disent donc la même chose au même moment.

## La barre de progression représente l'année écoulée

```php
$pourcent = max(0, min(100, (int) (100 * (365 - $jours) / 365)));
```

Le double bornage n'est pas décoratif : `$jours` peut être négatif (adhésion expirée) ou supérieur à 365 (adhésion prise en avance). Sans `max`/`min`, la barre sortirait de son cadre.

## Le `min` du champ date n'est pas une sécurité

```html
<input type="date" min="<?= $aujourdhui ?>" required>
```

Il empêche de choisir une date passée dans le calendrier — pour ceux qui passent par le formulaire. Le contrôleur revérifie côté serveur. **Les deux servent, seule la seconde protège.**

## Ce que la vue n'affiche pas

Ni le nom du bénévole affecté, ni le nombre d'articles donnés. Les deux demanderaient des routes réservées au personnel.

Trois chiffres justes valent mieux que quatre dont un inventé — un chiffre approximatif sur un écran client se remarque tout de suite, et discrédite les autres.

➡️ **Explication complète : [../../controllers/front/EspaceCommercantController.php.md](../../Controllers/Front/EspaceCommercantController.php.md)**
