<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Back-office : gestion des traductions.
 *
 * Variables attendues :
 *   $langues      : les langues du site (venant de l'API)
 *   $parCle       : ['nav.accueil' => ['fr' => ['id'=>1,'valeur'=>'Accueil'], 'en' => [...]]]
 *   $langueFiltre : code de langue filtre, ou ''
 *   $recherche    : texte recherche, ou ''
 *   $total        : nombre de cles affichees
 */

$langues = $langues ?? [];
$parCle = $parCle ?? [];
$langueFiltre = $langueFiltre ?? '';
$recherche = $recherche ?? '';
$total = $total ?? 0;

// Les colonnes du tableau : toutes les langues, ou seulement celle filtree.
$colonnes = $langues;
if ($langueFiltre !== '') {
    $colonnes = array_values(array_filter(
        $langues,
        fn($l) => ($l['code'] ?? '') === $langueFiltre
    ));
}

?>


<!-- ===================== SYNCHRONISATION ===================== -->
<div class="bg-body border rounded-3 p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="lh-sm" style="max-width:520px">
            <div class="fw-semibold" style="font-size:.9rem">
                <?= Langue::t('traductions.synchro') ?>
            </div>
            <small class="text-body-secondary">
                <?= Langue::t('traductions.synchro_aide') ?>
            </small>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <!-- Base -> fichiers : l'action normale apres une modification. -->
            <form method="post" action="/back/traductions" class="d-inline">
                <input type="hidden" name="action" value="exporter">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-download"></i> <?= Langue::t('traductions.exporter') ?>
                </button>
            </form>

            <!-- Fichiers -> base : plus rare, sert a charger un lot prepare. -->
            <form method="post" action="/back/traductions" class="d-inline"
                  onsubmit="return confirm('<?= Langue::t('traductions.importer_confirm') ?>');">
                <input type="hidden" name="action" value="importer">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-upload"></i> <?= Langue::t('traductions.importer') ?>
                </button>
            </form>
        </div>
    </div>

    <div class="border-start border-3 border-warning-subtle ps-3 mt-3 text-body-secondary"
         style="font-size:.8rem">
        <i class="bi bi-info-circle"></i>
        <?= Langue::t('traductions.avertissement') ?>
    </div>
</div>

<!-- ===================== FILTRES ===================== -->
<form method="get" action="/back/traductions" class="bg-body border rounded-3 p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="q" class="form-label text-body-secondary mb-1" style="font-size:.78rem">
                <?= Langue::t('traductions.recherche') ?>
            </label>
            <input type="text" class="form-control form-control-sm" id="q" name="q"
                   value="<?= Vue::e($recherche) ?>" placeholder="nav.accueil, Connexion...">
        </div>
        <div class="col-6 col-md-3">
            <label for="langue" class="form-label text-body-secondary mb-1" style="font-size:.78rem">
                <?= Langue::t('app.langue') ?>
            </label>
            <select id="langue" name="langue" class="form-select form-select-sm">
                <option value="">—</option>
                <?php foreach ($langues as $l): ?>
                    <option value="<?= Vue::e($l['code'] ?? '') ?>"
                        <?= ($l['code'] ?? '') === $langueFiltre ? 'selected' : '' ?>>
                        <?= Vue::e($l['libelle'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <button type="submit" class="btn btn-sm btn-secondary w-100">
                <i class="bi bi-funnel"></i> <?= Langue::t('traductions.filtrer') ?>
            </button>
        </div>
        <div class="col-md-3 text-md-end">
            <span class="badge rounded-pill text-bg-secondary">
                <?= $total ?> <?= Langue::t('traductions.cles') ?>
            </span>
        </div>
    </div>
</form>

<!-- ===================== AJOUT ===================== -->
<div class="accordion mb-3" id="accordionAjout">
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#zoneAjout">
                <i class="bi bi-plus-lg me-2"></i> <?= Langue::t('traductions.ajouter') ?>
            </button>
        </h2>
        <div id="zoneAjout" class="accordion-collapse collapse" data-bs-parent="#accordionAjout">
            <div class="accordion-body">

                <form method="post" action="/back/traductions" class="row g-2 align-items-end mb-4">
                    <input type="hidden" name="action" value="creer">
                    <div class="col-md-3">
                        <label class="form-label" style="font-size:.8rem"><?= Langue::t('traductions.cle') ?></label>
                        <input type="text" name="cle" class="form-control form-control-sm"
                               placeholder="nav.accueil" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" style="font-size:.8rem"><?= Langue::t('traductions.valeur') ?></label>
                        <input type="text" name="valeur" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" style="font-size:.8rem"><?= Langue::t('app.langue') ?></label>
                        <select name="code_langue" class="form-select form-select-sm" required>
                            <?php foreach ($langues as $l): ?>
                                <option value="<?= Vue::e($l['code'] ?? '') ?>">
                                    <?= Vue::e($l['libelle'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <?= Langue::t('traductions.ajouter_court') ?>
                        </button>
                    </div>
                </form>

                <hr>

                <!-- Gestion des langues elles-memes -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-uppercase text-body-secondary fw-semibold mb-2"
                             style="font-size:.68rem; letter-spacing:.08em">
                            <?= Langue::t('traductions.ajouter_langue') ?>
                        </div>
                        <form method="post" action="/back/traductions" class="row g-2 align-items-end">
                            <input type="hidden" name="action" value="creer_langue">
                            <div class="col-4">
                                <label class="form-label" style="font-size:.8rem">Code</label>
                                <input type="text" name="code" class="form-control form-control-sm"
                                       placeholder="es" maxlength="5" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label" style="font-size:.8rem"><?= Langue::t('traductions.nom') ?></label>
                                <input type="text" name="libelle" class="form-control form-control-sm"
                                       placeholder="Espanol" required>
                            </div>
                            <div class="col-3">
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                    <?= Langue::t('traductions.ajouter_court') ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="col-md-6">
                        <div class="text-uppercase text-body-secondary fw-semibold mb-2"
                             style="font-size:.68rem; letter-spacing:.08em">
                            <?= Langue::t('traductions.langues_actives') ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($langues as $l): ?>
                                <?php $code = $l['code'] ?? ''; ?>
                                <span class="badge rounded-pill text-bg-light border text-body d-flex align-items-center gap-1">
                                    <?= Vue::e($l['libelle'] ?? '') ?>
                                    <small class="text-body-tertiary">(<?= Vue::e($code) ?>)</small>
                                    <?php if ($code !== 'fr'): ?>
                                        <form method="post" action="/back/traductions" class="d-inline"
                                              onsubmit="return confirm('<?= Langue::t('traductions.supprimer_langue_confirm') ?>');">
                                            <input type="hidden" name="action" value="supprimer_langue">
                                            <input type="hidden" name="code" value="<?= Vue::e($code) ?>">
                                            <button type="submit" class="btn btn-link btn-sm p-0 text-danger"
                                                    style="font-size:.75rem">&times;</button>
                                        </form>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-body-tertiary d-block mt-2" style="font-size:.74rem">
                            <?= Langue::t('traductions.fr_protege') ?>
                        </small>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ===================== TABLEAU ===================== -->
<?php if (empty($parCle)): ?>

    <div class="alert alert-info mb-0">
        <i class="bi bi-info-circle"></i> <?= Langue::t('traductions.aucune') ?>
    </div>

<?php else: ?>

    <div class="bg-body border rounded-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="border-bottom">
                    <tr>
                        <th class="text-uppercase text-body-secondary fw-semibold"
                            style="font-size:.66rem; letter-spacing:.09em">
                            <?= Langue::t('traductions.cle') ?>
                        </th>
                        <?php foreach ($colonnes as $l): ?>
                            <th class="text-uppercase text-body-secondary fw-semibold"
                                style="font-size:.66rem; letter-spacing:.09em">
                                <?= Vue::e($l['libelle'] ?? '') ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($parCle as $cle => $valeurs): ?>
                    <tr>
                        <td>
                            <code class="text-body-secondary" style="font-size:.78rem"><?= Vue::e($cle) ?></code>
                        </td>

                        <?php foreach ($colonnes as $l): ?>
                            <?php
                                $code = $l['code'] ?? '';
                                $entree = $valeurs[$code] ?? null;
                            ?>
                            <td>
                                <?php if ($entree === null): ?>
                                    <!-- Traduction manquante : on propose de la creer
                                         directement, sans quitter la page. -->
                                    <form method="post" action="/back/traductions"
                                          class="d-flex gap-1">
                                        <input type="hidden" name="action" value="creer">
                                        <input type="hidden" name="cle" value="<?= Vue::e($cle) ?>">
                                        <input type="hidden" name="code_langue" value="<?= Vue::e($code) ?>">
                                        <input type="text" name="valeur"
                                               class="form-control form-control-sm border-warning"
                                               placeholder="<?= Langue::t('traductions.manquant') ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning"
                                                title="<?= Langue::t('traductions.ajouter_court') ?>">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Modification en place : un champ + un bouton.
                                         Pas besoin d'ouvrir une autre page. -->
                                    <form method="post" action="/back/traductions"
                                          class="d-flex gap-1">
                                        <input type="hidden" name="action" value="modifier">
                                        <input type="hidden" name="id" value="<?= (int) $entree['id'] ?>">
                                        <input type="hidden" name="cle" value="<?= Vue::e($cle) ?>">
                                        <input type="hidden" name="code_langue" value="<?= Vue::e($code) ?>">
                                        <input type="text" name="valeur" class="form-control form-control-sm"
                                               value="<?= Vue::e($entree['valeur']) ?>">
                                        <button type="submit" class="btn btn-sm btn-light"
                                                title="<?= Langue::t('traductions.enregistrer') ?>">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>
