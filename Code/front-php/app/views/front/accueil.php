<?php

use App\Middleware\Langue;

/**
 * Page d'accueil du front-office (visible par tout le monde, sans connexion).
 *
 * Volontairement SANS AUCUNE IMAGE : le projet n'utilise que Bootstrap et ses
 * icones (bi-*), jamais de photo hebergee ni de CSS ecrit a la main. Le
 * relief visuel vient donc de trois choses seulement : la taille des icones,
 * la hierarchie des titres, et l'espacement -- pas d'image a heberger, pas de
 * droit d'auteur a verifier, rien qui puisse casser si un lien externe tombe.
 *
 * Les icones des 3 etapes reprennent EXACTEMENT celles du back-office
 * (bi-basket3 pour les collectes, bi-boxes pour les stocks, bi-truck pour les
 * tournees) : le meme concept porte la meme icone partout dans le site.
 */

?>

<!-- p-5 = marge interieure large, rounded-3 = coins arrondis -->
<div class="p-5 mb-5 bg-body-secondary rounded-3 text-center">
    <h1 class="display-5 fw-bold">
        <i class="bi bi-recycle text-success"></i>
        <?= Langue::t('app.nom') ?>
    </h1>
    <p class="lead text-body-secondary mb-3"><?= Langue::t('app.slogan') ?></p>
    <p class="mx-auto mb-0" style="max-width:640px">
        <?= Langue::t('accueil.mission') ?>
    </p>
</div>

<!-- ================================================================
     Comment ca marche : le circuit complet, en trois etapes.
     display-4 sur l'icone est ce qui remplace une photo -- un signal
     visuel fort, sans rien a heberger.
     ================================================================ -->
<div class="text-center mb-4">
    <h2 class="h3 fw-bold"><?= Langue::t('accueil.comment_ca_marche') ?></h2>
</div>

<div class="row row-cols-1 row-cols-md-3 g-4 mb-5">

    <div class="col text-center">
        <div class="display-4 text-success mb-3"><i class="bi bi-basket3"></i></div>
        <h3 class="h5 fw-semibold"><?= Langue::t('accueil.etape_1_titre') ?></h3>
        <p class="text-body-secondary mb-0"><?= Langue::t('accueil.etape_1_texte') ?></p>
    </div>

    <div class="col text-center">
        <div class="display-4 text-success mb-3"><i class="bi bi-boxes"></i></div>
        <h3 class="h5 fw-semibold"><?= Langue::t('accueil.etape_2_titre') ?></h3>
        <p class="text-body-secondary mb-0"><?= Langue::t('accueil.etape_2_texte') ?></p>
    </div>

    <div class="col text-center">
        <div class="display-4 text-success mb-3"><i class="bi bi-truck"></i></div>
        <h3 class="h5 fw-semibold"><?= Langue::t('accueil.etape_3_titre') ?></h3>
        <p class="text-body-secondary mb-0"><?= Langue::t('accueil.etape_3_texte') ?></p>
    </div>

</div>

<!-- ================================================================
     Les deux actions principales, desormais avec un vrai texte
     explicatif -- avant, seul le titre etait affiche.
     ================================================================ -->
<div class="row row-cols-1 row-cols-md-2 g-3 mb-5">

    <div class="col">
        <a href="/services" class="card h-100 text-decoration-none text-body shadow-sm">
            <div class="card-body">
                <div class="fs-2 text-success mb-2"><i class="bi bi-calendar-event"></i></div>
                <h2 class="h5 card-title"><?= Langue::t('nav.services') ?></h2>
                <p class="card-text text-body-secondary" style="font-size:.9rem">
                    <?= Langue::t('accueil.carte_services') ?>
                </p>
                <span class="text-success fw-medium" style="font-size:.88rem">
                    <?= Langue::t('accueil.decouvrir') ?> <i class="bi bi-arrow-right"></i>
                </span>
            </div>
        </a>
    </div>

    <div class="col">
        <a href="/benevoles/candidature" class="card h-100 text-decoration-none text-body shadow-sm">
            <div class="card-body">
                <div class="fs-2 text-success mb-2"><i class="bi bi-person-plus"></i></div>
                <h2 class="h5 card-title"><?= Langue::t('nav.devenir_benevole') ?></h2>
                <p class="card-text text-body-secondary" style="font-size:.9rem">
                    <?= Langue::t('accueil.carte_benevole') ?>
                </p>
                <span class="text-success fw-medium" style="font-size:.88rem">
                    <?= Langue::t('accueil.decouvrir') ?> <i class="bi bi-arrow-right"></i>
                </span>
            </div>
        </a>
    </div>

</div>

<!-- ================================================================
     Pourquoi rejoindre l'association : une liste, pas des chiffres
     inventes -- un site de demonstration n'a pas de vraies statistiques
     a afficher, autant ne pas en fabriquer.
     ================================================================ -->
<div class="border-start border-3 border-success ps-4 py-2 mb-5" style="max-width:760px">
    <h2 class="h5 fw-semibold mb-3"><?= Langue::t('accueil.pourquoi_titre') ?></h2>
    <ul class="text-body-secondary mb-0 ps-3">
        <li class="mb-2"><?= Langue::t('accueil.pourquoi_1') ?></li>
        <li class="mb-2"><?= Langue::t('accueil.pourquoi_2') ?></li>
        <li><?= Langue::t('accueil.pourquoi_3') ?></li>
    </ul>
</div>
