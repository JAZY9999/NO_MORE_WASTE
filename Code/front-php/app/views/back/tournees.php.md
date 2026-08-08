# `tournees.php` — la liste des tournées

> Vue rendue par `TourneesController::liste()`.

Un tableau : date, chauffeur, statut. Le nom du chauffeur vient d'un index `$benevoles` construit en une seule requête par le contrôleur — l'API ne renvoie qu'un `benevole_id`.

Quand il n'y a aucune tournée, la vue affiche un état vide expliqué plutôt qu'un tableau à zéro ligne : on dit **ce qui apparaîtra ici** une fois des tournées planifiées.

➡️ **Explication complète : [../../controllers/back/TourneesController.php.md](../../Controllers/Back/TourneesController.php.md)**
