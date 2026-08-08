<?php

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Vue;

/**
 * La barre laterale sombre du back-office.
 *
 * Elle se contente de PARCOURIR la description du menu : la liste des
 * rubriques vit dans app/config/menu_back.php, jamais ici. Ajouter une entree
 * ne demande donc pas de toucher a ce fichier.
 *
 * data-bs-theme="dark" fait basculer toutes les classes Bootstrap a
 * l'interieur en version sombre -- sans une seule ligne de CSS ecrite a la
 * main. Le contraste avec le contenu suffit a separer la navigation du reste :
 * pas besoin de bordure.
 *
 * Variables attendues :
 *   $menu       : le tableau de config/menu_back.php
 *   $menuActif  : la cle de l'entree a surligner (peut etre '')
 *   $compteurs  : ['adhesions' => 2, ...] facultatif
 *   $config     : la configuration
 */

$compteurs = $compteurs ?? [];
$utilisateur = Auth::utilisateur();
$email = $utilisateur['email'] ?? '';

// Deux lettres tirees de l'email, faute de photo de profil. mb_* pour ne pas
// couper un caractere accentue en deux octets.
$initiales = mb_strtoupper(mb_substr($email, 0, 2));

?>
<aside class="d-flex flex-column flex-shrink-0 bg-body" data-bs-theme="dark" style="width:225px">

    <a href="/" class="d-flex align-items-center gap-2 px-3 py-3 text-decoration-none">
        <span class="d-flex align-items-center justify-content-center rounded-circle bg-primary text-white flex-shrink-0"
              style="width:30px; height:30px"><i class="bi bi-recycle" style="font-size:.9rem"></i></span>
        <span class="d-flex flex-column lh-1">
            <span class="fw-semibold text-body" style="font-size:.76rem; letter-spacing:.04em">
                <?= Langue::t('app.nom') ?>
            </span>
            <small class="text-body-tertiary" style="font-size:.62rem"><?= Langue::t('back.titre') ?></small>
        </span>
    </a>

    <nav class="flex-grow-1 overflow-auto pb-3">
        <?php foreach ($menu['sections'] as $section): ?>

            <div class="px-4 pt-3 pb-1 text-uppercase text-body-tertiary"
                 style="font-size:.6rem; letter-spacing:.12em">
                <?= Langue::t($section['titre']) ?>
            </div>

            <?php foreach ($section['entrees'] as $entree): ?>
                <?php
                // Certaines entrees ne concernent qu'un role precis. On les
                // masque plutot que de proposer un lien qui renvoie au
                // tableau de bord -- un lien qui rebondit donne l'impression
                // d'un site casse.
                //
                // C'est du CONFORT : la vraie protection est dans le
                // controleur, qui verifie le role a chaque appel.
                if (isset($entree['role']) && Auth::role() !== $entree['role']) {
                    continue;
                }

                $estActive = ($entree['cle'] === $menuActif);

                $classes = 'd-flex align-items-center gap-2 px-3 py-2 mx-2 rounded text-decoration-none ';
                $classes .= $estActive
                    ? 'bg-primary text-white fw-medium'
                    : 'text-body-secondary';

                // La pastille n'apparait que si un compteur est fourni ET
                // positif : afficher "0 candidature a valider" attirerait
                // l'oeil sur une information sans interet.
                $compteur = $compteurs[$entree['cle']] ?? null;
                $afficheCompteur = is_int($compteur) && $compteur > 0;
                ?>
                <a href="<?= Vue::e($entree['url']) ?>" class="<?= $classes ?>" style="font-size:.84rem">
                    <i class="bi <?= Vue::e($entree['icone']) ?>" style="width:16px"></i>
                    <?= Langue::t($entree['libelle']) ?>
                    <?php if ($afficheCompteur): ?>
                        <span class="badge rounded-pill text-bg-<?= Vue::e($entree['couleur'] ?? 'secondary') ?> ms-auto"
                              style="font-size:.6rem"><?= (int) $compteur ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

        <?php endforeach; ?>
    </nav>

    <div class="p-3 border-top border-secondary-subtle">
        <a href="/back/profil" class="d-flex align-items-center gap-2 text-decoration-none">
            <span class="d-flex align-items-center justify-content-center rounded-circle bg-secondary text-white fw-semibold flex-shrink-0"
                  style="width:28px; height:28px; font-size:.66rem"><?= Vue::e($initiales) ?></span>
            <div class="lh-1 flex-grow-1 overflow-hidden">
                <div class="text-body text-truncate" style="font-size:.72rem"><?= Vue::e($email) ?></div>
                <small class="text-body-tertiary" style="font-size:.62rem"><?= Vue::e(Auth::role()) ?></small>
            </div>
        </a>
        <a href="/deconnexion" class="btn btn-sm btn-outline-secondary w-100 mt-2" style="font-size:.74rem">
            <i class="bi bi-box-arrow-right"></i> <?= Langue::t('nav.deconnexion') ?>
        </a>
    </div>

</aside>
