<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * La fiche d'un commercant partenaire.
 *
 * L'ordre repond aux questions dans l'ordre ou elles se posent :
 *   1. est-il a jour de son adhesion (c'est ce qui conditionne tout)
 *   2. son compte de connexion est-il rattache
 *   3. ses coordonnees
 *   4. son historique de collectes
 *
 * Variables attendues : $commercant, $adhesions, $collectes, $comptes
 */

$commercant = $commercant ?? [];
$adhesions = $adhesions ?? [];
$collectes = $collectes ?? [];
$comptes = $comptes ?? [];

$id = (int) ($commercant['id'] ?? 0);
$compteActuel = (int) ($commercant['utilisateur_id'] ?? 0);

// L'adhesion la plus recente : c'est elle qui dit s'il est en regle.
$courante = null;
foreach ($adhesions as $a) {
    if ($courante === null || ($a['date_fin'] ?? '') > ($courante['date_fin'] ?? '')) {
        $courante = $a;
    }
}

$couleursAdhesion = [
    'active' => 'success',
    'expiree' => 'danger',
    'resiliee' => 'secondary',
    'en_attente' => 'warning',
];
$couleursCollecte = [
    'demandee' => 'secondary',
    'planifiee' => 'info',
    'realisee' => 'success',
    'annulee' => 'danger',
];

// Petit raccourci pour les champs du formulaire.
$val = function (string $champ) use ($commercant): string {
    return Vue::e($commercant[$champ] ?? '');
};

?>

<div class="row g-3 mb-3">

    <!-- 1. Est-il a jour ? -->
    <div class="col-md-6">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.mon_adhesion') ?></div>

            <?php if ($courante === null): ?>

                <div class="fw-medium mt-2" style="font-size:.9rem">
                    <?= Langue::t('commercants.aucune_adhesion') ?>
                </div>
                <small class="text-body-tertiary"><?= Langue::t('commercants.aucune_adhesion_aide') ?></small>

            <?php else: ?>
                <?php
                $statut = $courante['statut'] ?? 'active';
                $jours = (int) ($courante['jours_restants'] ?? 0);
                $fin = !empty($courante['date_fin']) ? date('d/m/Y', strtotime($courante['date_fin'])) : '';
                ?>
                <div class="d-flex align-items-baseline gap-2 mt-2 flex-wrap">
                    <span class="badge rounded-pill text-bg-<?= $couleursAdhesion[$statut] ?? 'secondary' ?>">
                        <?= Langue::t('adhesions.statut_' . $statut) ?>
                    </span>
                    <span class="text-body-secondary" style="font-size:.86rem">
                        <?= Langue::t('espace.jusqu_au') ?> <?= Vue::e($fin) ?>
                    </span>
                </div>
                <small class="d-block mt-1 <?= $jours < 0 ? 'text-danger-emphasis' : 'text-body-tertiary' ?>"
                       style="font-size:.78rem">
                    <?php if ($jours < 0): ?>
                        <?= Langue::t('adhesions.depuis') ?> <?= abs($jours) ?> <?= Langue::t('espace.jours') ?>
                    <?php else: ?>
                        <?= Langue::t('adhesions.dans') ?> <?= $jours ?> <?= Langue::t('espace.jours') ?>
                    <?php endif; ?>
                </small>
                <a href="/back/adhesions/<?= (int) $courante['id'] ?>"
                   class="btn btn-sm btn-light mt-2"><?= Langue::t('commercants.voir_adhesion') ?></a>
            <?php endif; ?>

            <!-- Creer une adhesion : c'est ici qu'on renouvelle un partenaire. -->
            <form method="post" action="/back/commercants/<?= $id ?>" class="mt-3 pt-3 border-top">
                <input type="hidden" name="action" value="creer_adhesion">
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label" style="font-size:.76rem"><?= Langue::t('commercants.date_debut') ?></label>
                        <input type="date" name="date_debut" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" style="font-size:.76rem"><?= Langue::t('commercants.date_fin') ?></label>
                        <input type="date" name="date_fin" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" style="font-size:.76rem"><?= Langue::t('commercants.montant') ?></label>
                        <input type="number" name="montant_cotisation" step="0.01" min="0"
                               class="form-control form-control-sm">
                    </div>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-primary mt-2">
                    <?= Langue::t('commercants.nouvelle_adhesion') ?>
                </button>
            </form>
        </div>
    </div>

    <!-- 2. Son compte de connexion -->
    <div class="col-md-6">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commercants.compte') ?></div>

            <form method="post" action="/back/commercants/<?= $id ?>" class="mt-2">
                <input type="hidden" name="action" value="rattacher">
                <div class="input-group input-group-sm">
                    <!-- Seuls les comptes ADHERENT sont proposes : le role
                         decide de l'espace auquel on accede. -->
                    <select name="utilisateur_id" class="form-select">
                        <option value="0"><?= Langue::t('commercants.aucun_compte') ?></option>
                        <?php foreach ($comptes as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"
                                <?= (int) $u['id'] === $compteActuel ? 'selected' : '' ?>>
                                <?= Vue::e($u['email'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary"><?= Langue::t('commun.enregistrer') ?></button>
                </div>
            </form>

            <small class="text-body-tertiary d-block mt-2" style="font-size:.76rem">
                <?= Langue::t('commercants.compte_aide') ?>
            </small>

            <?php if ($compteActuel === 0): ?>
                <div class="border-start border-3 border-warning ps-3 py-1 mt-3 text-body-secondary"
                     style="font-size:.78rem">
                    <?= Langue::t('commercants.sans_compte_consequence') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- 3. Ses coordonnees -->
<div class="bg-body border rounded-3 p-3 mb-3">
    <div class="text-uppercase text-body-tertiary fw-semibold mb-3"
         style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commercants.coordonnees') ?></div>

    <form method="post" action="/back/commercants/<?= $id ?>">
        <input type="hidden" name="action" value="modifier">

        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label" style="font-size:.78rem"><?= Langue::t('commercants.raison_sociale') ?></label>
                <input type="text" name="raison_sociale" class="form-control form-control-sm"
                       value="<?= $val('raison_sociale') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" style="font-size:.78rem"><?= Langue::t('commercants.siret') ?></label>
                <input type="text" name="siret" class="form-control form-control-sm" value="<?= $val('siret') ?>">
            </div>
        </div>

        <div class="row g-2 mt-0">
            <div class="col-md-6">
                <label class="form-label" style="font-size:.78rem"><?= Langue::t('beneficiaires.adresse') ?></label>
                <input type="text" name="adresse" class="form-control form-control-sm" value="<?= $val('adresse') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.78rem"><?= Langue::t('beneficiaires.ville') ?></label>
                <input type="text" name="ville" class="form-control form-control-sm" value="<?= $val('ville') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.78rem"><?= Langue::t('commercants.pays') ?></label>
                <input type="text" name="pays" class="form-control form-control-sm" value="<?= $val('pays') ?>">
            </div>
        </div>

        <div class="row g-2 mt-0">
            <div class="col-md-4">
                <label class="form-label" style="font-size:.78rem"><?= Langue::t('candidature.email') ?></label>
                <input type="email" name="email" class="form-control form-control-sm" value="<?= $val('email') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-size:.78rem"><?= Langue::t('beneficiaires.telephone') ?></label>
                <input type="tel" name="telephone" class="form-control form-control-sm" value="<?= $val('telephone') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-size:.78rem"><?= Langue::t('commercants.contact_nom') ?></label>
                <input type="text" name="contact_nom" class="form-control form-control-sm" value="<?= $val('contact_nom') ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-sm btn-primary mt-3"><?= Langue::t('commun.enregistrer') ?></button>

        <small class="text-body-tertiary d-block mt-2" style="font-size:.76rem">
            <?= Langue::t('commercants.email_sert_aux_rappels') ?>
        </small>
    </form>
</div>

<!-- 4. Son historique de collectes -->
<div class="text-uppercase text-body-tertiary fw-semibold mb-2"
     style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.mes_collectes') ?></div>

<?php if (empty($collectes)): ?>

    <div class="bg-body border rounded-3 p-4 text-body-secondary" style="font-size:.86rem">
        <?= Langue::t('commercants.aucune_collecte') ?>
    </div>

<?php else: ?>

    <div class="bg-body border rounded-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="border-bottom">
                    <tr>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('espace.date_prevue') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></span></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($collectes as $c): ?>
                    <?php
                    $statut = $c['statut'] ?? 'demandee';
                    $prevue = !empty($c['date_prevue']) ? date('d/m/Y', strtotime($c['date_prevue'])) : '';
                    ?>
                    <tr>
                        <td style="font-size:.86rem"><?= Vue::e($prevue) ?></td>
                        <td>
                            <span class="badge rounded-pill text-bg-<?= $couleursCollecte[$statut] ?? 'secondary' ?>"
                                  style="font-size:.66rem"><?= Langue::t('collectes.statut_' . $statut) ?></span>
                        </td>
                        <td class="text-end">
                            <a href="/back/collectes/<?= (int) $c['id'] ?>" class="btn btn-sm btn-light">
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
