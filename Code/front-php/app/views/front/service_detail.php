<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Le detail d'un service et ses creneaux, avec l'inscription.
 *
 * Trois etats possibles pour le bouton d'inscription, selon QUI regarde :
 *   - visiteur non connecte -> on l'invite a se connecter
 *   - connecte mais pas adherent (benevole, staff) -> on l'explique
 *   - adherent -> le bouton, sauf si le creneau est complet
 *
 * Variables attendues : $service, $creneaux, $peutSInscrire, $estConnecte
 */

$service = $service ?? [];
$creneaux = $creneaux ?? [];
$peutSInscrire = $peutSInscrire ?? false;
$estConnecte = $estConnecte ?? false;

$id = (int) ($service['id'] ?? 0);

?>

<a href="/services" class="text-decoration-none text-body-secondary" style="font-size:.88rem">
    &larr; <?= Langue::t('services_publics.tous_les_services') ?>
</a>

<div class="row g-5 mt-1">

    <div class="col-lg-8">
        <h1 class="display-6 fw-bold mb-3"><?= Vue::e($service['nom'] ?? '') ?></h1>

        <?php if (!empty($service['description'])): ?>
            <p class="fs-5 text-body-secondary mb-5" style="max-width:560px">
                <?= Vue::e($service['description']) ?>
            </p>
        <?php endif; ?>

        <div class="text-uppercase text-body-tertiary fw-semibold"
             style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('services_publics.creneaux_disponibles') ?></div>

        <?php if (empty($creneaux)): ?>

            <p class="text-body-secondary mt-3 mb-0" style="font-size:.94rem">
                <?= Langue::t('services_publics.aucun_creneau_detail') ?>
            </p>

        <?php else: ?>

            <div class="border-top mt-3">
                <?php foreach ($creneaux as $c): ?>
                    <?php
                    $creneauId = (int) ($c['id'] ?? 0);
                    $date = !empty($c['date_creneau']) ? date('d/m/Y', strtotime($c['date_creneau'])) : '';
                    // L'API renvoie deja "HH:MM".
                    $horaire = ($c['heure_debut'] ?? '') . ' – ' . ($c['heure_fin'] ?? '');
                    $complet = ($c['statut'] ?? '') === 'complet';
                    ?>

                    <div class="d-flex justify-content-between align-items-center gap-4 py-3 border-bottom flex-wrap">
                        <div>
                            <div class="fw-medium"><?= Vue::e($date) ?></div>
                            <div class="text-body-secondary" style="font-size:.88rem">
                                <?= Vue::e($horaire) ?>
                                <?= !empty($c['lieu']) ? ' · ' . Vue::e($c['lieu']) : '' ?>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <span class="text-body-secondary" style="font-size:.86rem">
                                <?= (int) ($c['capacite_max'] ?? 0) ?> <?= Langue::t('services_publics.places') ?>
                            </span>

                            <?php if ($complet): ?>
                                <span class="text-body-tertiary" style="font-size:.88rem">
                                    <?= Langue::t('services.statut_complet') ?>
                                </span>
                            <?php elseif ($peutSInscrire): ?>
                                <!-- Aucun identifiant de personne n'est envoye :
                                     l'API deduit du jeton qui s'inscrit. -->
                                <form method="post" action="/services/<?= $id ?>/inscription">
                                    <input type="hidden" name="creneau_id" value="<?= $creneauId ?>">
                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">
                                        <?= Langue::t('services_publics.s_inscrire') ?>
                                    </button>
                                </form>
                            <?php elseif (!$estConnecte): ?>
                                <a href="/connexion" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <?= Langue::t('services_publics.se_connecter_pour') ?>
                                </a>
                            <?php else: ?>
                                <small class="text-body-tertiary" style="font-size:.82rem">
                                    <?= Langue::t('services_publics.reserve_adherents') ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="border-start border-3 border-primary ps-4">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('services_publics.bon_a_savoir') ?></div>
            <ul class="mt-3 mb-0 ps-3 text-body-secondary" style="font-size:.9rem">
                <li class="mb-2"><?= Langue::t('services_publics.info_gratuit') ?></li>
                <li class="mb-2"><?= Langue::t('services_publics.info_capacite') ?></li>
                <li><?= Langue::t('services_publics.info_compte') ?></li>
            </ul>
        </div>
    </div>

</div>
