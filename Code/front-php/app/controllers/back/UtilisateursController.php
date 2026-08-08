<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Back-office : les comptes et leurs roles.
 *
 * POURQUOI CET ECRAN EXISTE
 *
 * POST /auth/register cree toujours un "adherent" : le role y est ecrit en
 * dur. Creer un compte pour un membre du personnel imposait donc une requete
 * SQL a la main. Autrement dit, installer l'application sur un serveur neuf
 * demandait d'ouvrir un client PostgreSQL -- inacceptable pour un produit
 * "package pour pouvoir etre aisement deploye", comme le demande le sujet.
 *
 * L'API a comble ce trou (POST /utilisateurs/). Cet ecran le rend utilisable.
 */
class UtilisateursController
{
    private ApiClient $api;
    private array $config;

    /**
     * Les quatre roles de l'application, dans l'ordre des pouvoirs
     * decroissants. C'est la meme liste que celle de l'API -- recopiee, comme
     * les delais de rappel, faute d'une route qui l'exposerait.
     */
    private const ROLES = ['admin_back', 'staff_back', 'adherent', 'benevole'];

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /back/utilisateurs[?role=...]
     *
     * ATTENTION : cet ecran est reserve a admin_back, pas a staff_back.
     * Auth::exigerStaff() laisserait passer les deux. La verification du role
     * exact est donc faite ici, en plus.
     */
    public function liste(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        // L'API repondrait 403 de toute facon, mais un membre du personnel
        // verrait alors un message d'erreur brut au lieu d'une explication.
        if (Auth::role() !== ($this->config['role_admin_back'] ?? 'admin_back')) {
            $_SESSION['message_erreur'] = Langue::t('utilisateurs.reserve_admin');
            Auth::rediriger('/back');
            return;
        }

        $role = $_GET['role'] ?? '';
        if (!in_array($role, self::ROLES, true)) {
            $role = '';
        }

        $tous = $this->extraire($this->api->get('/utilisateurs/', Auth::jeton()));

        // L'API n'expose pas de filtre : on le fait ici, sur une liste qui
        // reste petite (les comptes d'une association).
        $utilisateurs = $role === ''
            ? $tous
            : array_values(array_filter($tous, function ($u) use ($role) {
                return ($u['role'] ?? '') === $role;
            }));

        $parRole = array_fill_keys(self::ROLES, 0);
        foreach ($tous as $u) {
            $cle = $u['role'] ?? '';
            if (isset($parRole[$cle])) {
                $parRole[$cle]++;
            }
        }

        $onglets = [[
            'libelle' => Langue::t('commun.tous'),
            'url' => '/back/utilisateurs',
            'compteur' => count($tous),
            'actif' => $role === '',
        ]];
        foreach (self::ROLES as $r) {
            $onglets[] = [
                'libelle' => Langue::t('utilisateurs.role_' . $r),
                'url' => '/back/utilisateurs?role=' . $r,
                'compteur' => $parRole[$r],
                'actif' => $role === $r,
            ];
        }

        Vue::afficher('back/utilisateurs', [
            'config' => $this->config,
            'utilisateurs' => $utilisateurs,
            'roles' => self::ROLES,
            'moi' => Auth::utilisateur()['email'] ?? '',
        ], Langue::t('menu.utilisateurs'), [
            'sous_titre' => Langue::t('utilisateurs.sous_titre'),
            'onglets' => $onglets,
            'recherche' => false,
        ]);
    }

    /**
     * POST /back/utilisateurs — creer un compte avec choix du role.
     */
    public function creer(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        if (Auth::role() !== ($this->config['role_admin_back'] ?? 'admin_back')) {
            $_SESSION['message_erreur'] = Langue::t('utilisateurs.reserve_admin');
            Auth::rediriger('/back');
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $role = $_POST['role'] ?? '';

        if ($email === '' || $motDePasse === '') {
            $_SESSION['message_erreur'] = Langue::t('utilisateurs.email_mdp_obligatoires');
            Auth::rediriger('/back/utilisateurs');
            return;
        }

        // Longueur minimale verifiee ici : l'API l'impose aussi, mais un
        // message immediat vaut mieux qu'un refus apres coup.
        if (strlen($motDePasse) < 8) {
            $_SESSION['message_erreur'] = Langue::t('utilisateurs.mdp_trop_court');
            Auth::rediriger('/back/utilisateurs');
            return;
        }

        // Le menu ne propose que des roles valides, mais rien n'empeche d'en
        // forger un autre. Sans cette verification, on creerait un compte
        // qu'AUCUNE garde ne reconnaitrait : son proprietaire serait refuse
        // partout sans que personne comprenne pourquoi.
        if (!in_array($role, self::ROLES, true)) {
            $_SESSION['message_erreur'] = Langue::t('utilisateurs.role_invalide');
            Auth::rediriger('/back/utilisateurs');
            return;
        }

        $this->message(
            $this->api->post('/utilisateurs/', [
                'email' => $email,
                'mot_de_passe' => $motDePasse,
                'role' => $role,
            ], Auth::jeton()),
            'utilisateurs.cree'
        );

        Auth::rediriger('/back/utilisateurs');
    }

    private function extraire(array $reponse): array
    {
        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            return [];
        }

        return $reponse['corps'] ?? [];
    }

    private function message(array $reponse, string $cleSucces): void
    {
        if (ApiClient::estSucces($reponse)) {
            $_SESSION['message_succes'] = Langue::t($cleSucces);
        } else {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
        }
    }
}
