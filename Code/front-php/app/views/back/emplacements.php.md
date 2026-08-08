# `emplacements.php` — où les produits sont rangés

> Vue rendue par `StocksController::emplacements()`.

Trois niveaux : **zone – rayon – étagère**, dans un entrepôt. Seul l'entrepôt est obligatoire — une petite association peut n'avoir qu'une réserve sans zonage.

Sans cet écran, « retrouvable très rapidement » ne serait vrai qu'à moitié : on saurait que le produit existe, sans savoir où aller le chercher.

Le formulaire de création est sur la même page que la liste : on crée un emplacement en passant, sans changer d'écran.

➡️ **Explication complète : [../../controllers/back/StocksController.php.md](../../Controllers/Back/StocksController.php.md)**
