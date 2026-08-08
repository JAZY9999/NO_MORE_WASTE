<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Le catalogue public des services.
 *
 * Une liste, pas des cartes : les services ont des descriptions de longueurs
 * tres differentes, et une grille de cartes obligerait soit a tronquer, soit
 * a laisser des trous. Une liste supporte n'importe quelle longueur.
 *
 * Variables attendues : $services, $creneaux (id => nombre), $typesPresents,
 * $typeActif
 */

$services = $services ?? [];
$creneaux = $creneaux ?? [];
$typesPresents = $typesPresents ?? [];
$typeActif = $typeActif ?? '';

?>

<div class="mb-4 pb-2">
    <h1 class="display-6 fw-bold mb-3"><?= Langue::t('services_publics.titre') ?></h1>
    <p class="fs-5 text-body-secondary mb-0" style="max-width:600px">
        <?= Langue::t('services_publics.accroche') ?>
    </p>
</div>

<?php if (count($typesPresents) > 1): ?>
    <!-- Le filtre n'apparait que s'il y a plusieurs categories : proposer de
         filtrer une liste homogene n'aide personne. -->
    <div class="d-flex gap-2 mb-2 flex-wrap">
        <a href="/services"
           class="btn btn-sm rounded-pill px-3 <?= $typeActif === '' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <?= Langue::t('commun.tous') ?>
        </a>
        <?php foreach ($typesPresents as $t): ?>
            <a href="/services?type=<?= Vue::e($t) ?>"
               class="btn btn-sm rounded-pill px-3 <?= $typeActif === $t ? 'btn-dark' : 'btn-outline-secondary' ?>">
                <?= Langue::t('services.type_' . $t) ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (empty($services)): ?>

    <div class="py-5 text-body-secondary">
        <?= Langue::t('services_publics.aucun') ?>
    </div>

<?php else: ?>

    <div class="border-top">
        <?php foreach ($services as $s): ?>
            <?php $nombre = (int) ($creneaux[$s['id']] ?? 0); ?>

            <a href="/services/<?= (int) $s['id'] ?>"
               class="d-flex justify-content-between align-items-center gap-4 py-4 border-bottom
                      text-decoration-none text-body flex-wrap">
                <div style="max-width:560px">
                    <h2 class="h5 fw-semibold mb-1"><?= Vue::e($s['nom'] ?? '') ?></h2>
                    <?php if (!empty($s['description'])): ?>
                        <p class="text-body-secondary mb-2" style="font-size:.9rem">
                            <?= Vue::e($s['description']) ?>
                        </p>
                    <?php endif; ?>
                    <small class="text-body-tertiary"><?= Langue::t('services.type_' . ($s['type'] ?? 'autre')) ?></small>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <span class="text-body-secondary" style="font-size:.88rem">
                        <?php if ($nombre > 0): ?>
                            <?= $nombre ?> <?= Langue::t('services_publics.creneaux') ?>
                        <?php else: ?>
                            <span class="text-body-tertiary"><?= Langue::t('services_publics.aucun_creneau') ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="text-primary">&rarr;</span>
                </div>
            </a>

        <?php endforeach; ?>
    </div>

<?php endif; ?>
