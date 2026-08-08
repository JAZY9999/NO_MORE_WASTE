<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Page de connexion (item 1.3 de la todo : "page de connexion multilingue").
 *
 * Tous les libelles passent par Langue::t : la page s'affiche donc en francais,
 * anglais, italien ou portugais sans qu'on ait a la dupliquer.
 *
 * Variable attendue : $emailSaisi (pour ne pas vider le champ apres une erreur)
 */

$emailSaisi = $emailSaisi ?? '';

?>

<!-- justify-content-center centre la colonne horizontalement -->
<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-6 col-lg-4">

        <div class="card shadow-sm">
            <div class="card-body p-4">

                <h1 class="h4 card-title text-center mb-4">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <?= Langue::t('connexion.titre') ?>
                </h1>

                <form method="post" action="/connexion">

                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <?= Langue::t('connexion.email') ?>
                        </label>
                        <!-- input-group permet de coller une icone au champ -->
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= Vue::e($emailSaisi) ?>" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="mot_de_passe" class="form-label">
                            <?= Langue::t('connexion.mot_de_passe') ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="mot_de_passe"
                                   name="mot_de_passe" required>
                        </div>
                    </div>

                    <!-- d-grid fait occuper toute la largeur au bouton -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">
                            <?= Langue::t('connexion.valider') ?>
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>
