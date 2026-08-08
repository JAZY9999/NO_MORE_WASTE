<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Creation d'un commercant partenaire.
 *
 * Un seul champ obligatoire : la raison sociale. C'est aussi le seul que la
 * base exige. Tout le reste se complete plus tard depuis la fiche -- on
 * enregistre souvent un partenaire au telephone, avec trois informations.
 *
 * Variables attendues : $comptes, $saisie
 */

$comptes = $comptes ?? [];
$saisie = $saisie ?? [];

// Reaffiche la saisie apres une erreur, pour ne pas retaper le formulaire.
$val = function (string $champ) use ($saisie): string {
    return Vue::e($saisie[$champ] ?? '');
};

?>

<div class="bg-body border rounded-3 p-4" style="max-width:820px">

    <form method="post" action="/back/commercants">

        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label" style="font-size:.82rem">
                    <?= Langue::t('commercants.raison_sociale') ?> *
                </label>
                <input type="text" name="raison_sociale" class="form-control" required
                       value="<?= $val('raison_sociale') ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label" style="font-size:.82rem"><?= Langue::t('commercants.siret') ?></label>
                <input type="text" name="siret" class="form-control" value="<?= $val('siret') ?>">
            </div>
        </div>

        <div class="row g-3 mt-0">
            <div class="col-md-6">
                <label class="form-label" style="font-size:.82rem"><?= Langue::t('beneficiaires.adresse') ?></label>
                <input type="text" name="adresse" class="form-control" value="<?= $val('adresse') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.82rem"><?= Langue::t('beneficiaires.ville') ?></label>
                <input type="text" name="ville" class="form-control" value="<?= $val('ville') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.82rem"><?= Langue::t('commercants.pays') ?></label>
                <input type="text" name="pays" class="form-control" value="<?= $val('pays') ?>">
            </div>
        </div>

        <div class="row g-3 mt-0">
            <div class="col-md-4">
                <label class="form-label" style="font-size:.82rem"><?= Langue::t('candidature.email') ?></label>
                <input type="email" name="email" class="form-control" value="<?= $val('email') ?>">
                <small class="text-body-tertiary" style="font-size:.74rem">
                    <?= Langue::t('commercants.email_sert_aux_rappels') ?>
                </small>
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-size:.82rem"><?= Langue::t('beneficiaires.telephone') ?></label>
                <input type="tel" name="telephone" class="form-control" value="<?= $val('telephone') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-size:.82rem"><?= Langue::t('commercants.contact_nom') ?></label>
                <input type="text" name="contact_nom" class="form-control" value="<?= $val('contact_nom') ?>">
            </div>
        </div>

        <hr class="my-4">

        <div style="max-width:420px">
            <label class="form-label" style="font-size:.82rem"><?= Langue::t('commercants.compte') ?></label>
            <select name="utilisateur_id" class="form-select">
                <option value="0"><?= Langue::t('commercants.aucun_compte') ?></option>
                <?php foreach ($comptes as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"
                        <?= (int) $u['id'] === (int) ($saisie['utilisateur_id'] ?? 0) ? 'selected' : '' ?>>
                        <?= Vue::e($u['email'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-body-tertiary d-block mt-1" style="font-size:.76rem">
                <?= Langue::t('commercants.compte_aide') ?>
                <?= Langue::t('commercants.compte_plus_tard') ?>
            </small>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary"><?= Langue::t('commun.creer') ?></button>
            <a href="/back/commercants" class="btn btn-light ms-2"><?= Langue::t('commun.retour') ?></a>
        </div>

        <div class="text-body-tertiary mt-3" style="font-size:.78rem">
            <?= Langue::t('candidature.champs_obligatoires') ?>
        </div>

    </form>

</div>
