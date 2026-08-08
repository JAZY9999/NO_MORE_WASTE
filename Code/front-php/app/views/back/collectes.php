<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Liste des collectes.
 *
 * Variables attendues : $collectes, $commercants (index id => raison sociale)
 */

$collectes = $collectes ?? [];
$commercants = $commercants ?? [];

$couleurs = [
    'demandee' => 'secondary',
    'planifiee' => 'info',
    'realisee' => 'success',
    'annulee' => 'danger',
];

?>

<?php if (empty($collectes)): ?>

    <div class="bg-body border rounded-3 p-5 text-center">
        <i class="bi bi-basket3 fs-1 text-body-tertiary"></i>
        <div class="fw-medium mt-3"><?= Langue::t('collectes.aucune') ?></div>
        <p class="text-body-secondary mb-0" style="font-size:.88rem">
            <?= Langue::t('collectes.aucune_detail') ?>
        </p>
    </div>

<?php else: ?>

    <div class="bg-body border rounded-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="border-bottom">
                    <tr>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('collectes.source') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('collectes.date_prevue') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('collectes.date_realisee') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></span></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($collectes as $c): ?>
                    <?php
                    $statut = $c['statut'] ?? 'demandee';
                    $couleur = $couleurs[$statut] ?? 'secondary';

                    // Une collecte concerne SOIT un commercant, SOIT un
                    // particulier. On affiche celui qui est renseigne, avec
                    // une icone differente pour distinguer les deux d'un coup
                    // d'oeil.
                    $estCommercant = !empty($c['commercant_id']);
                    if ($estCommercant) {
                        $source = $commercants[$c['commercant_id']] ?? ('#' . $c['commercant_id']);
                        $icone = 'bi-shop';
                        $type = Langue::t('collectes.type_commercant');
                    } else {
                        $source = $c['particulier_nom'] ?? '';
                        $icone = 'bi-person';
                        $type = Langue::t('collectes.type_particulier');
                    }

                    $prevue = !empty($c['date_prevue']) ? date('d/m/Y', strtotime($c['date_prevue'])) : '';
                    $realisee = !empty($c['date_realisee']) ? date('d/m/Y', strtotime($c['date_realisee'])) : '';
                    ?>
                    <tr>
                        <td>
                            <a href="/back/collectes/<?= (int) $c['id'] ?>"
                               class="text-decoration-none text-body lh-sm d-block">
                                <span class="fw-medium d-block" style="font-size:.86rem">
                                    <i class="bi <?= $icone ?> text-body-tertiary"></i>
                                    <?= Vue::e($source) ?>
                                </span>
                                <small class="text-body-tertiary" style="font-size:.74rem"><?= Vue::e($type) ?></small>
                            </a>
                        </td>
                        <td style="font-size:.82rem"><?= Vue::e($prevue) ?></td>
                        <td style="font-size:.82rem">
                            <?= $realisee !== '' ? Vue::e($realisee) : '<span class="text-body-tertiary">&mdash;</span>' ?>
                        </td>
                        <td>
                            <span class="badge rounded-pill text-bg-<?= $couleur ?>" style="font-size:.66rem">
                                <?= Langue::t('collectes.statut_' . $statut) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="/back/collectes/<?= (int) $c['id'] ?>" class="btn btn-sm btn-light">
                                <?= Langue::t('commun.ouvrir') ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<div class="d-flex gap-2 align-items-start border rounded bg-body-secondary px-3 py-2 mt-3"
     style="font-size:.82rem">
    <i class="bi bi-info-circle text-body-secondary"></i>
    <div><?= Langue::t('collectes.regle_source') ?></div>
</div>
