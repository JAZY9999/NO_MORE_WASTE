# `espace_commercant_sans_fiche.php` — compte sans boutique

> Vue rendue par `EspaceCommercantController::index()` quand l'API répond 404.

## Pourquoi un écran entier pour un cas d'erreur

L'API répond **404** quand le compte est légitime mais qu'aucune boutique n'y est rattachée. Et 404 est le bon code : ce n'est pas le compte qui est introuvable, c'est la fiche.

Laisser passer ce 404 afficherait « page introuvable ». C'est **faux** — la page existe — et inquiétant : l'utilisateur croirait le site cassé alors que c'est son dossier qui est incomplet.

Cet écran lui dit ce qui manque et à qui s'adresser.

C'est le même raisonnement que le code-barre inconnu sur l'écran des stocks : **un message d'erreur doit être réservé à ce qui est réellement anormal.**

## Quand ce cas se produit

Une boutique créée depuis le back-office **sans** `utilisateur_id`, alors que le commerçant a créé son compte de son côté. Les deux existent, mais rien ne les relie.

Le rattachement ne peut se faire qu'à la création de la boutique : il n'existe pas encore de route `PUT /commercants/{id}`. C'est ce que l'écran « fiche commerçant » de la vague 4 devra combler.

➡️ **Explication complète : [../../controllers/front/EspaceCommercantController.php.md](../../Controllers/Front/EspaceCommercantController.php.md)**
