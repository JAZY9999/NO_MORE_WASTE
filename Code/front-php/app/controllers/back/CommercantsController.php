<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Back-office : gestion des commercants (item 2.4 de la todo).
 */
class CommercantsController
{
    private ApiClient $api;
    private array $config;

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    public function liste(): void
    {
        // Garde de role : meme principe que utils.RequireRole cote Go.
        // Appel explicite en premiere ligne, avant toute autre chose.
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $reponse = $this->api->get('/commercants/', Auth::jeton());

        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            $commercants = [];
        } else {
            // L'API renvoie "null" plutot qu'un tableau vide quand il n'y a
            // aucun resultat (comportement de Go pour une slice non initialisee).
            // On normalise ici pour que la vue n'ait pas a s'en soucier.
            $commercants = $reponse['corps'] ?? [];
        }

        // --- Filtre par ville ---
        //
        // Le filtre est applique cote PHP et non cote API, parce que l'API
        // n'expose pas (encore) de parametre "ville" sur cette route. Pour le
        // volume de donnees d'une association, c'est sans consequence ; si la
        // liste devenait tres grande, il faudrait filtrer cote API pour eviter
        // de tout transferer a chaque affichage.
        $villeFiltre = trim($_GET['ville'] ?? '');

        // On construit la liste des villes AVANT de filtrer, sinon le menu
        // deroulant ne contiendrait plus que la ville selectionnee et on ne
        // pourrait plus en changer.
        $villes = [];
        foreach ($commercants as $c) {
            $ville = $c['ville'] ?? '';
            if ($ville !== '' && !in_array($ville, $villes, true)) {
                $villes[] = $ville;
            }
        }
        sort($villes);

        if ($villeFiltre !== '') {
            $commercants = array_values(array_filter(
                $commercants,
                fn($c) => ($c['ville'] ?? '') === $villeFiltre
            ));
        }

