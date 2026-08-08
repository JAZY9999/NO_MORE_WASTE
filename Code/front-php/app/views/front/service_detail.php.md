# `service_detail.php` — un service et ses créneaux

> Vue rendue par `ServicesPublicsController::detail()`. Consultable **sans connexion**.

## Le bouton a quatre états, selon qui regarde

| Situation | Ce qui s'affiche |
|---|---|
| créneau complet | « Complet » |
| adhérent connecté | le bouton **S'inscrire** |
| visiteur anonyme | « Se connecter » |
| connecté mais pas adhérent | « Réservé aux adhérents » |

Le dernier cas compte autant que les autres. Un **bénévole** connecté à qui l'on dirait « Se connecter » serait dérouté : il *est* connecté. Lui dire que l'inscription est réservée aux adhérents est la seule réponse qui ne l'envoie pas tourner en rond.

## Le formulaire n'envoie que le créneau

```html
<input type="hidden" name="creneau_id" value="…">
```

Aucun identifiant de personne. L'API déduit du jeton qui s'inscrit — c'est ce qui empêche un adhérent d'inscrire quelqu'un d'autre à sa place. Avant que cette règle existe côté API, la requête forgée était acceptée.

## Les créneaux affichés sont déjà filtrés

Le contrôleur a écarté les créneaux annulés et ceux déjà passés. Afficher « Cours du 3 mars » en août ferait douter que le site soit à jour.

Les heures ne sont pas découpées : l'API renvoie déjà `"HH:MM"`.

## L'encadré « Bon à savoir »

Trois phrases, à droite. Elles répondent aux questions qu'on se pose avant de cliquer : est-ce payant, y a-t-il de la place, faut-il un compte. Y répondre **avant** le clic évite un aller-retour et une déception.

➡️ **Explication complète : [../../controllers/front/ServicesPublicsController.php.md](../../Controllers/Front/ServicesPublicsController.php.md)**
