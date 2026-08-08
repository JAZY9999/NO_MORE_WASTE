<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Le formulaire de candidature benevole. Page publique.
 *
 * Deux champs obligatoires seulement : nom et prenom. C'est volontaire --
 * chaque champ obligatoire supplementaire fait abandonner des candidats, et
 * l'association peut demander le reste ensuite. Le sujet dit "chacun peut
 * s'inscrire" ; la page doit etre facile a franchir.
 *
 * Variables attendues : $estConnecte, $saisie
 */

$estConnecte = $estConnecte ?? false;
$saisie = $saisie ?? [];

// Petit raccourci pour reafficher ce qui avait ete tape apres une erreur.
$valeur = function (string $champ) use ($saisie): string {
    return Vue::e($saisie[$champ] ?? '');
};

?>

<div style="max-width:620px">

    <h1 class="display-6 fw-bold mb-3"><?= Langue::t('candidature.titre') ?></h1>
    <p class="fs-5 text-body-secondary mb-4"><?= Langue::t('candidature.accroche') ?></p>

    <div class="border-start border-3 border-primary ps-4 py-1 mb-4 text-body-secondary"
         style="font-size:.9rem">
        <?= Langue::t('candidature.conditions') ?>
    </div>

    <?php if (!$estConnecte): ?>
        <!-- Un visiteur connecte voit sa fiche rattachee a son compte. On le
             signale ici : c'est la difference entre un espace benevole qui
             marche tout de suite et une fiche orpheline. -->
        <div class="alert alert-light border" style="font-size:.9rem">
            <?= Langue::t('candidature.connectez_vous') ?>
            <a href="/connexion" class="text-decoration-none"><?= Langue::t('nav.connexion') ?></a>
        </div>
    <?php endif; ?>

    <form method="post" action="/benevoles/candidature">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" style="font-size:.88rem"><?= Langue::t('candidature.prenom') ?> *</label>
                <input type="text" name="prenom" class="form-control" required
                       value="<?= $valeur('prenom') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" style="font-size:.88rem"><?= Langue::t('candidature.nom') ?> *</label>
                <input type="text" name="nom" class="form-control" required
                       value="<?= $valeur('nom') ?>">
            </div>
        </div>

        <div class="row g-3 mt-0">
            <div class="col-md-6">
                <label class="form-label" style="font-size:.88rem"><?= Langue::t('candidature.email') ?></label>
                <input type="email" name="email" class="form-control" value="<?= $valeur('email') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" style="font-size:.88rem"><?= Langue::t('candidature.telephone') ?></label>
                <input type="tel" name="telephone" class="form-control" value="<?= $valeur('telephone') ?>">
            </div>
        </div>

        <div class="mt-3">
            <label class="form-label" style="font-size:.88rem"><?= Langue::t('candidature.adresse') ?></label>
            <input type="text" name="adresse" class="form-control" value="<?= $valeur('adresse') ?>">
        </div>

        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" name="permis_conduire"
                   id="permis" <?= !empty($saisie['permis_conduire']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="permis" style="font-size:.92rem">
                <?= Langue::t('candidature.permis') ?>
            </label>
            <div class="text-body-tertiary" style="font-size:.82rem">
                <?= Langue::t('candidature.permis_aide') ?>
            </div>
        </div>

        <button type="submit" class="btn btn-primary rounded-pill px-4 mt-4">
            <?= Langue::t('candidature.envoyer') ?>
        </button>

        <div class="text-body-tertiary mt-3" style="font-size:.82rem">
            <?= Langue::t('candidature.champs_obligatoires') ?>
        </div>

    </form>

</div>
