<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Fiche d'un benevole : l'ecran cle du back-office.
 *
 * Il rend visible la regle du sujet : un benevole ne peut etre valide que si
 * TOUS ses documents le sont. Le bandeau du haut dit pourquoi le bouton est
 * desactive -- plutot que de laisser cliquer puis afficher un refus.
 *
 * Variables attendues : $benevole, $documents, $competences,
 * $competencesRestantes, $documentsValides, $documentsTotal, $peutValider
 */

$benevole = $benevole ?? [];
$documents = $documents ?? [];
$competences = $competences ?? [];
$competencesRestantes = $competencesRestantes ?? [];
$documentsValides = $documentsValides ?? 0;
$documentsTotal = $documentsTotal ?? 0;
$peutValider = $peutValider ?? false;

$id = (int) ($benevole['id'] ?? 0);
$statut = $benevole['statut'] ?? 'candidat';
$dejaValide = ($statut === 'valide');

$pourcent = $documentsTotal > 0 ? (int) (100 * $documentsValides / $documentsTotal) : 0;

?>

<?php if ($dejaValide): ?>

    <div class="bg-success-subtle border border-success-subtle rounded-3 p-3 mb-3">
        <div class="d-flex gap-3 align-items-center">
            <i class="bi bi-check-circle-fill text-success-emphasis"></i>
            <div>
                <div class="fw-medium text-success-emphasis" style="font-size:.9rem">
                    <?= Langue::t('benevoles.deja_valide') ?>
                </div>
                <div class="text-body-secondary" style="font-size:.84rem">
                    <?= Langue::t('benevoles.deja_valide_detail') ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif (!$peutValider): ?>

    <!-- LA regle du sujet, rendue visible AVANT le clic. -->
    <div class="bg-warning-subtle border border-warning-subtle rounded-3 p-3 mb-3">
        <div class="d-flex gap-3 align-items-start">
            <i class="bi bi-hourglass-split text-warning-emphasis"></i>
            <div class="flex-grow-1">
                <div class="fw-medium text-warning-emphasis" style="font-size:.9rem">
                    <?= Langue::t('benevoles.validation_impossible') ?>
                </div>
                <p class="mb-2 text-body-secondary" style="font-size:.84rem">
                    <?= Langue::t('benevoles.validation_regle') ?>
                </p>
                <div class="d-flex align-items-center gap-2" style="max-width:280px">
                    <div class="progress flex-grow-1" style="height:5px">
                        <div class="progress-bar bg-warning" style="width:<?= $pourcent ?>%"></div>
                    </div>
                    <small class="text-warning-emphasis fw-semibold" style="font-size:.74rem">
                        <?= $documentsValides ?>/<?= $documentsTotal ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<div class="row g-3">

    <div class="col-lg-8">

        <!-- DOCUMENTS -->
        <div class="bg-body border rounded-3 mb-3">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <span class="text-uppercase text-body-tertiary fw-semibold"
                      style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('benevoles.documents') ?></span>
                <span class="badge rounded-pill text-bg-<?= $peutValider ? 'success' : 'warning' ?>"
                      style="font-size:.66rem">
                    <?= $documentsValides ?>/<?= $documentsTotal ?> <?= Langue::t('benevoles.valides') ?>
                </span>
            </div>

            <?php if (empty($documents)): ?>
                <div class="p-4 text-center text-body-secondary" style="font-size:.86rem">
                    <?= Langue::t('benevoles.aucun_document') ?>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $d): ?>
                    <?php $estValide = !empty($d['valide']); ?>
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom
                                <?= $estValide ? '' : 'bg-warning-subtle' ?>">
                        <div class="lh-sm">
                            <div style="font-size:.86rem"><?= Vue::e($d['type_document'] ?? '') ?></div>
                            <small class="text-body-tertiary" style="font-size:.74rem">
                                <?= Vue::e($d['chemin_fichier'] ?? '') ?>
                            </small>
                        </div>
                        <?php if ($estValide): ?>
                            <span class="badge rounded-pill text-bg-success" style="font-size:.66rem">
                                <?= Langue::t('benevoles.valide') ?>
                            </span>
                        <?php else: ?>
                            <form method="post" action="/back/benevoles/<?= $id ?>" class="m-0">
                                <input type="hidden" name="action" value="valider_document">
                                <input type="hidden" name="document_id" value="<?= (int) $d['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <?= Langue::t('benevoles.valider_document') ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- DECISION -->
        <div class="d-flex gap-2 justify-content-end">
            <?php if (!$dejaValide): ?>
                <form method="post" action="/back/benevoles/<?= $id ?>" class="m-0">
                    <input type="hidden" name="action" value="refuser">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <?= Langue::t('benevoles.refuser') ?>
                    </button>
                </form>
                <form method="post" action="/back/benevoles/<?= $id ?>" class="m-0">
                    <input type="hidden" name="action" value="valider">
                    <!-- Desactive tant que la regle n'est pas remplie : l'API
                         refuserait de toute facon, autant l'empecher ici. -->
                    <button type="submit" class="btn btn-sm btn-success" <?= $peutValider ? '' : 'disabled' ?>>
                        <?= Langue::t('benevoles.valider') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">

        <!-- IDENTITE -->
        <div class="bg-body border rounded-3 mb-3">
            <div class="p-3 d-flex flex-column gap-2" style="font-size:.84rem">
                <div>
                    <i class="bi bi-envelope text-body-tertiary"></i>
                    <a href="mailto:<?= Vue::e($benevole['email'] ?? '') ?>"
                       class="text-decoration-none ms-1"><?= Vue::e($benevole['email'] ?? '') ?></a>
                </div>
                <?php if (!empty($benevole['telephone'])): ?>
                    <div><i class="bi bi-telephone text-body-tertiary"></i>
                        <span class="ms-1"><?= Vue::e($benevole['telephone']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($benevole['adresse'])): ?>
                    <div><i class="bi bi-geo-alt text-body-tertiary"></i>
                        <span class="ms-1"><?= Vue::e($benevole['adresse']) ?></span></div>
                <?php endif; ?>
                <div>
                    <?php if (!empty($benevole['permis_conduire'])): ?>
                        <i class="bi bi-car-front text-success"></i>
                        <span class="ms-1"><?= Langue::t('benevoles.permis') ?></span>
                    <?php else: ?>
                        <i class="bi bi-car-front text-body-tertiary"></i>
                        <span class="ms-1 text-body-tertiary"><?= Langue::t('benevoles.sans_permis') ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- COMPETENCES -->
        <div class="bg-body border rounded-3">
            <div class="px-3 py-2 border-bottom">
                <span class="text-uppercase text-body-tertiary fw-semibold"
                      style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('benevoles.competences') ?></span>
            </div>
            <div class="p-3">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php if (empty($competences)): ?>
                        <small class="text-body-tertiary"><?= Langue::t('benevoles.aucune_competence') ?></small>
                    <?php else: ?>
                        <?php foreach ($competences as $c): ?>
                            <form method="post" action="/back/benevoles/<?= $id ?>" class="m-0">
                                <input type="hidden" name="action" value="retirer_competence">
                                <input type="hidden" name="competence_id" value="<?= (int) $c['id'] ?>">
                                <span class="badge rounded-pill text-bg-light border text-body d-inline-flex align-items-center gap-1"
                                      style="font-size:.7rem">
                                    <?= Vue::e($c['libelle'] ?? '') ?>
                                    <button type="submit" class="btn btn-sm btn-link p-0 text-body-tertiary lh-1"
                                            style="font-size:.8rem"
                                            title="<?= Vue::e(Langue::t('commun.retirer')) ?>">&times;</button>
                                </span>
                            </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($competencesRestantes)): ?>
                    <form method="post" action="/back/benevoles/<?= $id ?>" class="input-group input-group-sm">
                        <input type="hidden" name="action" value="ajouter_competence">
                        <select name="competence_id" class="form-select">
                            <?php foreach ($competencesRestantes as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= Vue::e($c['libelle'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-primary"><?= Langue::t('commun.ajouter') ?></button>
                    </form>
                <?php endif; ?>

                <small class="text-body-tertiary d-block mt-2" style="font-size:.74rem">
                    <?= Langue::t('benevoles.competences_aide') ?>
                </small>
            </div>
        </div>
    </div>
</div>
