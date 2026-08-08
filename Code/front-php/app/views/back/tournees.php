<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Liste des tournees de distribution.
 *
 * Variables attendues : $tournees, $benevoles (index id => nom)
 */

$tournees = $tournees ?? [];
$benevoles = $benevoles ?? [];

$couleurs = [
    'planifiee' => 'secondary',
    'en_cours' => 'info',
    'terminee' => 'success',
    'annulee' => 'danger',
];

?>

<?php if (empty($tournees)): ?>

    <div class="bg-body border rounded-3 p-5 text-center">
        <i class="bi bi-truck fs-1 text-body-tertiary"></i>
        <div class="fw-medium mt-3"><?= Langue::t('tournees.aucune') ?></div>
        <p class="text-body-secondary mb-0" style="font-size:.88rem">
            <?= Langue::t('tournees.aucune_detail') ?>
        </p>
    </div>

<?php else: ?>

    <div class="bg-body border rounded-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="border-bottom">
                    <tr>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('tournees.date') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('tournees.chauffeur') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></span></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tournees as $t): ?>
                    <?php
                    $statut = $t['statut'] ?? 'planifiee';
                    $couleur = $couleurs[$statut] ?? 'secondary';
                    $date = !empty($t['date_tournee']) ? date('d/m/Y', strtotime($t['date_tournee'])) : '';
                    $chauffeur = $benevoles[$t['benevole_id'] ?? 0] ?? '';
                    ?>
                    <tr>
                        <td>
                            <a href="/back/tournees/<?= (int) $t['id'] ?>"
                               class="text-decoration-none text-body fw-medium" style="font-size:.86rem">
                                <?= Vue::e($date) ?>
                            </a>
                        </td>
                        <td style="font-size:.82rem">
                            <?= $chauffeur !== '' ? Vue::e($chauffeur) : '<span class="text-body-tertiary">&mdash;</span>' ?>
                        </td>
                        <td>
                            <span class="badge rounded-pill text-bg-<?= $couleur ?>" style="font-size:.66rem">
                                <?= Langue::t('tournees.statut_' . $statut) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="/back/tournees/<?= (int) $t['id'] ?>" class="btn btn-sm btn-light">
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
