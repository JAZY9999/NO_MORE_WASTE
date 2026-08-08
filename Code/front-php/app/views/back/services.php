<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Les services et leurs creneaux.
 *
 * L'ecran est organise autour du CRENEAU : c'est lui qui a une date, un lieu,
 * un benevole affecte et des inscrits.
 *
 * Variables attendues : $services, $creneaux, $benevoles, $nomsBenevoles,
 * $competences, $aujourdhui
 */

$services = $services ?? [];
$creneaux = $creneaux ?? [];
$benevoles = $benevoles ?? [];
$nomsBenevoles = $nomsBenevoles ?? [];
$competences = $competences ?? [];
$types = $types ?? [];
$aujourdhui = $aujourdhui ?? date('Y-m-d');

$couleurs = [
    'ouvert' => 'success',
    'complet' => 'danger',
    'annule' => 'secondary',
];

?>

<!-- ================================================================
     Le planning quotidien : telechargement CSV et envoi par e-mail.
     ================================================================ -->
<div class="bg-body border rounded-3 p-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="lh-sm">
        <div class="fw-medium" style="font-size:.88rem">
            <i class="bi bi-envelope-arrow-up text-primary"></i>
            <?= Langue::t('services.planning_titre') ?>
        </div>
        <small class="text-body-tertiary" style="font-size:.76rem">
            <?= Langue::t('services.planning_aide') ?>
        </small>
    </div>

    <form method="get" action="/back/plannings" class="d-flex gap-2 align-items-center flex-wrap">
        <input type="date" name="date" value="<?= Vue::e($aujourdhui) ?>"
               class="form-control form-control-sm" style="width:auto">
        <button type="submit" class="btn btn-sm btn-light">
            <i class="bi bi-filetype-csv"></i> <?= Langue::t('services.telecharger_csv') ?>
        </button>
    </form>
</div>

<!-- L'envoi par e-mail est un POST separe : ce n'est pas une lecture, ca
     declenche reellement des envois. Un GET aurait pu etre rejoue par un
     simple rafraichissement de page. -->
<form method="post" action="/back/plannings" class="mb-3">
    <input type="hidden" name="date" value="<?= Vue::e($aujourdhui) ?>">
    <button type="submit" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-send"></i> <?= Langue::t('services.envoyer_maintenant') ?>
    </button>
    <small class="text-body-tertiary ms-2" style="font-size:.76rem">
        <?= Langue::t('services.envoi_aide') ?>
    </small>
</form>

<!-- ================================================================
     La liste a plat des creneaux, tous services confondus.
     ================================================================ -->
<?php if (empty($creneaux)): ?>

    <div class="bg-body border rounded-3 p-5 text-center mb-3">
        <i class="bi bi-calendar-week fs-1 text-body-tertiary"></i>
        <div class="fw-medium mt-3"><?= Langue::t('services.aucun_creneau') ?></div>
        <p class="text-body-secondary mb-0" style="font-size:.88rem">
            <?= Langue::t('services.aucun_creneau_detail') ?>
        </p>
    </div>

