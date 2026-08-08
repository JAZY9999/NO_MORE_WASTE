<?php

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Vue;

/** Accueil du back-office, reserve au personnel de l'association. */

/*
 * Les modules du back-office.
 *
 * Ceux dont le lien vaut null ne sont pas encore developpes : ils s'affichent
 * grises et non cliquables. On voit ainsi la structure complete prevue, et
 * surtout on ne tombe pas sur une page en erreur pendant une demonstration.
 */
$modules = [
    ['cle' => 'back.commercants',  'icone' => 'bi-shop',           'lien' => '/back/commercants'],
    ['cle' => 'back.benevoles',    'icone' => 'bi-people',         'lien' => '/back/benevoles'],
    ['cle' => 'back.collectes',    'icone' => 'bi-basket',         'lien' => '/back/collectes'],
    ['cle' => 'back.stocks',       'icone' => 'bi-box-seam',       'lien' => '/back/stocks'],
    ['cle' => 'back.tournees',     'icone' => 'bi-truck',          'lien' => '/back/tournees'],
    ['cle' => 'back.services',     'icone' => 'bi-calendar-event', 'lien' => null],
    ['cle' => 'back.traductions',  'icone' => 'bi-translate',      'lien' => '/back/traductions'],
];

?>


<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
    <?php foreach ($modules as $module): ?>
        <?php $actif = $module['lien'] !== null; ?>
        <div class="col">
            <?php if ($actif): ?>
                <a href="<?= Vue::e($module['lien']) ?>"
                   class="card h-100 text-decoration-none text-body shadow-sm">
            <?php else: ?>
                <!-- opacity-50 grise la carte, pe-none desactive le clic -->
                <div class="card h-100 opacity-50 pe-none">
            <?php endif; ?>

                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi <?= Vue::e($module['icone']) ?> fs-2 text-secondary"></i>
                    <div>
                        <h2 class="h6 mb-0"><?= Langue::t($module['cle']) ?></h2>
                        <?php if (!$actif): ?>
                            <small class="text-body-secondary">
                                <i class="bi bi-hourglass-split"></i> <?= Langue::t('commun.chargement') ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

            <?= $actif ? '</a>' : '</div>' ?>
        </div>
    <?php endforeach; ?>
</div>
