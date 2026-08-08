# `tournee_detail.php` — les arrêts d'une tournée

> Vue rendue par `TourneesController::detail()`.

Les arrêts dans l'ordre de passage. Chaque arrêt a **deux états possibles** :

| État | Ce qui s'affiche |
|---|---|
| pas encore livré | un formulaire de clôture, replié |
| livré | le lien vers le **récapitulatif PDF** exigé par le sujet |

Le formulaire est replié par défaut (`collapse` de Bootstrap) : sur une tournée de dix arrêts, dix formulaires dépliés rendraient la page illisible.

Le lien PDF pointe vers `/back/livraisons/@id/pdf` et **non** vers `/api/...` : le navigateur n'emporterait pas le jeton, qui vit dans la session PHP, et l'API répondrait 401. Le front sert donc le PDF en relais.

Les heures sont affichées telles quelles : l'API renvoie déjà `"HH:MM"`.

➡️ **Explication complète : [../../controllers/back/TourneesController.php.md](../../Controllers/Back/TourneesController.php.md)**
