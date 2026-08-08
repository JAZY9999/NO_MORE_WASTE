<?php

namespace App\Controllers\Front;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * La candidature pour devenir benevole.
 *
 * Le sujet : "chacun peut s'inscrire pour devenir benevole a condition de
 * valider un certain nombre de conditions".
 *
 * "Chacun" : la page est PUBLIQUE, aucune connexion demandee. C'est la porte
 * d'entree de l'association -- exiger un compte avant meme de savoir si la
 * personne sera retenue ferait perdre des candidats.
 *
 * "a condition de valider" : la candidature est enregistree au statut
 * "candidat". C'est le back-office qui valide ensuite, une fois les
 * justificatifs verifies.
 */
class CandidatureController
{
    private ApiClient $api;
    private array $config;

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /benevoles/candidature
     */
    public function formulaire(): void
    {
        Vue::afficher('front/candidature', [
            'config' => $this->config,
            // Si le visiteur est deja connecte, sa fiche sera rattachee a son
            // compte -- et son espace benevole marchera des la validation. On
            // le lui dit, c'est un argument pour se connecter d'abord.
            'estConnecte' => Auth::estConnecte(),
            // Reaffiche apres une erreur, pour ne pas retaper le formulaire.
            'saisie' => $_SESSION['candidature_saisie'] ?? [],
        ], Langue::t('nav.devenir_benevole'));

        unset($_SESSION['candidature_saisie']);
    }

    /**
     * POST /benevoles/candidature
     */
    public function envoyer(): void
    {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');

        if ($nom === '' || $prenom === '') {
            $_SESSION['message_erreur'] = Langue::t('candidature.nom_prenom_obligatoires');
            $_SESSION['candidature_saisie'] = $_POST;
            Auth::rediriger('/benevoles/candidature');
            return;
        }

        $candidature = ['nom' => $nom, 'prenom' => $prenom];

        // Les champs facultatifs ne partent que s'ils sont remplis : une
        // chaine vide dans une colonne attendant un email ou un telephone
        // n'apporte rien, et vaut moins que l'absence de valeur.
        foreach (['email', 'telephone', 'adresse'] as $champ) {
            $valeur = trim($_POST[$champ] ?? '');
            if ($valeur !== '') {
                $candidature[$champ] = $valeur;
            }
        }

        // Une case a cocher n'est envoyee QUE si elle est cochee : son absence
        // vaut "non". D'ou le isset plutot qu'une lecture directe.
        $candidature['permis_conduire'] = isset($_POST['permis_conduire']);

        // Le jeton part s'il y en a un. C'est lui, et lui seul, qui rattache
        // la fiche a un compte : la route est publique, l'API refuse tout
        // identifiant de compte envoye dans le corps.
        $reponse = $this->api->post('/benevoles/candidature/', $candidature, Auth::jeton());

        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            $_SESSION['candidature_saisie'] = $_POST;
            Auth::rediriger('/benevoles/candidature');
            return;
        }

        $_SESSION['message_succes'] = Langue::t('candidature.envoyee');
        Auth::rediriger('/benevoles/candidature/merci');
    }

    /**
     * GET /benevoles/candidature/merci
     *
     * Une page distincte plutot qu'un simple message : elle explique la SUITE
     * (verification des justificatifs, puis validation). Un candidat qui ne
     * sait pas ce qui l'attend relance l'association, ou abandonne.
     */
    public function merci(): void
    {
        Vue::afficher('front/candidature_merci', [
            'config' => $this->config,
        ], Langue::t('candidature.merci_titre'));
    }
}
