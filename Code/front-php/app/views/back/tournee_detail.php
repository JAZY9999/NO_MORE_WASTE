<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Detail d'une tournee : ses arrets dans l'ordre de passage.
 *
 * Pour chaque arret, deux etats possibles :
 *   - pas encore livre -> un formulaire de cloture (choix des produits remis)
 *   - livre            -> le lien vers le RECAPITULATIF PDF exige par le sujet
 *
 * Variables attendues : $tournee, $etapes, $beneficiaires, $produits,
 * $chauffeur, $date, $livrees, $total
 */

$tournee = $tournee ?? [];
$etapes = $etapes ?? [];
$beneficiaires = $beneficiaires ?? [];
$produits = $produits ?? [];
$chauffeur = $chauffeur ?? '';
$livrees = $livrees ?? 0;
$total = $total ?? 0;

$id = (int) ($tournee['id'] ?? 0);
$statut = $tournee['statut'] ?? 'planifiee';
$statuts = ['planifiee', 'en_cours', 'terminee', 'annulee'];

$pourcent = $total > 0 ? (int) (100 * $livrees / $total) : 0;

?>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('tournees.chauffeur') ?></div>
            <div class="fw-medium mt-1" style="font-size:.9rem">
                <?= $chauffeur !== '' ? Vue::e($chauffeur) : '<span class="text-body-tertiary">&mdash;</span>' ?>
            </div>
            <small class="text-body-tertiary" style="font-size:.74rem">
                <?= Langue::t('tournees.chauffeur_regle') ?>
            </small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('tournees.progression') ?></div>
            <div class="fs-5 fw-semibold mt-1"><?= $livrees ?> / <?= $total ?></div>
            <div class="progress mt-2" style="height:5px">
                <div class="progress-bar bg-success" style="width:<?= $pourcent ?>%"></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></div>
            <form method="post" action="/back/tournees/<?= $id ?>" class="mt-2">
                <input type="hidden" name="action" value="changer_statut">
                <div class="input-group input-group-sm">
                    <select name="statut" class="form-select">
                        <?php foreach ($statuts as $s): ?>
                            <option value="<?= $s ?>" <?= $s === $statut ? 'selected' : '' ?>>
                                <?= Langue::t('tournees.statut_' . $s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary"><?= Langue::t('commun.enregistrer') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="text-uppercase text-body-tertiary fw-semibold mb-2"
     style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('tournees.arrets') ?></div>

<?php if (empty($etapes)): ?>

    <div class="bg-body border rounded-3 p-4 text-center text-body-secondary" style="font-size:.86rem">
        <?= Langue::t('tournees.aucun_arret') ?>
    </div>

<?php else: ?>

    <?php foreach ($etapes as $e): ?>
        <?php
        $etapeId = (int) ($e['id'] ?? 0);
        $estLivre = ($e['statut'] ?? '') === 'livre';

        $b = $beneficiaires[$e['beneficiaire_id'] ?? 0] ?? [];
        $nomBeneficiaire = $b['nom'] ?? '';
        // La cle est construite a partir de la valeur exacte de la base
        // (association_caritative / particulier_detresse), comme sur l'ecran
        // des beneficiaires. Un ternaire avec des cles abregees marchait,
        // mais faisait vivre DEUX conventions pour la meme information --
        // et l'ecran des beneficiaires affichait alors la cle brute.
        $typeBeneficiaire = Langue::t('beneficiaires.type_' . ($b['type'] ?? ''));
        $adresse = $b['adresse'] ?? '';

        // L'API renvoie deja "HH:MM" : rien a decouper ici.
        $prevue = $e['heure_prevue'] ?? '';
        $reelle = $e['heure_reelle'] ?? '';
        ?>

        <div class="bg-body border rounded-3 mb-2">
            <div class="d-flex gap-3 align-items-center p-3 flex-wrap">
                <span class="d-flex align-items-center justify-content-center rounded-circle
                             <?= $estLivre ? 'bg-success' : 'bg-secondary' ?> text-white fw-semibold flex-shrink-0"
                      style="width:26px; height:26px; font-size:.76rem"><?= (int) ($e['ordre'] ?? 0) ?></span>

                <div class="flex-grow-1 lh-sm" style="min-width:200px">
                    <div class="fw-medium" style="font-size:.88rem"><?= Vue::e($nomBeneficiaire) ?></div>
                    <small class="text-body-tertiary" style="font-size:.76rem">
                        <?= Vue::e($typeBeneficiaire) ?><?= $adresse !== '' ? ' · ' . Vue::e($adresse) : '' ?>
                    </small>
                    <div class="d-flex gap-3 mt-2 flex-wrap align-items-center text-body-secondary"
                         style="font-size:.78rem">
                        <?php if ($prevue !== ''): ?>
                            <span><?= Langue::t('tournees.heure_prevue') ?> <?= Vue::e($prevue) ?></span>
                        <?php endif; ?>
                        <?php if ($reelle !== ''): ?>
                            <span><?= Langue::t('tournees.heure_reelle') ?> <?= Vue::e($reelle) ?></span>
                        <?php endif; ?>
                        <span class="badge rounded-pill text-bg-<?= $estLivre ? 'success' : 'secondary' ?>"
                              style="font-size:.64rem"><?= Langue::t('tournees.etape_' . ($e['statut'] ?? 'a_faire')) ?></span>
                    </div>
                </div>

                <div>
                    <?php if ($estLivre && !empty($e['livraison_id'])): ?>
                        <!-- LE PDF exige par le sujet. On passe par le front et
                             non par /api/... : le navigateur n'enverrait pas le
                             jeton, qui vit dans la session PHP.
                             target="_blank" : on ne quitte pas la tournee. -->
                        <a href="/back/livraisons/<?= (int) $e['livraison_id'] ?>/pdf"
                           target="_blank" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-file-earmark-pdf"></i> <?= Langue::t('tournees.pdf') ?>
                        </a>
                    <?php elseif (!$estLivre): ?>
                        <button class="btn btn-sm btn-primary" type="button"
                                data-bs-toggle="collapse" data-bs-target="#cloture<?= $etapeId ?>">
                            <?= Langue::t('tournees.cloturer') ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$estLivre): ?>
                <!-- Le formulaire de cloture est replie par defaut : sur une
                     tournee de dix arrets, dix formulaires deplies rendraient
                     la page illisible. -->
                <div class="collapse border-top" id="cloture<?= $etapeId ?>">
                    <form method="post" action="/back/tournees/<?= $id ?>" class="p-3 bg-body-tertiary">
                        <input type="hidden" name="action" value="cloturer">
                        <input type="hidden" name="etape_id" value="<?= $etapeId ?>">

                        <div class="fw-medium mb-2" style="font-size:.86rem">
                            <?= Langue::t('tournees.produits_remis') ?>
                        </div>

                        <?php if (empty($produits)): ?>
                            <div class="text-body-secondary" style="font-size:.84rem">
                                <?= Langue::t('tournees.aucun_produit_stock') ?>
                            </div>
                        <?php else: ?>
                            <?php // Trois lignes : suffisant pour une livraison courante,
                                  // et on peut clôturer plusieurs fois si besoin. ?>
                            <?php for ($ligne = 0; $ligne < 3; $ligne++): ?>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-8">
                                        <select name="produit_id[]" class="form-select form-select-sm">
                                            <option value="0">&mdash;</option>
                                            <?php foreach ($produits as $p): ?>
                                                <option value="<?= (int) $p['id'] ?>">
                                                    <?= Vue::e(($p['libelle'] ?? '') . ' (' . ($p['code_barre'] ?? '') . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="number" name="quantite[]" class="form-control form-control-sm"
                                               min="0" value="0"
                                               placeholder="<?= Vue::e(Langue::t('collectes.quantite')) ?>">
                                    </div>
                                </div>
                            <?php endfor; ?>

                            <button type="submit" class="btn btn-sm btn-primary">
                                <?= Langue::t('tournees.cloturer_generer') ?>
                            </button>

                            <small class="text-body-tertiary d-block mt-2" style="font-size:.76rem">
                                <?= Langue::t('tournees.cloture_aide') ?>
                            </small>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>

    <?php endforeach; ?>

<?php endif; ?>
