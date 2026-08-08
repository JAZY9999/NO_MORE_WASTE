<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Les emplacements de stock : ou les produits sont ranges physiquement.
 *
 * Trois niveaux -- zone, rayon, etagere -- qui permettent a un benevole de
 * retrouver un produit APRES l'avoir cherche par code-barre. Sans eux, la
 * recherche dirait "ce produit existe" sans dire ou aller le chercher.
 *
 * Variables attendues : $emplacements
 */

$emplacements = $emplacements ?? [];

?>

<div class="row g-3">

    <div class="col-lg-8">
        <?php if (empty($emplacements)): ?>

            <div class="bg-body border rounded-3 p-5 text-center">
                <i class="bi bi-geo-alt fs-1 text-body-tertiary"></i>
                <div class="fw-medium mt-3"><?= Langue::t('emplacements.aucun') ?></div>
                <p class="text-body-secondary mb-0" style="font-size:.88rem">
                    <?= Langue::t('emplacements.aucun_detail') ?>
                </p>
            </div>

        <?php else: ?>

            <div class="bg-body border rounded-3">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="border-bottom">
                            <tr>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('emplacements.entrepot') ?></span></th>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('emplacements.reference') ?></span></th>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('emplacements.zone') ?></span></th>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('emplacements.rayon') ?></span></th>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('emplacements.etagere') ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($emplacements as $e): ?>
                            <?php
                            $reference = trim(($e['zone'] ?? '') . '-' . ($e['rayon'] ?? '')
                                . '-' . ($e['etagere'] ?? ''), '-');
                            ?>
                            <tr>
                                <td class="fw-medium" style="font-size:.86rem"><?= Vue::e($e['entrepot'] ?? '') ?></td>
                                <td>
                                    <span class="badge rounded-pill text-bg-light border text-body-secondary"
                                          style="font-size:.68rem"><?= Vue::e($reference) ?></span>
                                </td>
                                <td style="font-size:.82rem"><?= Vue::e($e['zone'] ?? '') ?></td>
                                <td style="font-size:.82rem"><?= Vue::e($e['rayon'] ?? '') ?></td>
                                <td style="font-size:.82rem"><?= Vue::e($e['etagere'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="bg-body border rounded-3">
            <div class="px-3 py-2 border-bottom">
                <span class="text-uppercase text-body-tertiary fw-semibold"
                      style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('emplacements.nouveau') ?></span>
            </div>
            <form method="post" action="/back/emplacements" class="p-3">
                <div class="mb-2">
                    <label class="form-label" style="font-size:.82rem">
                        <?= Langue::t('emplacements.entrepot') ?> *
                    </label>
                    <input type="text" name="entrepot" class="form-control form-control-sm" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label" style="font-size:.82rem"><?= Langue::t('emplacements.zone') ?></label>
                        <input type="text" name="zone" class="form-control form-control-sm" maxlength="10">
                    </div>
                    <div class="col-4">
                        <label class="form-label" style="font-size:.82rem"><?= Langue::t('emplacements.rayon') ?></label>
                        <input type="text" name="rayon" class="form-control form-control-sm" maxlength="10">
                    </div>
                    <div class="col-4">
                        <label class="form-label" style="font-size:.82rem"><?= Langue::t('emplacements.etagere') ?></label>
                        <input type="text" name="etagere" class="form-control form-control-sm" maxlength="10">
                    </div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary w-100"><?= Langue::t('commun.ajouter') ?></button>
            </form>
        </div>

        <div class="border-start border-3 border-secondary-subtle ps-3 py-1 mt-3 text-body-secondary"
             style="font-size:.8rem">
            <?= Langue::t('emplacements.aide') ?>
        </div>
    </div>
</div>
