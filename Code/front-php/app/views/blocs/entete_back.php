<?php

use App\Middleware\Langue;
use App\Vue;

/**
 * L'en-tete d'un ecran de back-office : fil d'Ariane, titre, actions, onglets.
 *
 * Tout ce qu'il affiche vient de $options (voir Vue::afficher). Le controleur
 * DECRIT ce qu'il veut ; ce fichier decide comment le dessiner.
 *
 * C'est la raison pour laquelle 'actions' et 'onglets' sont des tableaux et
 * non du HTML tout pret : si un controleur pouvait envoyer
 * "<a class='btn'>...</a>", on rouvrirait une porte aux failles XSS dans le
 * seul projet ou toute donnee passe par Vue::e() -- et le Bootstrap se
 * disperserait dans les controleurs.
 *
 * Variables attendues : $options, $titre
 */

$titrePage  = $options['titre_page'] ?? $titre;
$sousTitre  = $options['sous_titre'] ?? '';
$actions    = $options['actions'] ?? [];
$onglets    = $options['onglets'] ?? [];
$recherche  = $options['recherche'] ?? true;

// Par defaut : "Back-office / <titre de la page>". Un controleur peut fournir
// un fil plus profond, par exemple Commercants > Boulangerie Martin.
$fil = $options['fil'] ?? [['libelle' => $titrePage, 'url' => null]];

?>
<header class="bg-body border-bottom px-4 pt-3 sticky-top">

    <nav style="--bs-breadcrumb-divider:'/'" class="mb-1">
        <ol class="breadcrumb mb-0" style="font-size:.74rem">
            <li class="breadcrumb-item">
                <a href="/back" class="text-decoration-none text-body-tertiary"><?= Langue::t('back.titre') ?></a>
            </li>
            <?php foreach ($fil as $etape): ?>
                <?php if (!empty($etape['url'])): ?>
                    <li class="breadcrumb-item">
                        <a href="<?= Vue::e($etape['url']) ?>" class="text-decoration-none text-body-tertiary">
                            <?= Vue::e($etape['libelle']) ?>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="breadcrumb-item active"><?= Vue::e($etape['libelle']) ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h1 class="h5 mb-0"><?= Vue::e($titrePage) ?></h1>
            <?php if ($sousTitre !== ''): ?>
                <div class="text-body-secondary" style="font-size:.82rem"><?= Vue::e($sousTitre) ?></div>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <?php if ($recherche): ?>
                <div class="input-group input-group-sm" style="width:200px">
                    <span class="input-group-text bg-body border-end-0">
                        <i class="bi bi-search text-body-tertiary" style="font-size:.8rem"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 ps-0"
                           placeholder="<?= Vue::e(Langue::t('commun.rechercher')) ?>" style="font-size:.8rem">
                </div>
            <?php endif; ?>

            <?php foreach ($actions as $action): ?>
                <?php
                $style = $action['style'] ?? 'light';
                $icone = !empty($action['icone'])
                    ? '<i class="bi ' . Vue::e($action['icone']) . '"></i> '
                    : '';
                ?>
                <a href="<?= Vue::e($action['url'] ?? '#') ?>" class="btn btn-sm btn-<?= Vue::e($style) ?>">
                    <?= $icone ?><?= Vue::e($action['libelle']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($onglets): ?>
        <!-- margin-bottom negatif : les onglets doivent chevaucher la bordure
             basse de l'en-tete pour que l'onglet actif paraisse s'y fondre. -->
        <ul class="nav mt-3 mb-0" style="margin-bottom:-1px">
            <?php foreach ($onglets as $onglet): ?>
                <?php
                $classe = 'nav-link px-0 me-4 border-0 border-bottom border-2 rounded-0';
                $classe .= !empty($onglet['actif'])
                    ? ' active border-primary text-primary fw-medium'
                    : ' border-transparent text-body-secondary';
                ?>
                <li class="nav-item">
                    <a class="<?= $classe ?>" href="<?= Vue::e($onglet['url'] ?? '#') ?>" style="font-size:.85rem">
                        <?= Vue::e($onglet['libelle']) ?>
                        <?php if (isset($onglet['compteur'])): ?>
                            <span class="badge rounded-pill text-bg-light border text-body-secondary ms-1"
                                  style="font-size:.6rem"><?= (int) $onglet['compteur'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</header>
