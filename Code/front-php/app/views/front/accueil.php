<?php

use App\Middleware\Langue;

/** Page d'accueil du front-office (visible par tout le monde, sans connexion). */

?>

<!-- p-5 = marge interieure large, rounded-3 = coins arrondis -->
<div class="p-5 mb-4 bg-body-secondary rounded-3 text-center">
    <h1 class="display-5 fw-bold">
        <i class="bi bi-recycle text-success"></i>
        <?= Langue::t('app.nom') ?>
    </h1>
    <p class="lead text-body-secondary mb-0"><?= Langue::t('app.slogan') ?></p>
</div>

<!-- row-cols-md-2 : deux colonnes sur ecran moyen, une seule sur telephone.
     g-3 = espacement entre les colonnes. -->
<div class="row row-cols-1 row-cols-md-2 g-3">

    <div class="col">
        <a href="/services" class="card h-100 text-decoration-none text-body shadow-sm">
            <div class="card-body">
                <h2 class="h5 card-title">
                    <i class="bi bi-calendar-event text-success"></i>
                    <?= Langue::t('nav.services') ?>
                </h2>
            </div>
        </a>
    </div>

    <div class="col">
        <a href="/benevoles/candidature" class="card h-100 text-decoration-none text-body shadow-sm">
            <div class="card-body">
                <h2 class="h5 card-title">
                    <i class="bi bi-person-plus text-success"></i>
                    <?= Langue::t('nav.devenir_benevole') ?>
                </h2>
            </div>
        </a>
    </div>

</div>
