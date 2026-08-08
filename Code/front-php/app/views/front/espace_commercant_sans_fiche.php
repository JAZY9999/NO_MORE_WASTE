<?php

use App\Middleware\Langue;

/**
 * Ecran affiche quand un compte adherent n'a AUCUNE boutique rattachee.
 *
 * L'API repond 404 dans ce cas -- et 404 est le bon code : le compte est
 * legitime, c'est la fiche commercant qui manque.
 *
 * Un 404 brut afficherait "page introuvable", ce qui est faux et inquietant :
 * l'utilisateur croirait que le site est casse alors que c'est son dossier qui
 * est incomplet. Cet ecran lui dit ce qui manque et quoi faire.
 */

?>

<div class="py-5" style="max-width:560px">
    <h1 class="display-6 fw-bold mb-3"><?= Langue::t('espace.titre_commercant') ?></h1>

    <div class="border-start border-3 border-warning ps-4 py-1">
        <div class="fs-5 fw-semibold mb-2"><?= Langue::t('espace.sans_fiche') ?></div>
        <p class="text-body-secondary mb-0" style="font-size:.94rem">
            <?= Langue::t('espace.sans_fiche_detail') ?>
        </p>
    </div>

    <a href="/" class="btn btn-outline-secondary rounded-pill px-4 mt-4">
        <?= Langue::t('commun.retour') ?>
    </a>
</div>
