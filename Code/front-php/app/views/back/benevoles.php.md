# `benevoles.php` — la liste des bénévoles

> Vue rendue par `BenevolesController::liste()`.

Un tableau : nom, ville, statut, compétences. Les onglets du bandeau (Tous / Candidat / Validé / Refusé) sont construits par le contrôleur, pas ici.

**La vue ne calcule rien.** Elle reçoit `$benevoles` et `$competences` déjà prêts et les affiche. Toute la logique — filtre, compteurs, index des compétences — est dans le contrôleur.

Chaque nom est un lien vers la fiche, qui est l'écran réellement important du module.

➡️ **Explication complète : [../../controllers/back/BenevolesController.php.md](../../Controllers/Back/BenevolesController.php.md)**
