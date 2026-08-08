<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * L'espace d'un commercant adherent.
 *
 * L'ordre de la page suit l'ordre des preoccupations : d'abord SUIS-JE EN
 * REGLE (l'adhesion), ensuite QUE PUIS-JE FAIRE (demander une collecte),
 * enfin QU'AI-JE FAIT (l'historique).
 *
 * Variables attendues : $commercant, $adhesion, $collectes, $statistiques,
 * $aujourdhui
 */

$commercant = $commercant ?? [];
$adhesion = $adhesion ?? null;
$collectes = $collectes ?? [];
$statistiques = $statistiques ?? ['collectes' => 0, 'realisees' => 0, 'annees' => 0];
$aujourdhui = $aujourdhui ?? date('Y-m-d');

$couleurs = [
    'demandee' => 'secondary',
    'planifiee' => 'info',
    'realisee' => 'success',
    'annulee' => 'danger',
];

// L'adhesion : trois etats, trois couleurs. Le seuil de 30 jours est celui
// auquel l'association envoie son premier rappel — l'ecran et l'email disent
// donc la meme chose au meme moment.
$jours = $adhesion['jours_restants'] ?? 0;
$expiree = $adhesion !== null && $jours < 0;
$bientot = $adhesion !== null && $jours >= 0 && $jours <= 30;
$couleurAdhesion = $expiree ? 'danger' : ($bientot ? 'warning' : 'success');

?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-5">
    <div>
        <h1 class="display-6 fw-bold mb-2">
            <?= Langue::t('espace.bonjour') ?><?= $commercant ? ', ' . Vue::e($commercant['raison_sociale'] ?? '') : '' ?>
        </h1>
        <p class="fs-5 text-body-secondary mb-0"><?= Langue::t('espace.sous_titre_commercant') ?></p>
    </div>
</div>

<div class="row g-4 mb-5">

    <div class="col-lg-7">
        <?php if ($adhesion === null): ?>

            <div class="border-start border-3 border-secondary-subtle ps-4">
                <div class="text-uppercase text-body-tertiary fw-semibold"
                     style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.mon_adhesion') ?></div>
                <div class="fs-4 fw-semibold mt-2"><?= Langue::t('espace.aucune_adhesion') ?></div>
                <p class="text-body-secondary mb-0" style="font-size:.9rem">
                    <?= Langue::t('espace.aucune_adhesion_detail') ?>
                </p>
            </div>

        <?php else: ?>
            <?php
            $fin = !empty($adhesion['date_fin']) ? date('d/m/Y', strtotime($adhesion['date_fin'])) : '';
            // La barre represente l'annee ecoulee : 365 jours moins ce qui reste.
            $pourcent = max(0, min(100, (int) (100 * (365 - $jours) / 365)));
            ?>

            <div class="border-start border-3 border-<?= $couleurAdhesion ?> ps-4">
                <div class="text-uppercase text-body-tertiary fw-semibold"
                     style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.mon_adhesion') ?></div>

                <div class="d-flex align-items-baseline gap-3 mt-2 flex-wrap">
                    <span class="fs-3 fw-bold">
                        <?= Langue::t('adhesions.statut_' . ($adhesion['statut'] ?? 'active')) ?>
                    </span>
                    <span class="text-body-secondary"><?= Langue::t('espace.jusqu_au') ?> <?= Vue::e($fin) ?></span>
                </div>

                <div class="progress mt-3" style="height:6px; max-width:340px">
                    <div class="progress-bar bg-<?= $couleurAdhesion ?>" style="width:<?= $pourcent ?>%"></div>
                </div>

                <div class="text-body-secondary mt-2" style="font-size:.88rem">
                    <?php if ($expiree): ?>
                        <?= Langue::t('espace.adhesion_expiree') ?>
                    <?php else: ?>
                        <?= Langue::t('espace.il_reste') ?> <strong><?= $jours ?></strong>
                        <?= Langue::t('espace.jours') ?>.
                        <?= Langue::t('espace.rappel_explication') ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="row g-3 text-center">
            <div class="col-4">
                <div class="fs-3 fw-bold text-primary lh-1"><?= (int) $statistiques['collectes'] ?></div>
                <div class="text-body-secondary" style="font-size:.82rem"><?= Langue::t('espace.stat_collectes') ?></div>
            </div>
            <div class="col-4">
                <div class="fs-3 fw-bold text-primary lh-1"><?= (int) $statistiques['realisees'] ?></div>
                <div class="text-body-secondary" style="font-size:.82rem"><?= Langue::t('espace.stat_realisees') ?></div>
            </div>
            <div class="col-4">
                <div class="fs-3 fw-bold text-primary lh-1"><?= (int) $statistiques['annees'] ?></div>
                <div class="text-body-secondary" style="font-size:.82rem"><?= Langue::t('espace.stat_adhesions') ?></div>
            </div>
        </div>
    </div>

</div>

<!-- L'action principale : demander une collecte. -->
<div class="border rounded-3 bg-body-tertiary p-4 mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="fw-semibold mb-1"><?= Langue::t('espace.invendus') ?></div>
            <div class="text-body-secondary" style="font-size:.9rem">
                <?= Langue::t('espace.invendus_detail') ?>
            </div>
        </div>

        <form method="post" action="/mon-espace/collectes" class="d-flex gap-2 align-items-center flex-wrap">
            <!-- min : le navigateur empeche deja de choisir une date passee.
                 Le controleur le reverifie -- l'attribut min ne protege que
                 ceux qui passent par le formulaire. -->
            <input type="date" name="date_prevue" class="form-control"
                   min="<?= Vue::e($aujourdhui) ?>" required style="width:auto">
            <button type="submit" class="btn btn-primary rounded-pill px-4">
                <?= Langue::t('espace.demander_collecte') ?>
            </button>
        </form>
    </div>
</div>

<div class="text-uppercase text-body-tertiary fw-semibold"
     style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.mes_collectes') ?></div>

<?php if (empty($collectes)): ?>

    <p class="text-body-secondary mt-3 mb-0" style="font-size:.9rem">
        <?= Langue::t('espace.aucune_collecte') ?>
    </p>

<?php else: ?>

    <div class="table-responsive mt-2">
        <table class="table table-sm align-middle">
            <thead class="border-bottom">
                <tr>
                    <th><span class="text-uppercase text-body-tertiary fw-semibold"
                              style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.date_prevue') ?></span></th>
                    <th><span class="text-uppercase text-body-tertiary fw-semibold"
                              style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></span></th>
                    <th><span class="text-uppercase text-body-tertiary fw-semibold"
                              style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.date_realisee') ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($collectes as $c): ?>
                <?php
                $statut = $c['statut'] ?? 'demandee';
                $prevue = !empty($c['date_prevue']) ? date('d/m/Y', strtotime($c['date_prevue'])) : '';
                $realisee = !empty($c['date_realisee']) ? date('d/m/Y', strtotime($c['date_realisee'])) : '';
                ?>
                <tr>
                    <td style="font-size:.86rem"><?= Vue::e($prevue) ?></td>
                    <td>
                        <span class="badge rounded-pill text-bg-<?= $couleurs[$statut] ?? 'secondary' ?>"
                              style="font-size:.66rem"><?= Langue::t('collectes.statut_' . $statut) ?></span>
                    </td>
                    <td style="font-size:.86rem">
                        <?= $realisee !== '' ? Vue::e($realisee) : '<span class="text-body-tertiary">&mdash;</span>' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>
