<?php

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Vue;

/**
 * En-tete du front-office : barre horizontale claire.
 *
 * Volontairement different de la barre laterale du back-office. Les deux
 * espaces ne servent pas au meme usage : le front est une vitrine consultee
 * quelques minutes (on veut du confort de lecture), le back un outil utilise
 * toute la journee (on veut de la densite). La difference visuelle indique
 * aussi immediatement ou l'on se trouve pendant une demonstration.
 *
 * Variables attendues : $config, $chemin (chemin de la vue affichee)
 */

$langueActuelle = Langue::actuelle();
$urlEspace = Auth::urlEspace($config);

/**
 * Souligne le lien de la page courante.
 *
 * On compare au chemin de la VUE et non a $_SERVER['REQUEST_URI'] : l'adresse
 * peut contenir des parametres (?lang=it, ?ville=Paris) qui feraient echouer
 * une comparaison de chaines, alors que le chemin de vue est stable.
 */
$lien = function (string $vue, string $url, string $cleLibelle) use ($chemin): string {
    $actif = ($chemin === $vue) ? 'text-body fw-semibold' : 'text-body-secondary';

    return '<a href="' . Vue::e($url) . '" class="text-decoration-none ' . $actif . '"'
        . ' style="font-size:.92rem">' . Langue::t($cleLibelle) . '</a>';
};

?>
<header class="border-bottom bg-body sticky-top">
    <div class="container d-flex align-items-center justify-content-between py-3 flex-wrap gap-3">

        <a href="/" class="text-decoration-none text-body d-flex align-items-center gap-2">
            <span class="d-flex align-items-center justify-content-center rounded-circle bg-primary text-white"
                  style="width:36px; height:36px">
                <i class="bi bi-recycle fs-5"></i>
            </span>
            <span class="fw-semibold" style="letter-spacing:.04em"><?= Langue::t('app.nom') ?></span>
        </a>

        <nav class="d-flex align-items-center gap-4 flex-wrap">

            <?= $lien('front/accueil', '/', 'nav.accueil') ?>
            <?= $lien('front/services', '/services', 'nav.services') ?>
            <?= $lien('front/candidature', '/benevoles/candidature', 'nav.devenir_benevole') ?>

            <!-- Selecteur de langue : chaque lien renvoie sur la MEME page avec
                 ?lang=xx, ce qui permet de changer de langue sans quitter la
                 page ou l'on se trouve. -->
            <div class="dropdown">
                <button class="btn btn-sm btn-link text-body-secondary text-decoration-none dropdown-toggle p-0"
                        data-bs-toggle="dropdown" style="font-size:.92rem">
                    <?= strtoupper($langueActuelle) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach (Langue::disponibles() as $code => $libelle): ?>
                        <li>
                            <a class="dropdown-item <?= $code === $langueActuelle ? 'active' : '' ?>"
                               href="?lang=<?= Vue::e($code) ?>"><?= Vue::e($libelle) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($urlEspace !== null): ?>
                <!-- L'adresse depend du role, mais c'est Auth::urlEspace qui le
                     sait : le gabarit n'a pas a connaitre les roles. -->
                <a href="<?= Vue::e($urlEspace) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <?= Langue::t('nav.mon_espace') ?>
                </a>
                <a href="/deconnexion" class="text-decoration-none text-body-secondary"
                   style="font-size:.92rem"><?= Langue::t('nav.deconnexion') ?></a>
            <?php else: ?>
                <a href="/connexion" class="btn btn-sm btn-dark rounded-pill px-3">
                    <?= Langue::t('nav.connexion') ?>
                </a>
            <?php endif; ?>

        </nav>
    </div>
</header>
