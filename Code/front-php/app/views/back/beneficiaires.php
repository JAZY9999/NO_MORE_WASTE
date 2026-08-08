<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Les beneficiaires des tournees de distribution.
 *
 * Ecran volontairement simple : une liste et un formulaire de creation sur la
 * meme page. Un beneficiaire, c'est un nom et une adresse -- pas de quoi
 * justifier un ecran de detail separe, qui obligerait a naviguer pour lire
 * trois champs.
 *
 * Variables attendues : $beneficiaires, $types
 */

$beneficiaires = $beneficiaires ?? [];
$types = $types ?? [];

// Une association et un particulier ne se traitent pas pareil : la couleur
// permet de les distinguer sans lire le libelle.
$couleurs = [
    'association_caritative' => 'info',
    'particulier_detresse' => 'warning',
];

?>

<div class="row g-3">

    <!-- La liste occupe la plus grande part : c'est ce qu'on vient consulter. -->
    <div class="col-xl-8">

        <?php if (empty($beneficiaires)): ?>

            <div class="bg-body border rounded-3 p-5 text-center">
                <i class="bi bi-heart fs-1 text-body-tertiary"></i>
                <div class="fw-medium mt-3"><?= Langue::t('beneficiaires.aucun') ?></div>
                <p class="text-body-secondary mb-0" style="font-size:.88rem">
                    <?= Langue::t('beneficiaires.aucun_detail') ?>
                </p>
            </div>

        <?php else: ?>

            <div class="bg-body border rounded-3">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="border-bottom">
                            <tr>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('beneficiaires.nom') ?></span></th>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('beneficiaires.type') ?></span></th>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('beneficiaires.adresse') ?></span></th>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('beneficiaires.contact') ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($beneficiaires as $b): ?>
                            <?php
                            $type = $b['type'] ?? '';
                            $adresse = trim(($b['adresse'] ?? '') . ' ' . ($b['ville'] ?? ''));
                            ?>
                            <tr>
                                <td class="fw-medium" style="font-size:.86rem">
                                    <?= Vue::e($b['nom'] ?? '') ?>
                                </td>
                                <td>
                                    <span class="badge rounded-pill text-bg-<?= $couleurs[$type] ?? 'secondary' ?>"
                                          style="font-size:.66rem">
                                        <?= Langue::t('beneficiaires.type_' . $type) ?>
                                    </span>
                                </td>
                                <td style="font-size:.82rem">
                                    <?= $adresse !== ''
                                        ? Vue::e($adresse)
                                        : '<span class="text-body-tertiary">&mdash;</span>' ?>
                                </td>
                                <td class="lh-sm" style="font-size:.82rem">
                                    <?= !empty($b['contact']) ? Vue::e($b['contact']) : '' ?>
                                    <?php if (!empty($b['telephone'])): ?>
                                        <br><small class="text-body-tertiary" style="font-size:.74rem">
                                            <?= Vue::e($b['telephone']) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if (empty($b['contact']) && empty($b['telephone'])): ?>
                                        <span class="text-body-tertiary">&mdash;</span>
                                    <?php endif; ?>
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
            <?= Langue::t('beneficiaires.role_dans_tournees') ?>
        </div>

    </div>

    <!-- Le formulaire de creation, sur la meme page. -->
    <div class="col-xl-4">
        <div class="bg-body border rounded-3 p-3">
            <div class="text-uppercase text-body-tertiary fw-semibold mb-3"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('beneficiaires.nouveau') ?></div>

            <form method="post" action="/back/beneficiaires">

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('beneficiaires.nom') ?> *</label>
                    <input type="text" name="nom" class="form-control form-control-sm" required>
                </div>

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('beneficiaires.type') ?> *</label>
                    <!-- Un menu et non un champ libre : la base n'accepte que
                         ces deux valeurs (contrainte CHECK). -->
                    <select name="type" class="form-select form-select-sm" required>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= Vue::e($t) ?>"><?= Langue::t('beneficiaires.type_' . $t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('beneficiaires.adresse') ?></label>
                    <input type="text" name="adresse" class="form-control form-control-sm">
                </div>

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('beneficiaires.ville') ?></label>
                    <input type="text" name="ville" class="form-control form-control-sm">
                </div>

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('beneficiaires.contact') ?></label>
                    <input type="text" name="contact" class="form-control form-control-sm">
                </div>

                <div class="mb-3">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('beneficiaires.telephone') ?></label>
                    <input type="tel" name="telephone" class="form-control form-control-sm">
                </div>

                <button type="submit" class="btn btn-sm btn-primary"><?= Langue::t('commun.creer') ?></button>

                <small class="text-body-tertiary d-block mt-2" style="font-size:.76rem">
                    <?= Langue::t('beneficiaires.adresse_aide') ?>
                </small>

            </form>
        </div>
    </div>

</div>
