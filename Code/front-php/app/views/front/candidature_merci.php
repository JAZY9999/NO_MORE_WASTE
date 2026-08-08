<?php

use App\Middleware\Langue;

/**
 * Confirmation apres l'envoi d'une candidature.
 *
 * Elle ne se contente pas de dire "merci" : elle explique LA SUITE. Un
 * candidat qui ignore ce qui l'attend relance l'association au bout de trois
 * jours, ou se decourage. Les trois etapes reprennent exactement le parcours
 * du back-office : candidat -> justificatifs verifies -> valide.
 */

?>

<div class="py-5" style="max-width:560px">

    <div class="mb-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem"></i>
    </div>

    <h1 class="display-6 fw-bold mb-3"><?= Langue::t('candidature.merci_titre') ?></h1>
    <p class="fs-5 text-body-secondary"><?= Langue::t('candidature.merci_accroche') ?></p>

    <div class="text-uppercase text-body-tertiary fw-semibold mt-5 mb-3"
         style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('candidature.et_ensuite') ?></div>

    <div class="border-top">
        <?php
        // Les trois etapes, dans l'ordre du parcours reel.
        $etapes = ['etape_1', 'etape_2', 'etape_3'];
        foreach ($etapes as $rang => $cle):
        ?>
            <div class="d-flex gap-3 py-3 border-bottom">
                <span class="d-flex align-items-center justify-content-center rounded-circle
                             bg-body-secondary flex-shrink-0 fw-semibold"
                      style="width:26px; height:26px; font-size:.78rem"><?= $rang + 1 ?></span>
                <div class="text-body-secondary" style="font-size:.94rem">
                    <?= Langue::t('candidature.' . $cle) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <a href="/" class="btn btn-outline-secondary rounded-pill px-4 mt-4">
        <?= Langue::t('commun.retour') ?>
    </a>

</div>
