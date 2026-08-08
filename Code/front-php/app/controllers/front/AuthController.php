<?php

namespace App\Controllers\Front;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Connexion et deconnexion.
 *
 * A retenir : ce controleur ne verifie aucun mot de passe. Il transmet ce que
 * l'utilisateur a saisi a l'API Go, qui seule connait les mots de passe (haches
 * en bcrypt) et fabrique le jeton. Le front ne fait que ranger ce jeton en session.
 */
class AuthController
{
    private ApiClient $api;
    private array $config;

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /** Affiche le formulaire (GET /connexion). */
    public function formulaire(): void
    {
        if (Auth::estConnecte()) {
            Auth::rediriger('/');
            return;
        }

        Vue::afficher('front/connexion', [
            'config' => $this->config,
        ], Langue::t('connexion.titre'));
    }

    /** Traite l'envoi du formulaire (POST /connexion). */
    public function traiter(): void
    {
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        // Le nom du champ envoye a l'API est "mot_de_passe" (et non "password") :
        // l'API a ete uniformisee en francais lors de la Phase 10.
        $reponse = $this->api->post('/auth/login/', [
            'email' => $email,
            'mot_de_passe' => $motDePasse,
        ]);

        if (!ApiClient::estSucces($reponse)) {
            // On reaffiche le formulaire avec l'email deja rempli, mais jamais
            // le mot de passe : le renvoyer dans le HTML serait une mauvaise
            // pratique de securite.
            $_SESSION['message_erreur'] = Langue::t('connexion.erreur');

            Vue::afficher('front/connexion', [
                'config' => $this->config,
                'emailSaisi' => $email,
            ], Langue::t('connexion.titre'));

            return;
        }

        $jeton = $reponse['corps']['token'] ?? '';

        // Le jeton seul ne suffit pas : on a besoin du role pour savoir si cet
        // utilisateur a droit au back-office. On le demande a l'API.
        // On pourrait decoder le JWT ici, mais interroger l'API est plus sur :
        // decoder un jeton sans verifier sa signature reviendrait a croire
        // n'importe quel contenu qu'on nous presente.
        $profil = $this->api->get('/auth/me/', $jeton);

        if (!ApiClient::estSucces($profil)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($profil);
            Auth::rediriger('/connexion');
            return;
        }

        Auth::connecter($jeton, $profil['corps']);

        // Le personnel arrive directement sur le back-office, les autres sur
        // l'accueil : chacun sur l'espace qui le concerne.
        Auth::rediriger(Auth::estStaff($this->config) ? '/back' : '/');
    }

    public function deconnexion(): void
    {
        Auth::deconnecter();
        Auth::rediriger('/');
    }
}
