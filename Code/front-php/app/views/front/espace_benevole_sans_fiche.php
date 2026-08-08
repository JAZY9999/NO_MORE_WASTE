<?php

use App\Middleware\Langue;

/**
 * Ecran affiche quand un compte benevole n'a AUCUNE fiche rattachee.
 *
 * Cas reel : quelqu'un a cree un compte, mais n'a jamais depose de
 * candidature -- ou l'a deposee en anonyme, avant de creer son compte.
 * L'ecran l'oriente vers le formulaire de candidature plutot que de le
 * laisser devant une page vide.
 */

?>

<div class="py-5" style="max-width:560px">
    <h1 class="display-6 fw-bold mb-3"><?= Langue::t('espace.titre_benevole') ?></h1>

    <div class="border-start border-3 border-warning ps-4 py-1">
        <div class="fs-5 fw-semibold mb-2"><?= Langue::t('espace.sans_fiche_benevole') ?></div>
        <p class="text-body-secondary mb-0" style="font-size:.94rem">
            <?= Langue::t('espace.sans_fiche_benevole_detail') ?>
        </p>
    </div>

    <a href="/benevoles/candidature" class="btn btn-primary rounded-pill px-4 mt-4">
        <?= Langue::t('nav.devenir_benevole') ?>
    </a>
</div>
