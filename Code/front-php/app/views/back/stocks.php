<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Les stocks, avec la recherche par code-barre en tete.
 *
 * Le sujet exige que les produits soient "retrouvables tres rapidement" :
 * c'est cette recherche, et c'est pour ca qu'elle occupe toute la largeur en
 * haut de page avec le focus automatique.
 *
 * Variables attendues : $produits, $emplacements, $codeBarre, $introuvable
 */

$produits = $produits ?? [];
$emplacements = $emplacements ?? [];
$codeBarre = $codeBarre ?? '';
$introuvable = $introuvable ?? false;

$couleurs = [
    'en_stock' => 'success',
    'reserve' => 'warning',
    'distribue' => 'secondary',
    'perime' => 'danger',
];

// Une DLC proche merite d'etre signalee : c'est toute la raison d'etre de
// l'association. On calcule le nombre de jours restants pour l'afficher.
$aujourdhui = new DateTimeImmutable('today');

?>

<!-- LA RECHERCHE : l'action principale de l'ecran. -->
<div class="bg-body border rounded-3 p-3 mb-3">
    <label for="cb" class="form-label fw-medium mb-2" style="font-size:.88rem">
        <i class="bi bi-upc-scan text-primary"></i> <?= Langue::t('stocks.recherche') ?>
    </label>

    <!-- method="get" : la recherche se retrouve dans l'adresse, donc la page
         est partageable et rechargeable. Regle generale : GET pour consulter,
         POST pour modifier. -->
    <form method="get" action="/back/stocks">
        <div class="input-group">
            <input type="text" class="form-control" id="cb" name="code_barre"
                   value="<?= Vue::e($codeBarre) ?>"
                   placeholder="<?= Vue::e(Langue::t('stocks.recherche_placeholder')) ?>" autofocus>
            <button type="submit" class="btn btn-primary px-4"><?= Langue::t('stocks.rechercher') ?></button>
            <?php if ($codeBarre !== ''): ?>
                <a href="/back/stocks" class="btn btn-light"><?= Langue::t('commun.reinitialiser') ?></a>
            <?php endif; ?>
        </div>
    </form>

    <small class="text-body-tertiary d-block mt-2" style="font-size:.76rem">
        <?= Langue::t('stocks.recherche_aide') ?>
    </small>
</div>

<?php if ($introuvable): ?>

    <div class="alert alert-warning">
        <i class="bi bi-search"></i>
        <?= Langue::t('stocks.introuvable') ?> <code><?= Vue::e($codeBarre) ?></code>
    </div>

<?php elseif (empty($produits)): ?>

    <div class="bg-body border rounded-3 p-5 text-center">
        <i class="bi bi-boxes fs-1 text-body-tertiary"></i>
        <div class="fw-medium mt-3"><?= Langue::t('stocks.aucun') ?></div>
        <p class="text-body-secondary mb-0" style="font-size:.88rem">
            <?= Langue::t('stocks.aucun_detail') ?>
        </p>
    </div>

<?php else: ?>

    <div class="bg-body border rounded-3">
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
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('stocks.emplacement') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($produits as $p): ?>
                    <?php
                    $statut = $p['statut'] ?? 'en_stock';
                    $couleur = $couleurs[$statut] ?? 'secondary';

                    $dlcTexte = '';
                    $dlcClasse = '';
                    if (!empty($p['dlc'])) {
                        $dlc = new DateTimeImmutable(substr($p['dlc'], 0, 10));
                        $jours = (int) $aujourdhui->diff($dlc)->format('%r%a');
                        $dlcTexte = $dlc->format('d/m/Y');

                        // Rouge sous 3 jours, orange sous 7 : on veut que
                        // l'oeil tombe dessus sans avoir a lire les dates.
                        if ($jours < 0) {
                            $dlcClasse = 'text-danger-emphasis fw-medium';
                        } elseif ($jours <= 3) {
                            $dlcClasse = 'text-danger-emphasis fw-medium';
                        } elseif ($jours <= 7) {
                            $dlcClasse = 'text-warning-emphasis';
                        }
                    }

                    $emplacement = !empty($p['emplacement_id'])
                        ? ($emplacements[$p['emplacement_id']] ?? '')
                        : '';
                    ?>
                    <tr>
                        <td><code class="text-body-secondary" style="font-size:.78rem"><?= Vue::e($p['code_barre'] ?? '') ?></code></td>
                        <td class="fw-medium" style="font-size:.86rem"><?= Vue::e($p['libelle'] ?? '') ?></td>
                        <td class="<?= $dlcClasse ?>" style="font-size:.82rem"><?= Vue::e($dlcTexte) ?></td>
                        <td class="text-center" style="font-size:.84rem"><?= (int) ($p['quantite'] ?? 0) ?></td>
                        <td class="text-body-secondary" style="font-size:.8rem">
                            <?= $emplacement !== '' ? Vue::e($emplacement) : '<span class="text-body-tertiary">&mdash;</span>' ?>
                        </td>
                        <td>
                            <span class="badge rounded-pill text-bg-<?= $couleur ?>" style="font-size:.66rem">
                                <?= Langue::t('stocks.statut_' . $statut) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>
