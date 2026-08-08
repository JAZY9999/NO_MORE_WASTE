<?php

use App\Vue;

/**
 * Les messages a usage unique ("flash messages").
 *
 * Extrait du gabarit pour etre inclus par les DEUX gabarits (back et front)
 * sans duplication. Si ce bloc etait recopie, corriger un detail d'affichage
 * demanderait de le faire deux fois -- et on en oublierait un.
 *
 * POURQUOI ON LES EFFACE APRES LECTURE
 *
 * Un message est souvent depose JUSTE AVANT une redirection :
 *
 *     $_SESSION['message_succes'] = "Enregistre.";
 *     Auth::rediriger('/back/commercants');
 *
 * Il doit donc survivre au changement de page (d'ou la session), mais
 * disparaitre ensuite. Sans le unset, il resterait affiche sur toutes les
 * pages suivantes, indefiniment.
 */

$messageErreur = $_SESSION['message_erreur'] ?? null;
$messageSucces = $_SESSION['message_succes'] ?? null;
unset($_SESSION['message_erreur'], $_SESSION['message_succes']);

?>

<?php if ($messageErreur): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= Vue::e($messageErreur) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
<?php endif; ?>

<?php if ($messageSucces): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <?= Vue::e($messageSucces) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
<?php endif; ?>
