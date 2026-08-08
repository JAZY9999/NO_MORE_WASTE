<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Detail d'une collecte, avec le scan des produits ramasses.
 *
 * Le champ de scan est en TETE de page et porte le focus automatique : c'est
 * le geste qu'un benevole repete pendant toute la collecte. Le reste de
 * l'ecran est de la consultation.
 *
 * Variables attendues : $collecte, $produits, $emplacements, $source, $date
 */

$collecte = $collecte ?? [];
$produits = $produits ?? [];
$emplacements = $emplacements ?? [];
$source = $source ?? '';
$date = $date ?? '';

$id = (int) ($collecte['id'] ?? 0);
$statut = $collecte['statut'] ?? 'demandee';

$statuts = ['demandee', 'planifiee', 'realisee', 'annulee'];

?>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('collectes.source') ?></div>
            <div class="fw-medium mt-1" style="font-size:.9rem"><?= Vue::e($source) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></div>
            <!-- Changer le statut se fait ici : passer a "realisee" remplit
                 la date de realisation automatiquement cote API. -->
            <form method="post" action="/back/collectes/<?= $id ?>" class="mt-2">
                <input type="hidden" name="action" value="changer_statut">
                <div class="input-group input-group-sm">
                    <select name="statut" class="form-select">
                        <?php foreach ($statuts as $s): ?>
                            <option value="<?= $s ?>" <?= $s === $statut ? 'selected' : '' ?>>
                                <?= Langue::t('collectes.statut_' . $s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary"><?= Langue::t('commun.enregistrer') ?></button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('collectes.articles') ?></div>
            <div class="fs-4 fw-semibold mt-1"><?= count($produits) ?></div>
        </div>
    </div>
</div>

<!-- LE SCAN : l'action principale de cet ecran. -->
<div class="bg-body border rounded-3 p-3 mb-3">
    <label for="cb" class="form-label fw-medium mb-2" style="font-size:.88rem">
        <i class="bi bi-upc-scan text-primary"></i> <?= Langue::t('collectes.scanner') ?>
    </label>

    <form method="post" action="/back/collectes/<?= $id ?>">
        <input type="hidden" name="action" value="scanner">

        <div class="row g-2">
            <div class="col-md-4">
                <!-- autofocus : la douchette se comporte comme un clavier,
                     le champ doit donc etre pret a recevoir sans clic. -->
                <input type="text" class="form-control" id="cb" name="code_barre"
                       placeholder="<?= Vue::e(Langue::t('collectes.code_barre')) ?>" autofocus required>
            </div>
            <div class="col-md-4">
                <input type="text" class="form-control" name="libelle"
                       placeholder="<?= Vue::e(Langue::t('collectes.libelle')) ?>" required>
            </div>
            <div class="col-6 col-md-2">
                <input type="number" class="form-control" name="quantite" min="1" value="1"
                       placeholder="<?= Vue::e(Langue::t('collectes.quantite')) ?>">
            </div>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-primary w-100"><?= Langue::t('commun.ajouter') ?></button>
            </div>

            <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" name="categorie"
                       placeholder="<?= Vue::e(Langue::t('collectes.categorie')) ?>">
            </div>
            <div class="col-md-4">
                <input type="date" class="form-control form-control-sm" name="dlc"
                       title="<?= Vue::e(Langue::t('collectes.dlc')) ?>">
            </div>
            <div class="col-md-4">
                <select name="emplacement_id" class="form-select form-select-sm">
                    <option value="0"><?= Langue::t('collectes.sans_emplacement') ?></option>
                    <?php foreach ($emplacements as $e): ?>
                        <option value="<?= (int) $e['id'] ?>">
                            <?= Vue::e(($e['entrepot'] ?? '') . ' — ' . ($e['zone'] ?? '')
                                . '-' . ($e['rayon'] ?? '') . '-' . ($e['etagere'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

    <small class="text-body-tertiary d-block mt-2" style="font-size:.76rem">
        <?= Langue::t('collectes.scan_aide') ?>
    </small>
</div>

<!-- LES PRODUITS DEJA RAMASSES -->
<div class="bg-body border rounded-3">
    <div class="px-3 py-2 border-bottom">
        <span class="text-uppercase text-body-tertiary fw-semibold"
              style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('collectes.articles_collecte') ?></span>
    </div>

    <?php if (empty($produits)): ?>
        <div class="p-4 text-center text-body-secondary" style="font-size:.86rem">
            <?= Langue::t('collectes.aucun_article') ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="border-bottom">
                    <tr>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('collectes.code_barre') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('collectes.produit') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('collectes.dlc') ?></span></th>
                        <th class="text-center"><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('collectes.quantite') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($produits as $p): ?>
                    <?php $dlc = !empty($p['dlc']) ? date('d/m/Y', strtotime($p['dlc'])) : ''; ?>
                    <tr>
                        <td><code class="text-body-secondary" style="font-size:.78rem"><?= Vue::e($p['code_barre'] ?? '') ?></code></td>
                        <td class="fw-medium" style="font-size:.86rem"><?= Vue::e($p['libelle'] ?? '') ?></td>
                        <td style="font-size:.82rem"><?= Vue::e($dlc) ?></td>
                        <td class="text-center" style="font-size:.84rem"><?= (int) ($p['quantite'] ?? 0) ?></td>
                        <td>
                            <span class="badge rounded-pill text-bg-light border text-body-secondary"
                                  style="font-size:.66rem"><?= Vue::e($p['statut'] ?? '') ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
