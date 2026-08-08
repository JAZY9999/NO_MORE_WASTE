<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Gabarit du FRONT-OFFICE : la partie publique du site.
 *
 * Choisi automatiquement par Vue::afficher quand le chemin de la vue commence
 * par "front/" (voir Vue.php). Son pendant est layout_back.php.
 *
 * Parti pris : AERE. Beaucoup d'espace, typographie large, pas de titre
 * dessine par le gabarit -- chaque page publique compose son propre titre
 * (une page d'accueil a un titre de heros, un formulaire de connexion a un
 * titre de carte : rien de commun a factoriser).
 *
 * Variables attendues :
 *   $titre   : titre de l'onglet du navigateur
 *   $contenu : le HTML deja produit par la vue
 *   $config  : la configuration
 *   $chemin  : le chemin de la vue (pour souligner le lien courant)
 *   $options : reglages du bandeau (inutilises ici, le front n'en a pas)
 */

?>
<!DOCTYPE html>
<html lang="<?= Vue::e(Langue::actuelle()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Vue::e($titre) ?> | <?= Langue::t('app.nom') ?></title>

    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/icons/bootstrap-icons.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-body">

<?php require __DIR__ . '/blocs/entete_front.php'; ?>

<!-- flex-grow-1 fait occuper tout l'espace disponible au contenu, ce qui colle
     le pied de page en bas meme sur une page courte. -->
<main class="container flex-grow-1" style="padding-top:3rem; padding-bottom:4rem">

    <?php require __DIR__ . '/blocs/messages.php'; ?>

    <?= $contenu ?>

</main>

<footer class="border-top py-4 mt-auto">
    <div class="container d-flex justify-content-between flex-wrap gap-3 text-body-secondary"
         style="font-size:.84rem">
        <div>
            <div class="fw-semibold text-body"><?= Langue::t('app.nom') ?></div>
            <div><?= Langue::t('app.slogan') ?></div>
        </div>
        <div><?= date('Y') ?></div>
    </div>
</footer>

<!-- Le JavaScript de Bootstrap : necessaire au menu deroulant des langues et
     au bouton de fermeture des alertes. En fin de page pour que le contenu
     s'affiche sans attendre son chargement. -->
<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>

</body>
</html>
