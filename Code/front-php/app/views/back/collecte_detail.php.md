# `collecte_detail.php` — le détail d'une collecte

> Vue rendue par `CollectesController::detail()`.

L'écran du **scan**. Le formulaire en tête enregistre un produit ramassé : code-barre, libellé, et facultativement catégorie, DLC, quantité, emplacement.

Une douchette code-barre se comporte comme un clavier : le champ est un `<input>` ordinaire, aucun matériel ni bibliothèque n'est nécessaire.

En dessous, la liste des produits déjà scannés pendant cette collecte, et le menu de changement de statut.

➡️ **Explication complète : [../../controllers/back/CollectesController.php.md](../../Controllers/Back/CollectesController.php.md)**