        Vue::afficher('back/commercants', [
            'config' => $this->config,
            'commercants' => $commercants,
            'villeFiltre' => $villeFiltre,
            'villes' => $villes,
        ], Langue::t('commercants.titre'), [
            'sous_titre' => count($commercants) . ' ' . Langue::t('commercants.total'),
            'actions' => [[
                'libelle' => Langue::t('commercants.nouveau'),
                'url' => '/back/commercants/nouveau',
                'style' => 'primary',
                'icone' => 'bi-plus-lg',
            ]],
        ]);
    }

    /**
     * GET /back/commercants/nouveau
     *
     * Route declaree AVANT /back/commercants/@id : sans cela, FlightPHP
     * prendrait "nouveau" pour un identifiant.
     */
    public function formulaireCreation(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        Vue::afficher('back/commercant_nouveau', [
            'config' => $this->config,
            'comptes' => $this->comptesRattachables(),
            'saisie' => $_SESSION['commercant_saisie'] ?? [],
        ], Langue::t('commercants.nouveau'), [
            'fil' => [
                ['libelle' => Langue::t('commercants.titre'), 'url' => '/back/commercants'],
                ['libelle' => Langue::t('commercants.nouveau'), 'url' => null],
            ],
            'recherche' => false,
        ]);

        unset($_SESSION['commercant_saisie']);
    }

    /**
     * POST /back/commercants — creer une boutique.
     */
    public function creer(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $raisonSociale = trim($_POST['raison_sociale'] ?? '');

        if ($raisonSociale === '') {
            $_SESSION['message_erreur'] = Langue::t('commercants.raison_sociale_obligatoire');
            $_SESSION['commercant_saisie'] = $_POST;
            Auth::rediriger('/back/commercants/nouveau');
            return;
        }

        $donnees = $this->champsRemplis();
        $donnees['raison_sociale'] = $raisonSociale;

        // Le rattachement du compte se fait des la creation quand il est
        // choisi. C'est le cas le plus courant : le commercant s'est inscrit
        // en ligne, le personnel enregistre sa boutique dans la foulee.
        // 0 = aucun compte, et on ne transmet alors rien.
        $utilisateurId = (int) ($_POST['utilisateur_id'] ?? 0);
        if ($utilisateurId > 0) {
            $donnees['utilisateur_id'] = $utilisateurId;
        }

        $reponse = $this->api->post('/commercants/', $donnees, Auth::jeton());

        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            $_SESSION['commercant_saisie'] = $_POST;
            Auth::rediriger('/back/commercants/nouveau');
            return;
        }

        $_SESSION['message_succes'] = Langue::t('commercants.cree');
        Auth::rediriger('/back/commercants/' . (int) ($reponse['corps']['id'] ?? 0));
    }

    /**
     * GET /back/commercants/@id — la fiche d'une boutique.
     */
    public function detail(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;

        $reponse = $this->api->get('/commercants/' . $id, Auth::jeton());
        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            Auth::rediriger('/back/commercants');
            return;
        }

        $commercant = $reponse['corps'];

        // Ses adhesions : l'API ne les expose que dans la liste globale, on y
        // retrouve celles de cette boutique. C'est l'information qui dit si le
        // partenaire est a jour -- la premiere question qu'on se pose en
        // ouvrant sa fiche.
        $adhesions = [];
        foreach ($this->extraire($this->api->get('/adhesions/', Auth::jeton())) as $a) {
            if ((int) ($a['commercant_id'] ?? 0) === $id) {
                $adhesions[] = $a;
            }
        }

        // Ses collectes, filtrees de la meme facon.
        $collectes = [];
        foreach ($this->extraire($this->api->get('/collectes/', Auth::jeton())) as $c) {
            if ((int) ($c['commercant_id'] ?? 0) === $id) {
                $collectes[] = $c;
            }
        }

        Vue::afficher('back/commercant_detail', [
            'config' => $this->config,
            'commercant' => $commercant,
            'adhesions' => $adhesions,
            'collectes' => $collectes,
            'comptes' => $this->comptesRattachables((int) ($commercant['utilisateur_id'] ?? 0)),
        ], $commercant['raison_sociale'] ?? Langue::t('commercants.titre'), [
            'fil' => [
                ['libelle' => Langue::t('commercants.titre'), 'url' => '/back/commercants'],
                ['libelle' => $commercant['raison_sociale'] ?? '', 'url' => null],
            ],
            'sous_titre' => trim(($commercant['ville'] ?? '') . ' ' . ($commercant['pays'] ?? '')),
            'actions' => [[
                'libelle' => Langue::t('commun.retour'),
                'url' => '/back/commercants',
                'style' => 'light',
                'icone' => 'bi-arrow-left',
            ]],
            'recherche' => false,
        ]);
    }

    /**
     * POST /back/commercants/@id
     */
    public function traiter(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;

        switch ($_POST['action'] ?? '') {

            case 'modifier':
                $this->modifier($id);
                break;

            case 'rattacher':
                $this->rattacher($id);
                break;

            case 'creer_adhesion':
                $this->creerAdhesion($id);
                break;

            default:
                $_SESSION['message_erreur'] = Langue::t('commun.action_inconnue');
        }

        Auth::rediriger('/back/commercants/' . $id);
    }

    // -----------------------------------------------------------------

    private function modifier(int $id): void
    {
        $donnees = $this->champsRemplis();

        $raisonSociale = trim($_POST['raison_sociale'] ?? '');
        if ($raisonSociale !== '') {
            $donnees['raison_sociale'] = $raisonSociale;
        }

        if (empty($donnees)) {
            $_SESSION['message_erreur'] = Langue::t('commercants.rien_a_modifier');
            return;
        }

        $this->message(
            $this->api->put('/commercants/' . $id, $donnees, Auth::jeton()),
            'commercants.modifie'
        );
    }

    /**
     * Rattache (ou detache) le compte de connexion du commercant.
     *
     * C'est ce qui rend l'espace client accessible a son proprietaire. Sans
     * cette action, une boutique enregistree sans compte restait definitivement
     * orpheline : son proprietaire se connectait et lisait "aucune boutique
     * rattachee a votre compte", sans recours.
     */
    private function rattacher(int $id): void
    {
        // 0 = "aucun compte". C'est la valeur que le menu envoie pour
        // detacher, et l'API la traduit en NULL.
        $utilisateurId = (int) ($_POST['utilisateur_id'] ?? 0);

        $this->message(
            $this->api->put('/commercants/' . $id, ['utilisateur_id' => $utilisateurId], Auth::jeton()),
            $utilisateurId > 0 ? 'commercants.compte_rattache' : 'commercants.compte_detache'
        );
    }

    private function creerAdhesion(int $id): void
    {
        $debut = trim($_POST['date_debut'] ?? '');
        $fin = trim($_POST['date_fin'] ?? '');

        if ($debut === '' || $fin === '') {
            $_SESSION['message_erreur'] = Langue::t('commercants.dates_obligatoires');
            return;
        }

        // Verifie ici parce que l'erreur est evidente pour l'utilisateur : il
        // s'est trompe d'annee. Un refus venu de la base serait moins clair.
        if ($fin <= $debut) {
            $_SESSION['message_erreur'] = Langue::t('commercants.dates_incoherentes');
            return;
        }

        $adhesion = ['date_debut' => $debut, 'date_fin' => $fin, 'statut' => 'active'];

        $montant = trim($_POST['montant_cotisation'] ?? '');
        if ($montant !== '') {
            $adhesion['montant_cotisation'] = $montant;
        }

        $this->message(
            $this->api->post('/commercants/' . $id . '/adhesions', $adhesion, Auth::jeton()),
            'commercants.adhesion_creee'
        );
    }

    /**
     * Les champs facultatifs reellement remplis.
     *
     * Partages par la creation et la modification : les deux ecrans ont les
     * memes champs, et la regle est la meme -- ne pas transmettre une chaine
     * vide la ou l'absence de valeur veut dire quelque chose.
     */
    private function champsRemplis(): array
    {
        $donnees = [];

        foreach (['siret', 'adresse', 'ville', 'pays', 'email', 'telephone', 'contact_nom'] as $champ) {
            $valeur = trim($_POST[$champ] ?? '');
            if ($valeur !== '') {
                $donnees[$champ] = $valeur;
            }
        }

        return $donnees;
    }

    /**
     * Les comptes qu'on peut rattacher a une boutique.
     *
     * Uniquement les ADHERENTS : rattacher un compte de personnel n'aurait
     * aucun sens, et rattacher un benevole non plus -- les roles decident de
     * l'espace auquel on accede.
     *
     * $compteActuel est conserve dans la liste meme s'il est deja pris, sinon
     * le menu de la fiche afficherait "aucun compte" alors qu'il y en a un.
     */
    private function comptesRattachables(int $compteActuel = 0): array
    {
        $comptes = [];

        foreach ($this->extraire($this->api->get('/utilisateurs/', Auth::jeton())) as $u) {
            if (($u['role'] ?? '') === 'adherent' || (int) ($u['id'] ?? 0) === $compteActuel) {
                $comptes[] = $u;
            }
        }

        return $comptes;
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
