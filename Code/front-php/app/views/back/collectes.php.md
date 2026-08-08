# `collectes.php` — la liste des collectes

> Vue rendue par `CollectesController::liste()`.

Un tableau : date prévue, source, statut. La **source** est soit un commerçant, soit un particulier — jamais les deux. La vue lit `$commercants[$c['commercant_id']]`, un index construit en **une seule requête** par le contrôleur, et retombe sur `particulier_nom` sinon.

C'est ce qui évite d'appeler l'API une fois par ligne du tableau.

➡️ **Explication complète : [../../controllers/back/CollectesController.php.md](../../controllers/back/CollectesController.php.md)**
