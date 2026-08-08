<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Back-office : les tournees de distribution.
 *
 * Le sujet demande de "gerer les tournees de distribution (associations
 * caritatives, particuliers en detresse...)" et precise que "chaque livraison
 * donnera lieu a l'emission d'un recapitulatif au format PDF".
 *
 * La cloture d'une livraison est l'operation la plus riche du projet : elle
 * enchaine cinq etapes cote API, dont le passage des produits livres au
 * statut "distribue".
 */
class TourneesController
{
    private ApiClient $api;
    private array $config;

    private const STATUTS = ['planifiee', 'en_cours', 'terminee', 'annulee'];

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /back/tournees[?statut=...]
     */
    public function liste(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $statut = $_GET['statut'] ?? '';
        if (!in_array($statut, self::STATUTS, true)) {
            $statut = '';
        }

        $chemin = '/tournees/' . ($statut !== '' ? '?statut=' . urlencode($statut) : '');
        $tournees = $this->extraire($this->api->get($chemin, Auth::jeton()));

        $tous = $statut === ''
            ? $tournees
            : $this->extraire($this->api->get('/tournees/', Auth::jeton()));

        // Le nom du chauffeur n'est pas dans la reponse : on indexe les
        // benevoles une fois, plutot que d'appeler l'API par ligne.
        $benevoles = [];
        foreach ($this->extraire($this->api->get('/benevoles/', Auth::jeton())) as $b) {
            $benevoles[$b['id']] = trim(($b['prenom'] ?? '') . ' ' . ($b['nom'] ?? ''));
        }

        $parStatut = array_fill_keys(self::STATUTS, 0);
        foreach ($tous as $t) {
            $cle = $t['statut'] ?? '';
            if (isset($parStatut[$cle])) {
                $parStatut[$cle]++;
            }
        }

        $onglets = [[
            'libelle' => Langue::t('commun.tous'),
            'url' => '/back/tournees',
            'compteur' => count($tous),
            'actif' => $statut === '',
        ]];
        foreach (self::STATUTS as $s) {
            $onglets[] = [
                'libelle' => Langue::t('tournees.statut_' . $s),
                'url' => '/back/tournees?statut=' . $s,
                'compteur' => $parStatut[$s],
                'actif' => $statut === $s,
            ];
        }

        Vue::afficher('back/tournees', [
            'config' => $this->config,
            'tournees' => $tournees,
            'benevoles' => $benevoles,
        ], Langue::t('menu.tournees'), [
            'sous_titre' => $parStatut['en_cours'] > 0
                ? $parStatut['en_cours'] . ' ' . Langue::t('tournees.en_cours_aujourdhui')
                : '',
            'onglets' => $onglets,
        ]);
    }

    /**
     * GET /back/tournees/@id
     *
     * Le detail d'une tournee : ses arrets dans l'ordre de passage, et pour
     * chacun soit le bouton de cloture, soit le lien vers le PDF.
     */
    public function detail(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;

        $reponse = $this->api->get('/tournees/' . $id, Auth::jeton());
        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            Auth::rediriger('/back/tournees');
            return;
        }

        $tournee = $reponse['corps'];
        $etapes = $this->extraire($this->api->get('/tournees/' . $id . '/etapes', Auth::jeton()));

        // Les noms des beneficiaires, indexes une fois.
        $beneficiaires = [];
        foreach ($this->extraire($this->api->get('/beneficiaires/', Auth::jeton())) as $b) {
            $beneficiaires[$b['id']] = $b;
        }

        // Le chauffeur.
        $chauffeur = '';
        if (!empty($tournee['benevole_id'])) {
            $b = $this->api->get('/benevoles/' . (int) $tournee['benevole_id'], Auth::jeton());
            if (ApiClient::estSucces($b)) {
                $chauffeur = trim(($b['corps']['prenom'] ?? '') . ' ' . ($b['corps']['nom'] ?? ''));
            }
        }

        // Les produits disponibles pour une livraison : uniquement ceux qui
        // sont encore en stock. Proposer un produit deja distribue n'aurait
        // aucun sens, et l'API le refuserait.
        $produits = $this->extraire($this->api->get('/produits/?statut=en_stock', Auth::jeton()));

        // La progression, calculee ici pour que la vue n'ait qu'a l'afficher.
        $livrees = 0;
        foreach ($etapes as $e) {
            if (($e['statut'] ?? '') === 'livre') {
                $livrees++;
            }
        }

        $date = !empty($tournee['date_tournee'])
            ? date('d/m/Y', strtotime($tournee['date_tournee']))
            : '';

