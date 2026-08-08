<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Back-office : les collectes.
 *
 * Le sujet demande de "gerer le systeme des collectes". Une collecte concerne
 * SOIT un commercant, SOIT un particulier -- jamais les deux, jamais aucun
 * des deux. C'est l'API qui fait respecter cette regle ; l'ecran se contente
 * d'afficher la bonne source selon le cas.
 */
class CollectesController
{
    private ApiClient $api;
    private array $config;

    private const STATUTS = ['demandee', 'planifiee', 'realisee', 'annulee'];

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /back/collectes[?statut=...]
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

        $chemin = '/collectes/' . ($statut !== '' ? '?statut=' . urlencode($statut) : '');
        $collectes = $this->extraire($this->api->get($chemin, Auth::jeton()));

        $tous = $statut === ''
            ? $collectes
            : $this->extraire($this->api->get('/collectes/', Auth::jeton()));

        // Le nom du commercant n'est pas dans la reponse : la route ne renvoie
        // qu'un commercant_id. On charge donc la liste une fois et on fabrique
        // un index id -> raison sociale, plutot que d'appeler l'API pour
        // chaque ligne du tableau (ce qui ferait dix requetes pour dix lignes).
        $commercants = [];
        foreach ($this->extraire($this->api->get('/commercants/', Auth::jeton())) as $c) {
            $commercants[$c['id']] = $c['raison_sociale'] ?? '';
        }

        $parStatut = array_fill_keys(self::STATUTS, 0);
        foreach ($tous as $c) {
            $cle = $c['statut'] ?? '';
            if (isset($parStatut[$cle])) {
                $parStatut[$cle]++;
            }
        }

        $onglets = [[
            'libelle' => Langue::t('commun.tous'),
            'url' => '/back/collectes',
            'compteur' => count($tous),
            'actif' => $statut === '',
        ]];
        foreach (self::STATUTS as $s) {
            $onglets[] = [
                'libelle' => Langue::t('collectes.statut_' . $s),
                'url' => '/back/collectes?statut=' . $s,
                'compteur' => $parStatut[$s],
                'actif' => $statut === $s,
            ];
        }

        Vue::afficher('back/collectes', [
            'config' => $this->config,
            'collectes' => $collectes,
            'commercants' => $commercants,
        ], Langue::t('menu.collectes'), [
            'sous_titre' => $parStatut['demandee'] > 0
                ? $parStatut['demandee'] . ' ' . Langue::t('collectes.demandes_attente')
                : '',
            'onglets' => $onglets,
        ]);
    }

    /**
     * GET /back/collectes/@id
     *
     * Le detail sert surtout a SCANNER les produits ramasses pendant la
     * collecte : c'est le geste principal sur le terrain.
     */
    public function detail(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;

        $reponse = $this->api->get('/collectes/' . $id, Auth::jeton());
        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            Auth::rediriger('/back/collectes');
            return;
        }

        $collecte = $reponse['corps'];
        $produits = $this->extraire($this->api->get('/collectes/' . $id . '/produits', Auth::jeton()));
        $emplacements = $this->extraire($this->api->get('/emplacements/', Auth::jeton()));

        // La source : un commercant ou un particulier, selon ce qui est rempli.
        $source = $collecte['particulier_nom'] ?? '';
        if (!empty($collecte['commercant_id'])) {
            $c = $this->api->get('/commercants/' . (int) $collecte['commercant_id'], Auth::jeton());
            if (ApiClient::estSucces($c)) {
                $source = $c['corps']['raison_sociale'] ?? '';
            }
        }

        $date = !empty($collecte['date_prevue'])
            ? date('d/m/Y', strtotime($collecte['date_prevue']))
            : '';

        Vue::afficher('back/collecte_detail', [
            'config' => $this->config,
            'collecte' => $collecte,
            'produits' => $produits,
            'emplacements' => $emplacements,
            'source' => $source,
            'date' => $date,
        ], Langue::t('collectes.titre_detail') . ' ' . $date, [
            'fil' => [
                ['libelle' => Langue::t('menu.collectes'), 'url' => '/back/collectes'],
                ['libelle' => $date, 'url' => null],
            ],
            'sous_titre' => $source . ' · ' . Langue::t('collectes.statut_' . ($collecte['statut'] ?? 'demandee')),
            'actions' => [[
                'libelle' => Langue::t('commun.retour'),
                'url' => '/back/collectes',
                'style' => 'light',
                'icone' => 'bi-arrow-left',
            ]],
            'recherche' => false,
        ]);
    }

    /**
     * POST /back/collectes/@id
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
                    $_SESSION['message_erreur'] = Langue::t('collectes.statut_invalide');
                    break;
                }
                // Passer a "realisee" remplit date_realisee automatiquement
                // cote API : le front n'a pas a envoyer la date.
                $this->message(
                    $this->api->put('/collectes/' . $id, ['statut' => $statut], Auth::jeton()),
                    'collectes.statut_change'
                );
                break;

            case 'scanner':
                $this->scanner($id);
                break;

            default:
                $_SESSION['message_erreur'] = Langue::t('commun.action_inconnue');
        }

        Auth::rediriger('/back/collectes/' . $id);
    }

    /**
     * Enregistre un produit ramasse pendant la collecte.
     *
     * La route de l'API cree le produit ET le rattache a la collecte en une
     * seule operation : c'est exactement le geste du terrain (on scanne, le
     * produit entre en stock et il est lie a la collecte en cours).
     */
    private function scanner(int $id): void
    {
        $codeBarre = trim($_POST['code_barre'] ?? '');
        $libelle = trim($_POST['libelle'] ?? '');

        if ($codeBarre === '' || $libelle === '') {
            $_SESSION['message_erreur'] = Langue::t('collectes.scan_incomplet');
            return;
        }

        $produit = [
            'code_barre' => $codeBarre,
            'libelle' => $libelle,
            'statut' => 'en_stock',
        ];

        // Champs facultatifs : on ne les envoie que s'ils sont remplis, pour
        // ne pas transmettre des chaines vides la ou l'API attend une date ou
        // un nombre.
        foreach (['categorie', 'dlc'] as $champ) {
            $valeur = trim($_POST[$champ] ?? '');
            if ($valeur !== '') {
                $produit[$champ] = $valeur;
            }
        }
        $quantite = (int) ($_POST['quantite'] ?? 0);
        if ($quantite > 0) {
            $produit['quantite'] = $quantite;
        }
        $emplacement = (int) ($_POST['emplacement_id'] ?? 0);
        if ($emplacement > 0) {
            $produit['emplacement_id'] = $emplacement;
        }

        $this->message(
            $this->api->post('/collectes/' . $id . '/produits', $produit, Auth::jeton()),
            'collectes.produit_ajoute'
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