<?php else: ?>

    <div class="bg-body border rounded-3 mb-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="border-bottom">
                    <tr>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('services.service') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('services.quand') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('services.lieu') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('services.benevole_affecte') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('services.inscrits') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('commun.statut') ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($creneaux as $c): ?>
                    <?php
                    $creneauId = (int) ($c['id'] ?? 0);
                    $statut = $c['statut'] ?? 'ouvert';
                    $couleur = $couleurs[$statut] ?? 'secondary';

                    $date = !empty($c['date_creneau'])
                        ? date('d/m', strtotime($c['date_creneau']))
                        : '';

                    $capacite = (int) ($c['capacite_max'] ?? 0);
                    $inscrits = (int) ($c['inscrits'] ?? 0);

                    // Le taux de remplissage. La division est protegee : une
                    // capacite a zero ferait planter la page.
                    $taux = $capacite > 0 ? min(100, (int) (100 * $inscrits / $capacite)) : 0;
                    $couleurBarre = ($capacite > 0 && $inscrits >= $capacite) ? 'danger' : 'success';

                    $benevoleId = (int) ($c['benevole_id'] ?? 0);
                    $nomBenevole = $nomsBenevoles[$benevoleId] ?? '';
                    ?>
                    <tr>
                        <td class="fw-medium" style="font-size:.86rem">
                            <?= Vue::e($c['service_nom'] ?? '') ?>
                        </td>

                        <td class="lh-sm" style="font-size:.82rem">
                            <?= Vue::e($date) ?><br>
                            <small class="text-body-tertiary" style="font-size:.72rem">
                                <?= Vue::e(($c['heure_debut'] ?? '') . '-' . ($c['heure_fin'] ?? '')) ?>
                            </small>
                        </td>

                        <td style="font-size:.82rem">
                            <?= !empty($c['lieu'])
                                ? Vue::e($c['lieu'])
                                : '<span class="text-body-tertiary">&mdash;</span>' ?>
                        </td>

                        <td style="min-width:190px">
                            <?php if ($benevoleId > 0 && $nomBenevole !== ''): ?>
                                <div style="font-size:.82rem"><?= Vue::e($nomBenevole) ?></div>
                            <?php else: ?>
                                <!-- Le menu ne propose que des benevoles VALIDES.
                                     La competence, elle, est verifiee par l'API :
                                     on affiche laquelle est requise pour que le
                                     refus soit comprehensible. -->
                                <form method="post" action="/back/services" class="d-flex gap-1">
                                    <input type="hidden" name="action" value="affecter">
                                    <input type="hidden" name="creneau_id" value="<?= $creneauId ?>">
                                    <select name="benevole_id" class="form-select form-select-sm"
                                            style="font-size:.78rem">
                                        <option value="0"><?= Langue::t('services.choisir') ?></option>
                                        <?php foreach ($benevoles as $b): ?>
                                            <option value="<?= (int) $b['id'] ?>">
                                                <?= Vue::e(trim(($b['prenom'] ?? '') . ' ' . ($b['nom'] ?? ''))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-light"
                                            style="font-size:.76rem"><?= Langue::t('services.affecter') ?></button>
                                </form>
                                <?php if (!empty($c['competence_requise'])): ?>
                                    <small class="text-body-tertiary" style="font-size:.72rem">
                                        <?= Langue::t('services.requiert') ?> <?= Vue::e($c['competence_requise']) ?>
                                    </small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td style="min-width:100px">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:4px">
                                    <div class="progress-bar bg-<?= $couleurBarre ?>"
                                         style="width:<?= $taux ?>%"></div>
                                </div>
                                <small class="text-body-tertiary" style="font-size:.72rem">
                                    <?= $inscrits ?>/<?= $capacite ?>
                                </small>
                            </div>
                        </td>

                        <td>
                            <span class="badge rounded-pill text-bg-<?= $couleur ?>" style="font-size:.66rem">
                                <?= Langue::t('services.statut_' . $statut) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<div class="border-start border-3 border-secondary-subtle ps-3 py-1 mb-4 text-body-secondary"
     style="font-size:.8rem">
    <strong><?= Langue::t('services.regle_titre') ?></strong>
    <?= Langue::t('services.regle_detail') ?>
</div>

<!-- ================================================================
     Les deux formulaires de creation, cote a cote.
     ================================================================ -->
<div class="row g-3">

    <div class="col-lg-6">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold mb-3"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('services.nouveau_service') ?></div>

            <form method="post" action="/back/services">
                <input type="hidden" name="action" value="creer_service">

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('services.nom') ?></label>
                    <input type="text" name="nom" class="form-control form-control-sm" required>
                </div>

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('services.type') ?></label>
                    <!-- Un menu et non un champ libre : la base n'accepte que
                         ces sept valeurs (contrainte CHECK). Laisser saisir
                         n'importe quoi produisait une erreur serveur pour ce
                         qui est, cote utilisateur, une simple faute de frappe. -->
                    <select name="type" class="form-select form-select-sm" required>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= Vue::e($t) ?>"><?= Langue::t('services.type_' . $t) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-body-tertiary" style="font-size:.72rem">
                        <?= Langue::t('services.type_aide') ?>
                    </small>
                </div>

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('services.description') ?></label>
                    <textarea name="description" class="form-control form-control-sm" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('services.competence_requise') ?></label>
                    <select name="competence_requise_id" class="form-select form-select-sm">
                        <option value="0"><?= Langue::t('services.aucune_competence') ?></option>
                        <?php foreach ($competences as $id => $libelle): ?>
                            <option value="<?= (int) $id ?>"><?= Vue::e($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-sm btn-primary"><?= Langue::t('commun.creer') ?></button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold mb-3"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('services.nouveau_creneau') ?></div>

            <?php if (empty($services)): ?>

                <div class="text-body-secondary" style="font-size:.84rem">
                    <?= Langue::t('services.creez_service_dabord') ?>
                </div>

            <?php else: ?>

                <form method="post" action="/back/services">
                    <input type="hidden" name="action" value="creer_creneau">

                    <div class="mb-2">
                        <label class="form-label" style="font-size:.8rem"><?= Langue::t('services.service') ?></label>
                        <select name="service_id" class="form-select form-select-sm" required>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= Vue::e($s['nom'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:.8rem"><?= Langue::t('services.date') ?></label>
                            <input type="date" name="date_creneau" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:.8rem"><?= Langue::t('services.debut') ?></label>
                            <input type="time" name="heure_debut" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:.8rem"><?= Langue::t('services.fin') ?></label>
                            <input type="time" name="heure_fin" class="form-control form-control-sm" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <label class="form-label" style="font-size:.8rem"><?= Langue::t('services.lieu') ?></label>
                            <input type="text" name="lieu" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:.8rem"><?= Langue::t('services.capacite') ?></label>
                            <input type="number" name="capacite_max" class="form-control form-control-sm"
                                   min="1" value="1">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary"><?= Langue::t('commun.creer') ?></button>
                </form>

            <?php endif; ?>
        </div>
    </div>

</div>
