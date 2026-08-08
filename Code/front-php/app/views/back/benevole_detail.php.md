# `benevole_detail.php` — la fiche d'un bénévole

> Vue rendue par `BenevolesController::detail()`.

L'écran que le sujet détaille le plus. Trois blocs : les **documents** justificatifs, les **compétences** (chauffeur, cuisinier, plombier…), et les actions de validation.

La règle du sujet — *« à condition de valider un certain nombre de conditions »* — est rendue visible ici : bandeau d'explication, barre de progression `2/3`, bouton `disabled` tant que tous les documents ne sont pas validés.

⚠️ Le `disabled` est du confort, pas de la sécurité. C'est l'API qui refuse réellement. On a besoin des deux.

Les cinq formulaires de la page pointent tous vers `POST /back/benevoles/@id` et se distinguent par un champ caché `action`.

➡️ **Explication complète : [../../controllers/back/BenevolesController.php.md](../../controllers/back/BenevolesController.php.md)**
