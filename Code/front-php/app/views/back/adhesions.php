<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Les adhesions et le rappel automatique de renouvellement.
 *
 * L'ordre de la page n'est pas neutre : le JOB AUTOMATIQUE est en tete, avant
 * les chiffres et la liste. C'est le point le plus cite du sujet, et il
 * tournait jusqu'ici sans que rien ne le montre.
 *
 * Variables attendues : $adhesions, $compteurs, $delais
 */

$adhesions = $adhesions ?? [];
$compteurs = $compteurs ?? ['actives' => 0, 'a_renouveler' => 0, 'expirees' => 0];
$delais = $delais ?? ['j30' => 30, 'j7' => 7, 'ex_abonne' => 180];

$couleurs = [
    'active' => 'success',
    'expiree' => 'danger',
    'resiliee' => 'secondary',
    'en_attente' => 'warning',
];

?>

<!-- ================================================================
     Le job automatique : visible et pilotable.
     ================================================================ -->
<div class="bg-body border rounded-3 p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="lh-sm">
            <div class="fw-medium" style="font-size:.9rem">
                <i class="bi bi-robot text-primary"></i>
                <?= Langue::t('adhesions.job_titre') ?>
            </div>
            <small class="text-body-tertiary" style="font-size:.76rem">
                <?= Langue::t('adhesions.job_aide') ?>
                <?= sprintf(
                    Langue::t('adhesions.job_delais'),
                    (int) $delais['j30'],
                    (int) $delais['j7'],
                    (int) $delais['ex_abonne']
                ) ?>
            </small>
        </div>

        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="badge rounded-pill text-bg-success"><?= Langue::t('adhesions.job_actif') ?></span>
            <!-- POST et non GET : ce bouton envoie reellement des emails.
                 Un GET serait rejoue par un simple rafraichissement. -->
            <form method="post" action="/back/adhesions">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-play-fill"></i> <?= Langue::t('adhesions.declencher') ?>
                </button>
            </form>
        </div>
    </div>

    <div class="border-start border-3 border-secondary-subtle ps-3 py-1 mt-3 text-body-secondary"
         style="font-size:.78rem">
        <?= Langue::t('adhesions.delais_en_dur') ?>
    </div>
</div>

<!-- ================================================================
     Les chiffres.
     ================================================================ -->
<div class="row g-3 mb-3">
    <?php
    // Un tableau plutot que trois blocs recopies : ajouter un compteur
    // demande une ligne, pas dix.
    $cartes = [
        ['cle' => 'actives', 'libelle' => 'adhesions.compteur_actives', 'couleur' => ''],
        ['cle' => 'a_renouveler', 'libelle' => 'adhesions.compteur_a_renouveler', 'couleur' => 'text-warning-emphasis'],
        ['cle' => 'expirees', 'libelle' => 'adhesions.compteur_expirees', 'couleur' => 'text-danger-emphasis'],
    ];
    foreach ($cartes as $carte):
        $valeur = (int) ($compteurs[$carte['cle']] ?? 0);
    ?>
        <div class="col-6 col-xl-4">
            <div class="bg-body border rounded-3 p-3 h-100">
                <div class="text-uppercase text-body-tertiary fw-semibold"
                     style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t($carte['libelle']) ?></div>
                <div class="fs-3 fw-semibold lh-1 mt-1 <?= $valeur > 0 ? $carte['couleur'] : '' ?>">
                    <?= $valeur ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ================================================================
     La liste.
     ================================================================ -->
<?php if (empty($adhesions)): ?>

    <div class="bg-body border rounded-3 p-5 text-center">
        <i class="bi bi-card-checklist fs-1 text-body-tertiary"></i>
        <div class="fw-medium mt-3"><?= Langue::t('adhesions.aucune') ?></div>
        <p class="text-body-secondary mb-0" style="font-size:.88rem">
            <?= Langue::t('adhesions.aucune_detail') ?>
        </p>
    </div>

<?php else: ?>

    <div class="bg-body border rounded-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="border-bottom">
                    <tr>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('adhesions.commercant') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('adhesions.echeance') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></span></th>
                        <th class="text-end"><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('adhesions.cotisation') ?></span></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($adhesions as $a): ?>
                    <?php
                    $id = (int) ($a['id'] ?? 0);
                    $statut = $a['statut'] ?? 'active';
                    $jours = (int) ($a['jours_restants'] ?? 0);
                    $fin = !empty($a['date_fin']) ? date('d/m/Y', strtotime($a['date_fin'])) : '';

                    // L'urgence se lit a la couleur : rouge si l'echeance est
                    // passee, orange si le premier rappel est deja parti.
                    if ($jours < 0) {
                        $urgence = 'text-danger-emphasis';
                    } elseif ($jours <= (int) $delais['j30']) {
                        $urgence = 'text-warning-emphasis';
                    } else {
                        $urgence = 'text-body-tertiary';
                    }
                    ?>
                    <tr>
                        <td>
                            <a href="/back/adhesions/<?= $id ?>"
                               class="text-decoration-none text-body fw-medium" style="font-size:.86rem">
                                <?= Vue::e($a['raison_sociale'] ?? '') ?>
                            </a>
                        </td>
                        <td class="lh-sm" style="font-size:.82rem">
                            <?= Vue::e($fin) ?><br>
                            <small class="<?= $urgence ?>" style="font-size:.72rem">
                                <?php if ($jours < 0): ?>
                                    <?= Langue::t('adhesions.depuis') ?> <?= abs($jours) ?> <?= Langue::t('espace.jours') ?>
                                <?php else: ?>
                                    <?= Langue::t('adhesions.dans') ?> <?= $jours ?> <?= Langue::t('espace.jours') ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge rounded-pill text-bg-<?= $couleurs[$statut] ?? 'secondary' ?>"
                                  style="font-size:.66rem"><?= Langue::t('adhesions.statut_' . $statut) ?></span>
                        </td>
                        <td class="text-end" style="font-size:.84rem">
                            <?= !empty($a['montant_cotisation'])
                                ? Vue::e($a['montant_cotisation']) . ' &euro;'
                                : '<span class="text-body-tertiary">&mdash;</span>' ?>
                        </td>
                        <td class="text-end">
                            <a href="/back/adhesions/<?= $id ?>" class="btn btn-sm btn-light">
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