        Vue::afficher('back/tournee_detail', [
            'config' => $this->config,
            'tournee' => $tournee,
            'etapes' => $etapes,
            'beneficiaires' => $beneficiaires,
            'produits' => $produits,
            'chauffeur' => $chauffeur,
            'date' => $date,
            'livrees' => $livrees,
            'total' => count($etapes),
        ], Langue::t('tournees.titre_detail') . ' ' . $date, [
            'fil' => [
                ['libelle' => Langue::t('menu.tournees'), 'url' => '/back/tournees'],
                ['libelle' => $date, 'url' => null],
            ],
            'sous_titre' => ($chauffeur !== '' ? $chauffeur . ' · ' : '')
                . Langue::t('tournees.statut_' . ($tournee['statut'] ?? 'planifiee')),
            'actions' => [[
                'libelle' => Langue::t('commun.retour'),
                'url' => '/back/tournees',
                'style' => 'light',
                'icone' => 'bi-arrow-left',
            ]],
            'recherche' => false,
        ]);
    }

    /**
     * GET /back/livraisons/@id/pdf — sert le recapitulatif PDF.
     *
     * POURQUOI PASSER PAR LE FRONT PLUTOT QUE POINTER SUR L'API
     *
     * Un lien <a href="/api/livraisons/1/pdf"> semble plus simple. Mais le
     * navigateur qui suit ce lien n'envoie AUCUN jeton : le JWT est range
     * dans la session PHP, pas dans un cookie que l'API saurait lire. La
     * reponse est donc un 401 "Jeton invalide" -- verifie.
     *
     * Le front sert donc de relais : il demande le PDF a l'API avec le jeton
     * de la session, puis le renvoie au navigateur. Au passage, la garde de
     * role s'applique aussi a ce telechargement.
     */
    public function pdf(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $reponse = $this->api->get('/livraisons/' . (int) $id . '/pdf', Auth::jeton());

        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            Auth::rediriger('/back/tournees');
            return;
        }

        // "inline" : le PDF s'ouvre dans le navigateur plutot que de se
        // telecharger. On veut le relire a l'ecran avant de l'imprimer pour
        // le faire signer.
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="recapitulatif-' . (int) $id . '.pdf"');

        // On envoie le corps BRUT : json_decode l'aurait transforme en null
        // (un PDF n'est pas du JSON), c'est pourquoi ApiClient conserve
        // toujours la reponse telle qu'elle est arrivee.
        echo $reponse['brut'];
        exit;
    }

    /**
     * POST /back/tournees/@id
     */
    public function traiter(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;
        $action = $_POST['action'] ?? '';

        switch ($action) {

            case 'changer_statut':
                $statut = $_POST['statut'] ?? '';
                if (!in_array($statut, self::STATUTS, true)) {
                    $_SESSION['message_erreur'] = Langue::t('tournees.statut_invalide');
                    break;
                }
                $this->message(
                    $this->api->put('/tournees/' . $id, ['statut' => $statut], Auth::jeton()),
                    'tournees.statut_change'
                );
                break;

            case 'cloturer':
                $this->cloturer();
                break;

            default:
                $_SESSION['message_erreur'] = Langue::t('commun.action_inconnue');
        }

        Auth::rediriger('/back/tournees/' . $id);
    }

    /**
     * Cloture la livraison d'un arret.
     *
     * Cote API, cette seule requete enchaine cinq operations : refus du
     * doublon (409), verification que tous les produits existent, creation de
     * la livraison, passage des produits au statut "distribue", et marquage
     * de l'arret avec l'heure reelle.
     *
     * C'est aussi ce qui rend le recapitulatif PDF disponible.
     */
    private function cloturer(): void
    {
        $etapeId = (int) ($_POST['etape_id'] ?? 0);

        // Le formulaire envoie des tableaux paralleles produit/quantite.
        $ids = $_POST['produit_id'] ?? [];
        $quantites = $_POST['quantite'] ?? [];

        $produits = [];
        foreach ($ids as $rang => $produitId) {
            $produitId = (int) $produitId;
            $quantite = (int) ($quantites[$rang] ?? 0);

            // On ignore les lignes vides : le formulaire en propose plusieurs,
            // toutes ne sont pas remplies.
            if ($produitId > 0 && $quantite > 0) {
                $produits[] = ['produit_id' => $produitId, 'quantite' => $quantite];
            }
        }

        if (empty($produits)) {
            $_SESSION['message_erreur'] = Langue::t('tournees.livraison_vide');
            return;
        }

        $this->message(
            $this->api->post('/tournee-etapes/' . $etapeId . '/livraison',
                ['produits' => $produits], Auth::jeton()),
            'tournees.livraison_cloturee'
        );
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
