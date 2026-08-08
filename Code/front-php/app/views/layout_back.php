<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Gabarit du BACK-OFFICE : barre laterale sombre + zone de contenu dense.
 *
 * Choisi automatiquement par Vue::afficher quand le chemin de la vue commence
 * par "back/" (voir Vue.php). Son pendant est layout_front.php.
 *
 * Parti pris : DENSE, a l'inverse du front. C'est un outil utilise toute la
 * journee par le personnel : on veut voir un maximum de lignes sans faire
 * defiler, et garder le menu visible en permanence -- quelqu'un qui passe des
 * stocks aux tournees quarante fois par jour n'a pas a remonter en haut de
 * page a chaque fois.
 *
 * Variables attendues :
 *   $titre     : titre de l'onglet du navigateur
 *   $contenu   : le HTML deja produit par la vue
 *   $config    : la configuration
 *   $options   : reglages du bandeau (titre, fil d'Ariane, actions, onglets)
 *   $menu      : la description du menu (config/menu_back.php)
 *   $menuActif : la cle de l'entree a surligner
 */

$compteurs = $options['compteurs'] ?? [];

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
<body>

<div class="d-flex min-vh-100">

    <?php require __DIR__ . '/blocs/menu_back.php'; ?>

    <!-- min-width:0 est indispensable : sans lui, un tableau large empeche
         cette colonne de retrecir et deborde de la page au lieu de defiler
         dans son propre cadre. C'est le piege classique de flexbox. -->
    <div class="flex-grow-1 d-flex flex-column bg-body" style="min-width:0">

        <?php require __DIR__ . '/blocs/entete_back.php'; ?>

        <main class="flex-grow-1 px-4 py-4 bg-body-tertiary">

            <?php require __DIR__ . '/blocs/messages.php'; ?>

            <?= $contenu ?>

        </main>

        <footer class="border-top px-4 py-3 text-body-tertiary bg-body" style="font-size:.76rem">
            <?= Langue::t('app.nom') ?> &mdash; <?= date('Y') ?>
        </footer>
    </div>
</div>

<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>

</body>
</html>
