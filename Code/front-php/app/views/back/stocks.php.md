# `stocks.php` — la recherche et la liste des produits

> Vue rendue par `StocksController::liste()`.

Le sujet demande qu'un produit soit *« retrouvable TRÈS RAPIDEMENT »*. Le champ de recherche par code-barre est donc **en tête**, avant tout le reste.

La vue n'a qu'un seul cas à traiter : elle boucle sur `$produits`. C'est le contrôleur qui normalise la réponse de l'API, laquelle renvoie **un objet** sur une recherche exacte et **une liste** sinon.

Le cas « code-barre inconnu » (`$introuvable`) s'affiche en encadré neutre, pas en bandeau rouge : scanner un code inconnu est normal, ce n'est pas une panne.

➡️ **Explication complète : [../../controllers/back/StocksController.php.md](../../controllers/back/StocksController.php.md)**
