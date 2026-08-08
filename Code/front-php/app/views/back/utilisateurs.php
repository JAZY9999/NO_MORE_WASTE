<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * Les comptes et leurs roles.
 *
 * Ecran reserve a admin_back : la liste des comptes renseigne sur qui peut
 * faire quoi dans l'application.
 *
 * Variables attendues : $utilisateurs, $roles, $moi
 */

$utilisateurs = $utilisateurs ?? [];
$roles = $roles ?? [];
$moi = $moi ?? '';

// La couleur dit le niveau de pouvoir, du plus eleve au plus restreint.
$couleurs = [
    'admin_back' => 'danger',
    'staff_back' => 'primary',
    'adherent' => 'success',
    'benevole' => 'info',
];

?>

<div class="row g-3">

    <div class="col-xl-8">

        <?php if (empty($utilisateurs)): ?>

            <div class="bg-body border rounded-3 p-5 text-center">
                <i class="bi bi-person-gear fs-1 text-body-tertiary"></i>
                <div class="fw-medium mt-3"><?= Langue::t('utilisateurs.aucun') ?></div>
            </div>

        <?php else: ?>

            <div class="bg-body border rounded-3">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="border-bottom">
                            <tr>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('utilisateurs.compte') ?></span></th>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('utilisateurs.role') ?></span></th>
                                <th><span class="text-uppercase text-body-tertiary fw-semibold"
                                          style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('utilisateurs.etat') ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($utilisateurs as $u): ?>
                            <?php
                            $email = $u['email'] ?? '';
                            $role = $u['role'] ?? '';
                            $estMoi = ($email === $moi);

                            // Deux lettres tirees de l'email, faute de photo.
                            // mb_* pour ne pas couper un caractere accentue
                            // en deux octets.
                            $initiales = mb_strtoupper(mb_substr($email, 0, 2));

                            $nomComplet = trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="d-flex align-items-center justify-content-center rounded-circle
                                                     bg-<?= $couleurs[$role] ?? 'secondary' ?>-subtle
                                                     text-<?= $couleurs[$role] ?? 'secondary' ?>-emphasis
                                                     fw-semibold flex-shrink-0"
                                              style="width:30px; height:30px; font-size:.68rem"><?= Vue::e($initiales) ?></span>
                                        <div class="lh-sm">
                                            <div class="fw-medium" style="font-size:.86rem">
                                                <?= $nomComplet !== '' ? Vue::e($nomComplet) : Vue::e($email) ?>
                                                <?php if ($estMoi): ?>
                                                    <span class="badge rounded-pill text-bg-light border ms-1"
                                                          style="font-size:.62rem"><?= Langue::t('utilisateurs.moi') ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($nomComplet !== ''): ?>
                                                <small class="text-body-tertiary" style="font-size:.74rem">
                                                    <?= Vue::e($email) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill text-bg-<?= $couleurs[$role] ?? 'secondary' ?>"
                                          style="font-size:.66rem"><?= Langue::t('utilisateurs.role_' . $role) ?></span>
                                </td>
                                <td>
                                    <span class="badge rounded-pill text-bg-<?= !empty($u['actif']) ? 'success' : 'secondary' ?>"
                                          style="font-size:.66rem">
                                        <?= Langue::t(!empty($u['actif']) ? 'utilisateurs.actif' : 'utilisateurs.inactif') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

        <!-- Ce que cet ecran NE PERMET PAS. Le dire vaut mieux que de laisser
             chercher un bouton qui n'existe pas. -->
        <div class="border-start border-3 border-secondary-subtle ps-3 py-1 mt-3 text-body-secondary"
             style="font-size:.8rem">
            <?= Langue::t('utilisateurs.limites') ?>
        </div>

    </div>

    <div class="col-xl-4">
        <div class="bg-body border rounded-3 p-3">
            <div class="text-uppercase text-body-tertiary fw-semibold mb-3"
                 style="font-size:.64rem; letter-spacing:.1em"><?= Langue::t('utilisateurs.nouveau') ?></div>

            <form method="post" action="/back/utilisateurs">

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('candidature.email') ?> *</label>
                    <input type="email" name="email" class="form-control form-control-sm" required>
                </div>

                <div class="mb-2">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('utilisateurs.mot_de_passe') ?> *</label>
                    <!-- type="password" : le navigateur masque la saisie et ne
                         la propose pas en autocompletion. minlength double la
                         verification faite cote serveur. -->
                    <input type="password" name="mot_de_passe" class="form-control form-control-sm"
                           minlength="8" required>
                    <small class="text-body-tertiary" style="font-size:.74rem">
                        <?= Langue::t('utilisateurs.mdp_aide') ?>
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label" style="font-size:.8rem"><?= Langue::t('utilisateurs.role') ?> *</label>
                    <select name="role" class="form-select form-select-sm" required>
                        <?php foreach ($roles as $r): ?>
                            <?php // Le role par defaut est le moins puissant :
                                  // un clic distrait ne cree pas un administrateur. ?>
                            <option value="<?= Vue::e($r) ?>" <?= $r === 'adherent' ? 'selected' : '' ?>>
                                <?= Langue::t('utilisateurs.role_' . $r) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-sm btn-primary"><?= Langue::t('commun.creer') ?></button>

                <div class="border-start border-3 border-warning ps-3 py-1 mt-3 text-body-secondary"
                     style="font-size:.76rem">
                    <?= Langue::t('utilisateurs.avertissement_role') ?>
                </div>

            </form>
        </div>
    </div>

</div>
