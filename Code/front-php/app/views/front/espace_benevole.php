<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * L'espace d'un benevole.
 *
 * L'ordre repond aux questions dans l'ordre ou elles se posent :
 *   1. ou en est ma candidature (le statut, et ce qui le bloque)
 *   2. quand suis-je attendu (le planning)
 *   3. que sais-je faire (les competences)
 *
 * Variables attendues : $benevole, $documents, $competences, $planning,
 * $documentsValides, $documentsTotal
 */

$benevole = $benevole ?? [];
$documents = $documents ?? [];
$competences = $competences ?? [];
$planning = $planning ?? [];
$documentsValides = $documentsValides ?? 0;
$documentsTotal = $documentsTotal ?? 0;

$statut = $benevole['statut'] ?? 'candidat';

$couleurs = [
    'candidat' => 'warning',
    'valide' => 'success',
    'refuse' => 'danger',
    'inactif' => 'secondary',
];
$couleur = $couleurs[$statut] ?? 'secondary';

$pourcent = $documentsTotal > 0 ? (int) (100 * $documentsValides / $documentsTotal) : 0;

?>

<div class="mb-5">
    <h1 class="display-6 fw-bold mb-2">
        <?= Langue::t('espace.bonjour') ?><?= $benevole ? ', ' . Vue::e($benevole['prenom'] ?? '') : '' ?>
    </h1>
    <p class="fs-5 text-body-secondary mb-0"><?= Langue::t('espace.sous_titre_benevole') ?></p>
</div>

<!-- ================================================================
     1. Ou en est ma candidature.
     ================================================================ -->
<div class="border-start border-3 border-<?= $couleur ?> ps-4 mb-5">
    <div class="text-uppercase text-body-tertiary fw-semibold"
         style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.ma_candidature') ?></div>

    <div class="fs-3 fw-bold mt-2"><?= Langue::t('benevoles.statut_' . $statut) ?></div>

    <?php if ($statut === 'valide'): ?>

        <p class="text-body-secondary mb-0" style="font-size:.92rem">
            <?= Langue::t('espace.candidature_validee') ?>
        </p>

    <?php elseif ($statut === 'refuse'): ?>

        <p class="text-body-secondary mb-0" style="font-size:.92rem">
            <?= Langue::t('espace.candidature_refusee') ?>
        </p>

    <?php else: ?>

        <!-- Le coeur de l'ecran : dire CE QUI MANQUE. Sans ça, un candidat
             ne comprend pas pourquoi il reste bloque. -->
        <p class="text-body-secondary mb-2" style="font-size:.92rem">
            <?= Langue::t('espace.candidature_en_cours') ?>
        </p>

        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="progress flex-grow-1" style="height:6px; max-width:280px">
                <div class="progress-bar bg-<?= $couleur ?>" style="width:<?= $pourcent ?>%"></div>
            </div>
            <span class="text-body-secondary" style="font-size:.88rem">
                <?= $documentsValides ?> / <?= $documentsTotal ?> <?= Langue::t('espace.justificatifs') ?>
            </span>
        </div>

    <?php endif; ?>
</div>

<!-- Les justificatifs : affiches dans tous les cas, c'est le dossier. -->
<div class="mb-5">
    <div class="text-uppercase text-body-tertiary fw-semibold mb-2"
         style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.mes_justificatifs') ?></div>

    <?php if (empty($documents)): ?>

        <p class="text-body-secondary mb-0" style="font-size:.9rem">
            <?= Langue::t('espace.aucun_justificatif') ?>
        </p>

    <?php else: ?>

        <div class="border-top">
            <?php foreach ($documents as $d): ?>
                <?php $valide = !empty($d['valide']); ?>
                <div class="d-flex justify-content-between align-items-center gap-3 py-3 border-bottom flex-wrap">
                    <div>
                        <div class="fw-medium" style="font-size:.92rem">
                            <?= Vue::e($d['type_document'] ?? '') ?>
                        </div>
                        <?php if (!$valide): ?>
                            <small class="text-body-tertiary"><?= Langue::t('espace.en_attente_verification') ?></small>
                        <?php endif; ?>
                    </div>
                    <span class="badge rounded-pill text-bg-<?= $valide ? 'success' : 'warning' ?>"
                          style="font-size:.68rem">
                        <?= Langue::t($valide ? 'espace.verifie' : 'espace.a_verifier') ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<!-- ================================================================
     2. Quand suis-je attendu.
     ================================================================ -->
<div class="mb-5">
    <div class="text-uppercase text-body-tertiary fw-semibold mb-2"
         style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.mon_planning') ?></div>

    <?php if ($statut !== 'valide'): ?>

        <p class="text-body-secondary mb-0" style="font-size:.9rem">
            <?= Langue::t('espace.planning_apres_validation') ?>
        </p>

    <?php elseif (empty($planning)): ?>

        <p class="text-body-secondary mb-0" style="font-size:.9rem">
            <?= Langue::t('espace.aucune_mission') ?>
        </p>

    <?php else: ?>

        <div class="border-top">
            <?php foreach ($planning as $p): ?>
                <?php
                $date = !empty($p['date_creneau']) ? date('d/m/Y', strtotime($p['date_creneau'])) : '';
                // L'API renvoie deja "HH:MM" : rien a decouper.
                $horaire = ($p['heure_debut'] ?? '') . ' – ' . ($p['heure_fin'] ?? '');
                ?>
                <div class="d-flex justify-content-between align-items-center gap-3 py-3 border-bottom flex-wrap">
                    <div>
                        <div class="fw-medium"><?= Vue::e($p['service_nom'] ?? '') ?></div>
                        <div class="text-body-secondary" style="font-size:.88rem">
                            <?= Vue::e($date) ?> · <?= Vue::e($horaire) ?>
                            <?= !empty($p['lieu']) ? ' · ' . Vue::e($p['lieu']) : '' ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<!-- ================================================================
     3. Que sais-je faire.
     ================================================================ -->
<div>
    <div class="text-uppercase text-body-tertiary fw-semibold mb-2"
         style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.mes_competences') ?></div>

    <?php if (empty($competences)): ?>

        <p class="text-body-secondary mb-0" style="font-size:.9rem">
            <?= Langue::t('espace.aucune_competence') ?>
        </p>

    <?php else: ?>

        <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($competences as $c): ?>
                <span class="badge rounded-pill text-bg-light border px-3 py-2" style="font-size:.82rem">
                    <?= Vue::e($c['libelle'] ?? '') ?>
                </span>
            <?php endforeach; ?>
        </div>

        <small class="text-body-tertiary d-block mt-3" style="font-size:.82rem">
            <?= Langue::t('espace.competences_aide') ?>
        </small>

    <?php endif; ?>
</div>
