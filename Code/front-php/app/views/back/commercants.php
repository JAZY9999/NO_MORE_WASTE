<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Liste des commercants avec filtre (item 2.4 de la todo).
 *
 * Variables attendues :
 *   $commercants  : tableau venant de l'API (peut etre vide)
 *   $villeFiltre  : la ville actuellement filtree ('' si aucune)
 *   $villes       : la liste des villes presentes, pour alimenter le menu
 */

$commercants = $commercants ?? [];
$villeFiltre = $villeFiltre ?? '';
$villes = $villes ?? [];

?>


<!-- Formulaire de filtre.
     method="get" (et non post) pour que la page filtree soit partageable,
     rechargeable et ajoutable aux favoris : /back/commercants?ville=Naples -->
<form method="get" action="/back/commercants" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-center">

        <div class="col-auto">
            <label for="ville" class="col-form-label">
                <i class="bi bi-funnel"></i> <?= Langue::t('commercants.ville') ?>
            </label>
        </div>

        <div class="col-12 col-sm-auto">
            <!-- onchange envoie le formulaire des qu'on change de ville :
                 pas besoin d'un bouton. C'est le seul JavaScript du projet. -->
            <select id="ville" name="ville" class="form-select" onchange="this.form.submit()">
                <option value="">—</option>
                <?php foreach ($villes as $ville): ?>
                    <option value="<?= Vue::e($ville) ?>"
                        <?= $ville === $villeFiltre ? 'selected' : '' ?>>
                        <?= Vue::e($ville) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- ms-sm-auto pousse le compteur a droite -->
        <div class="col-12 col-sm-auto ms-sm-auto">
            <span class="badge text-bg-secondary">
                <?= count($commercants) ?> <?= Langue::t('commercants.total') ?>
            </span>
        </div>

    </div>
</form>

<?php if (empty($commercants)): ?>

    <div class="alert alert-info mb-0">
        <i class="bi bi-info-circle"></i> <?= Langue::t('commercants.aucun') ?>
    </div>

<?php else: ?>

    <!-- table-responsive rend le tableau defilable horizontalement sur petit
         ecran, au lieu de deformer toute la page. -->
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle bg-body shadow-sm">
            <thead class="table-light">
            <tr>
                <th><?= Langue::t('commercants.raison_sociale') ?></th>
                <th><?= Langue::t('commercants.ville') ?></th>
                <th><?= Langue::t('commercants.pays') ?></th>
                <th><?= Langue::t('commercants.email') ?></th>
                <th><?= Langue::t('commercants.telephone') ?></th>
                <th><?= Langue::t('commercants.contact') ?></th>
                <th><?= Langue::t('commercants.compte') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($commercants as $c): ?>
                <tr>
                    <td class="fw-semibold">
                        <!-- Le nom mene a la fiche. Avant, cette liste etait
                             une impasse : on voyait les partenaires sans
                             pouvoir en ouvrir un seul. -->
                        <a href="/back/commercants/<?= (int) $c['id'] ?>"
                           class="text-decoration-none text-body">
                            <?= Vue::e($c['raison_sociale'] ?? '') ?>
                        </a>
                    </td>
                    <td>
                        <i class="bi bi-geo-alt text-body-secondary"></i>
                        <?= Vue::e($c['ville'] ?? '') ?>
                    </td>
                    <td><?= Vue::e($c['pays'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($c['email'])): ?>
                            <a href="mailto:<?= Vue::e($c['email']) ?>">
                                <?= Vue::e($c['email']) ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td><?= Vue::e($c['telephone'] ?? '') ?></td>
                    <td><?= Vue::e($c['contact_nom'] ?? '') ?></td>
                    <td>
                        <?php // Sans compte rattache, le commercant ne peut pas
                              // ouvrir son espace client. L'information vaut
                              // d'etre visible d'un coup d'oeil sur la liste. ?>
                        <?php if (!empty($c['utilisateur_id'])): ?>
                            <i class="bi bi-check-circle-fill text-success"
                               title="<?= Vue::e(Langue::t('commercants.compte_rattache_court')) ?>"></i>
                        <?php else: ?>
                            <i class="bi bi-dash-circle text-body-tertiary"
                               title="<?= Vue::e(Langue::t('commercants.aucun_compte')) ?>"></i>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/back/commercants/<?= (int) $c['id'] ?>" class="btn btn-sm btn-light">
                            <?= Langue::t('commun.ouvrir') ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>
