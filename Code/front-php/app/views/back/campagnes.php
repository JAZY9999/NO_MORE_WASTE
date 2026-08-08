<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Les campagnes d'emailing ciblees.
 *
 * Liste a gauche, formulaire de creation a droite. Creer n'envoie RIEN :
 * l'envoi se declenche depuis la fiche, apres avoir vu les destinataires.
 *
 * Variables attendues : $campagnes, $villes, $statuts
 */

$campagnes = $campagnes ?? [];
$villes = $villes ?? [];
$statuts = $statuts ?? [];

// Reaffiche la saisie apres une erreur.
$saisie = $_SESSION['campagne_saisie'] ?? [];
unset($_SESSION['campagne_saisie']);

$val = function (string $champ) use ($saisie): string {
    return Vue::e($saisie[$champ] ?? '');
};

?>

<div class="row g-3">

    <div class="col-xl-7">

        <?php if (empty($campagnes)): ?>

            <div class="bg-body border rounded-3 p-5 text-center">
                <i class="bi bi-megaphone fs-1 text-body-tertiary"></i>
                <div class="fw-medium mt-3"><?= Langue::t('campagnes.aucune') ?></div>
                <p class="text-body-secondary mb-0" style="font-size:.88rem">
                    <?= Langue::t('campagnes.aucune_detail') ?>
                </p>
            </div>

        <?php else: ?>

            <div class="bg-body border rounded-3">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="border-bottom">
                            <tr>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('campagnes.nom') ?></span></th>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('campagnes.cible') ?></span></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($campagnes as $c): ?>
                            <?php
                            // On resume les criteres en une phrase lisible.
                            // Sans ca, la colonne afficherait des valeurs
                            // techniques que personne ne relie a une cible.
                            $criteres = [];
                            if (!empty($c['critere_ville'])) {
                                $criteres[] = Vue::e($c['critere_ville']);
                            }
                            if (!empty($c['critere_pays'])) {
                                $criteres[] = Vue::e($c['critere_pays']);
                            }
                            if (!empty($c['critere_statut_adhesion'])) {
                                $criteres[] = Langue::t('adhesions.statut_' . $c['critere_statut_adhesion']);
                            }
                            if (!empty($c['critere_adhesion_expiree_depuis_jours'])) {
                                $criteres[] = sprintf(
                                    Langue::t('campagnes.expiree_depuis'),
                                    (int) $c['critere_adhesion_expiree_depuis_jours']
                                );
                            }
                            ?>
                            <tr>
                                <td class="fw-medium" style="font-size:.86rem">
                                    <a href="/back/campagnes/<?= (int) $c['id'] ?>"
                                       class="text-decoration-none text-body">
                                        <?= Vue::e($c['nom'] ?? '') ?>
                                    </a>
                                    <div class="text-body-tertiary" style="font-size:.74rem">
                                        <?= Vue::e($c['sujet_email'] ?? '') ?>
                                    </div>
                                </td>
                                <td style="font-size:.82rem">
                                    <?= empty($criteres)
                                        ? '<span class="text-body-secondary">' . Langue::t('campagnes.tous_les_commercants') . '</span>'
                                        : implode(' &middot; ', $criteres) ?>
                                </td>
                                <td class="text-end">
                                    <a href="/back/campagnes/<?= (int) $c['id'] ?>" class="btn btn-sm btn-light">
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

        <div class="border-start border-3 border-secondary-subtle ps-3 py-1 mt-3 text-body-secondary"
             style="font-size:.8rem">
            <?= Langue::t('campagnes.creer_n_envoie_pas') ?>
        </div>

    </div>

    <div class="col-xl-5">
        <div class="bg-body border rounded-3 p-3">
            <div class="text-uppercase text-body-tertiary fw-semibold mb-3"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('campagnes.nouvelle') ?></div>

            <form method="post" action="/back/campagnes">

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('campagnes.nom') ?> *</label>
                    <input type="text" name="nom" class="form-control form-control-sm" required
                           value="<?= $val('nom') ?>">
                    <small class="text-body-tertiary" style="font-size:.74rem">
                        <?= Langue::t('campagnes.nom_aide') ?>
                    </small>
                </div>

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('campagnes.sujet') ?> *</label>
                    <input type="text" name="sujet_email" class="form-control form-control-sm" required
                           value="<?= $val('sujet_email') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('campagnes.corps') ?> *</label>
                    <textarea name="corps_email" class="form-control form-control-sm" rows="5"
                              required><?= $val('corps_email') ?></textarea>
                    <small class="text-body-tertiary" style="font-size:.74rem">
                        <?= Langue::t('campagnes.variable_aide') ?>
                    </small>
                </div>

                <div class="text-uppercase text-body-tertiary fw-semibold mb-2"
                     style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('campagnes.criteres') ?></div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" style="font-size:.78rem"><?= Langue::t('beneficiaires.ville') ?></label>
                        <select name="critere_ville" class="form-select form-select-sm">
                            <option value=""><?= Langue::t('campagnes.toutes') ?></option>
                            <?php foreach ($villes as $v): ?>
                                <option value="<?= Vue::e($v) ?>"
                                    <?= ($saisie['critere_ville'] ?? '') === $v ? 'selected' : '' ?>>
                                    <?= Vue::e($v) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:.78rem"><?= Langue::t('commercants.pays') ?></label>
                        <input type="text" name="critere_pays" class="form-control form-control-sm"
                               value="<?= $val('critere_pays') ?>">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" style="font-size:.78rem"><?= Langue::t('campagnes.statut_adhesion') ?></label>
                        <select name="critere_statut_adhesion" class="form-select form-select-sm">
                            <option value=""><?= Langue::t('campagnes.tous_statuts') ?></option>
                            <?php foreach ($statuts as $s): ?>
                                <option value="<?= Vue::e($s) ?>"
                                    <?= ($saisie['critere_statut_adhesion'] ?? '') === $s ? 'selected' : '' ?>>
                                    <?= Langue::t('adhesions.statut_' . $s) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:.78rem"><?= Langue::t('campagnes.expiree_depuis_label') ?></label>
                        <input type="number" name="critere_adhesion_expiree_depuis_jours" min="0"
                               class="form-control form-control-sm"
                               value="<?= $val('critere_adhesion_expiree_depuis_jours') ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-sm btn-primary"><?= Langue::t('commun.creer') ?></button>

                <small class="text-body-tertiary d-block mt-2" style="font-size:.76rem">
                    <?= Langue::t('campagnes.criteres_aide') ?>
                </small>

            </form>
        </div>
    </div>

</div>
