<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Liste des benevoles.
 *
 * Variables attendues : $benevoles
 */

$benevoles = $benevoles ?? [];

// Une couleur par statut, decidee une seule fois. Le vert ne sert qu'a un
// etat positif : c'est la regle de couleur du projet.
$couleurs = [
    'candidat' => 'secondary',
    'en_validation' => 'warning',
    'valide' => 'success',
    'refuse' => 'danger',
    'inactif' => 'secondary',
];

?>

<?php if (empty($benevoles)): ?>

    <div class="bg-body border rounded-3 p-5 text-center">
        <i class="bi bi-people fs-1 text-body-tertiary"></i>
        <div class="fw-medium mt-3"><?= Langue::t('benevoles.aucun') ?></div>
        <p class="text-body-secondary mb-0" style="font-size:.88rem">
            <?= Langue::t('benevoles.aucun_detail') ?>
        </p>
    </div>

<?php else: ?>

    <div class="bg-body border rounded-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="border-bottom">
                    <tr>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('benevoles.benevole') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></span></th>
                        <th class="text-center"><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('benevoles.permis') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('benevoles.candidature') ?></span></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($benevoles as $b): ?>
                    <?php
                    $statut = $b['statut'] ?? 'candidat';
                    $couleur = $couleurs[$statut] ?? 'secondary';
                    $nom = trim(($b['prenom'] ?? '') . ' ' . ($b['nom'] ?? ''));

                    // Deux lettres pour la pastille, faute de photo.
                    $initiales = mb_strtoupper(
                        mb_substr($b['prenom'] ?? '', 0, 1) . mb_substr($b['nom'] ?? '', 0, 1)
                    );

                    // L'API renvoie une date ISO complete ; on n'affiche que
                    // le jour, au format francais.
                    $date = !empty($b['date_candidature'])
                        ? date('d/m/Y', strtotime($b['date_candidature']))
                        : '';
                    ?>
                    <tr>
                        <td>
                            <a href="/back/benevoles/<?= (int) $b['id'] ?>"
                               class="d-flex align-items-center gap-2 text-decoration-none text-body">
                                <span class="d-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis fw-semibold flex-shrink-0"
                                      style="width:30px; height:30px; font-size:.68rem"><?= Vue::e($initiales) ?></span>
                                <span class="lh-sm">
                                    <span class="fw-medium d-block" style="font-size:.86rem"><?= Vue::e($nom) ?></span>
                                    <small class="text-body-tertiary" style="font-size:.74rem"><?= Vue::e($b['email'] ?? '') ?></small>
                                </span>
                            </a>
                        </td>
                        <td>
                            <span class="badge rounded-pill text-bg-<?= $couleur ?>" style="font-size:.66rem">
                                <?= Langue::t('benevoles.statut_' . $statut) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($b['permis_conduire'])): ?>
                                <i class="bi bi-check-lg text-success" title="<?= Vue::e(Langue::t('benevoles.permis')) ?>"></i>
                            <?php else: ?>
                                <span class="text-body-tertiary">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.82rem"><?= Vue::e($date) ?></td>
                        <td class="text-end">
                            <a href="/back/benevoles/<?= (int) $b['id'] ?>" class="btn btn-sm btn-light">
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
