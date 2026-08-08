<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * La fiche d'une campagne : son contenu, ses destinataires, son envoi.
 *
 * L'ORDRE EST UNE PROTECTION
 *
 * Le bouton d'envoi est TOUT EN BAS, apres la liste complete des
 * destinataires. Il faut avoir fait defiler la liste pour l'atteindre.
 * Un envoi d'emails ne s'annule pas : la seule protection possible est de
 * voir qui va le recevoir.
 *
 * Variables attendues : $campagne, $destinataires, $avecEmail
 */

$campagne = $campagne ?? [];
$destinataires = $destinataires ?? [];
$avecEmail = $avecEmail ?? 0;

$id = (int) ($campagne['id'] ?? 0);
$total = count($destinataires);
$sansEmail = $total - $avecEmail;

?>

<div class="row g-3 mb-3">

    <!-- Le contenu de l'email -->
    <div class="col-lg-7">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('campagnes.contenu') ?></div>

            <div class="fw-medium mt-2" style="font-size:.9rem">
                <?= Vue::e($campagne['sujet_email'] ?? '') ?>
            </div>

            <!-- white-space:pre-wrap : le corps est du texte brut, ses retours
                 a la ligne doivent s'afficher tels quels. Sans ca, tout le
                 message tiendrait sur une seule ligne. -->
            <div class="mt-2 p-3 bg-body-tertiary rounded text-body-secondary"
                 style="font-size:.86rem; white-space:pre-wrap"><?= Vue::e($campagne['corps_email'] ?? '') ?></div>

            <small class="text-body-tertiary d-block mt-2" style="font-size:.76rem">
                <?= Langue::t('campagnes.variable_aide') ?>
            </small>
        </div>
    </div>

    <!-- Qui va le recevoir -->
    <div class="col-lg-5">
        <div class="bg-body border rounded-3 p-3 h-100">
            <div class="text-uppercase text-body-tertiary fw-semibold"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('campagnes.portee') ?></div>

            <div class="fs-3 fw-semibold lh-1 mt-2"><?= $avecEmail ?></div>
            <div class="text-body-secondary" style="font-size:.84rem">
                <?= Langue::t('campagnes.recevront') ?>
            </div>

            <?php if ($sansEmail > 0): ?>
                <!-- L'API ignore les destinataires sans adresse. Le dire AVANT
                     l'envoi evite de croire que tout le monde a ete touche. -->
                <div class="border-start border-3 border-warning ps-3 py-1 mt-3 text-body-secondary"
                     style="font-size:.78rem">
                    <?= sprintf(Langue::t('campagnes.sans_email'), $sansEmail) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ================================================================
     La liste exacte des destinataires.
     ================================================================ -->
<div class="text-uppercase text-body-tertiary fw-semibold mb-2"
     style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('campagnes.destinataires') ?></div>

<?php if (empty($destinataires)): ?>

    <div class="bg-body border rounded-3 p-4 text-body-secondary" style="font-size:.86rem">
        <?= Langue::t('campagnes.aucun_destinataire') ?>
        <div class="text-body-tertiary mt-1" style="font-size:.8rem">
            <?= Langue::t('campagnes.aucun_destinataire_detail') ?>
        </div>
    </div>

<?php else: ?>

    <div class="bg-body border rounded-3 mb-3">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="border-bottom">
                    <tr>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('adhesions.commercant') ?></span></th>
                        <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                  style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('candidature.email') ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($destinataires as $d): ?>
                    <tr>
                        <td style="font-size:.86rem"><?= Vue::e($d['raison_sociale'] ?? '') ?></td>
                        <td style="font-size:.84rem">
                            <?php if (!empty($d['email'])): ?>
                                <?= Vue::e($d['email']) ?>
                            <?php else: ?>
                                <span class="text-warning-emphasis">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <?= Langue::t('campagnes.pas_d_email') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<!-- ================================================================
     L'envoi, tout en bas et derriere une confirmation.
     ================================================================ -->
<?php if ($avecEmail > 0): ?>

    <div class="bg-body border border-danger-subtle rounded-3 p-3">
        <div class="fw-medium mb-1" style="font-size:.9rem">
            <i class="bi bi-send text-danger"></i> <?= Langue::t('campagnes.declencher') ?>
        </div>
        <p class="text-body-secondary mb-3" style="font-size:.84rem">
            <?= sprintf(Langue::t('campagnes.avertissement_envoi'), $avecEmail) ?>
        </p>

        <form method="post" action="/back/campagnes/<?= $id ?>">
            <!-- Le champ de confirmation n'existe que dans ce formulaire. Un
                 POST forge sans lui ne declenche rien : le controleur le
                 verifie. -->
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="confirmation"
                       value="oui" id="confirmer" required>
                <label class="form-check-label" for="confirmer" style="font-size:.86rem">
                    <?= Langue::t('campagnes.je_confirme') ?>
                </label>
            </div>
            <button type="submit" class="btn btn-sm btn-danger">
                <?= Langue::t('campagnes.envoyer_maintenant') ?>
            </button>
        </form>
    </div>

<?php endif; ?>
