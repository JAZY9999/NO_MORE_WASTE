<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Le detail d'une adhesion et l'historique de ses rappels.
 *
 * L'HISTORIQUE EST LA PREUVE
 *
 * Le sujet demande un "rappel automatique de renouvellement". Dire qu'il
 * existe ne suffit pas : ce tableau montre quel rappel est parti, quand, et a
 * quelle adresse. C'est ce qu'on ouvre en demonstration pour prouver que le
 * mecanisme tourne vraiment.
 *
 * Variables attendues : $adhesion, $historique, $delais
 */

$adhesion = $adhesion ?? [];
$historique = $historique ?? [];
$delais = $delais ?? ['j30' => 30, 'j7' => 7, 'ex_abonne' => 180];

$id = (int) ($adhesion['id'] ?? 0);
$statut = $adhesion['statut'] ?? 'active';
$jours = (int) ($adhesion['jours_restants'] ?? 0);

$debut = !empty($adhesion['date_debut']) ? date('d/m/Y', strtotime($adhesion['date_debut'])) : '';
$fin = !empty($adhesion['date_fin']) ? date('d/m/Y', strtotime($adhesion['date_fin'])) : '';

$couleurs = [
    'active' => 'success',
    'expiree' => 'danger',
    'resiliee' => 'secondary',
    'en_attente' => 'warning',
];

// Le libelle de chaque type de rappel. Les cles viennent de la base
// (colonne type_rappel), ecrites par le job et par la relance manuelle.
$typesRappel = [
    'j30' => 'adhesions.rappel_j30',
    'j7' => 'adhesions.rappel_j7',
    'ex_abonne' => 'adhesions.rappel_ex_abonne',
    'manuel' => 'adhesions.rappel_manuel',
];

$aEmail = !empty($adhesion['email']);

?>

<div class="row g-3 mb-3">

    <div class="col-md-4">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></div>
            <div class="mt-2">
                <span class="badge rounded-pill text-bg-<?= $couleurs[$statut] ?? 'secondary' ?>">
                    <?= Langue::t('adhesions.statut_' . $statut) ?>
                </span>
            </div>
            <small class="text-body-tertiary d-block mt-2" style="font-size:.76rem">
                <?= Vue::e($debut) ?> &rarr; <?= Vue::e($fin) ?>
            </small>
        </div>
    </div>

    <div class="col-md-4">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('adhesions.echeance') ?></div>
            <div class="fs-5 fw-semibold mt-1">
                <?php if ($jours < 0): ?>
                    <span class="text-danger-emphasis">
                        <?= Langue::t('adhesions.depuis') ?> <?= abs($jours) ?> <?= Langue::t('espace.jours') ?>
                    </span>
                <?php else: ?>
                    <?= Langue::t('adhesions.dans') ?> <?= $jours ?> <?= Langue::t('espace.jours') ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('adhesions.relance_manuelle') ?></div>

            <?php if ($aEmail): ?>
                <form method="post" action="/back/adhesions/<?= $id ?>" class="mt-2">
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-envelope"></i> <?= Langue::t('adhesions.relancer') ?>
                    </button>
                </form>
                <small class="text-body-tertiary d-block mt-2" style="font-size:.74rem">
                    <?= Vue::e($adhesion['email']) ?>
                </small>
            <?php else: ?>
                <!-- Sans adresse email, l'API refuse la relance (400). Autant
                     le dire ici plutot que de proposer un bouton qui echoue. -->
                <div class="text-body-secondary mt-2" style="font-size:.82rem">
                    <?= Langue::t('adhesions.sans_email') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ================================================================
     L'historique : la preuve que le rappel automatique fonctionne.
     ================================================================ -->
<div class="text-uppercase text-body-tertiary fw-semibold mb-2"
     style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('adhesions.historique') ?></div>

<?php if (empty($historique)): ?>

    <div class="bg-body border rounded-3 p-4 text-body-secondary" style="font-size:.86rem">
        <?= Langue::t('adhesions.aucun_rappel') ?>
        <div class="text-body-tertiary mt-1" style="font-size:.8rem">
            <?= sprintf(
                Langue::t('adhesions.aucun_rappel_detail'),
                (int) $delais['j30'],
                (int) $delais['j7']
            ) ?>
        </div>
    </div>

<?php else: ?>

    <div class="bg-body border rounded-3">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="border-bottom">
                    <tr>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('adhesions.type_rappel') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('adhesions.date_envoi') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('adhesions.destinataire') ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($historique as $h): ?>
                    <?php
                    $type = $h['type_rappel'] ?? '';
                    $envoi = !empty($h['date_envoi'])
                        ? date('d/m/Y à H:i', strtotime($h['date_envoi']))
                        : '';
                    ?>
                    <tr>
                        <td style="font-size:.86rem">
                            <?php // Un type inconnu s'affiche tel quel plutot que de disparaitre. ?>
                            <?= isset($typesRappel[$type]) ? Langue::t($typesRappel[$type]) : Vue::e($type) ?>
                        </td>
                        <td style="font-size:.84rem"><?= Vue::e($envoi) ?></td>
                        <td style="font-size:.84rem"><?= Vue::e($h['email_destinataire'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="border-start border-3 border-secondary-subtle ps-3 py-1 mt-3 text-body-secondary"
         style="font-size:.8rem">
        <?= Langue::t('adhesions.jamais_deux_fois') ?>
    </div>

<?php endif; ?>
