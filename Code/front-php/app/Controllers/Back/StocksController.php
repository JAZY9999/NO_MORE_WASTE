<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Back-office : les stocks et leurs emplacements.
 *
 * Le sujet demande que chaque produit soit "reference (code barre)" puis
 * "stocke et retrouvable TRES RAPIDEMENT". La recherche par code-barre est
 * donc l'action principale de l'ecran : champ en tete, focus automatique.
 */
class StocksController
{
    private ApiClient $api;
    private array $config;

    private const STATUTS = ['en_stock', 'reserve', 'distribue', 'perime'];

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /back/stocks[?code_barre=...][?statut=...]
     *
     * Une seule route pour deux usages, comme la route de l'API :
     *   - avec un code-barre : la recherche exacte (le geste du terrain)
     *   - sans : la liste, filtrable par statut
     */
    public function liste(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $codeBarre = trim($_GET['code_barre'] ?? '');

        $statut = $_GET['statut'] ?? '';
        if (!in_array($statut, self::STATUTS, true)) {
            $statut = '';
        }

        // La recherche par code-barre l'emporte sur le filtre : quand on
        // scanne, on veut CE produit, pas la liste filtree.
        if ($codeBarre !== '') {
            $chemin = '/produits/?code_barre=' . urlencode($codeBarre);
        } elseif ($statut !== '') {
            $chemin = '/produits/?statut=' . urlencode($statut);
        } else {
            $chemin = '/produits/';
        }

        $reponse = $this->api->get($chemin, Auth::jeton());

        // Sur une recherche exacte, l'API renvoie UN objet et non une liste :
        // la route sert a deux choses. On normalise en tableau pour que la vue
        // n'ait qu'un seul cas a traiter.
        $produits = [];
        $introuvable = false;

        if (ApiClient::estSucces($reponse)) {
            $corps = $reponse['corps'] ?? [];
            if ($codeBarre !== '' && isset($corps['id'])) {
                $produits = [$corps];
            } elseif ($codeBarre === '') {
                $produits = $corps;
            }
        } elseif ($codeBarre !== '' && $reponse['code'] === 404) {
            // Un code-barre inconnu n'est pas une erreur de l'application :
            // c'est une information utile pour le benevole qui scanne.
            $introuvable = true;
        } else {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
        }

        // Les emplacements servent a afficher "ou" est rangé chaque produit.
        $emplacements = [];
        foreach ($this->extraire($this->api->get('/emplacements/', Auth::jeton())) as $e) {
            $emplacements[$e['id']] = trim(($e['entrepot'] ?? '') . ' · ' . ($e['zone'] ?? '')
                . '-' . ($e['rayon'] ?? '') . '-' . ($e['etagere'] ?? ''));
        }

        $onglets = [[
            'libelle' => Langue::t('commun.tous'),
            'url' => '/back/stocks',
            'compteur' => null,
            'actif' => $statut === '' && $codeBarre === '',
        ]];
        foreach (self::STATUTS as $s) {
            $onglets[] = [
                'libelle' => Langue::t('stocks.statut_' . $s),
                'url' => '/back/stocks?statut=' . $s,
                'compteur' => null,
                'actif' => $statut === $s,
            ];
        }

        Vue::afficher('back/stocks', [
            'config' => $this->config,
            'produits' => $produits,
            'emplacements' => $emplacements,
            'codeBarre' => $codeBarre,
            'introuvable' => $introuvable,
        ], Langue::t('menu.stocks'), [
            'sous_titre' => count($produits) . ' ' . Langue::t('stocks.produits'),
            'onglets' => $onglets,
            'actions' => [[
                'libelle' => Langue::t('menu.emplacements'),
                'url' => '/back/emplacements',
                'style' => 'light',
                'icone' => 'bi-geo-alt',
            ]],
            'recherche' => false,
        ]);
    }

    /**
     * POST /back/stocks/@id — deplacer un produit ou changer son statut.
     */
    public function traiter(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;
        $donnees = [];

        $statut = $_POST['statut'] ?? '';
        if (in_array($statut, self::STATUTS, true)) {
            $donnees['statut'] = $statut;
        }

        $emplacement = (int) ($_POST['emplacement_id'] ?? 0);
        if ($emplacement > 0) {
            $donnees['emplacement_id'] = $emplacement;
        }

        if (empty($donnees)) {
            $_SESSION['message_erreur'] = Langue::t('stocks.rien_a_modifier');
        } else {
            $this->message(
                $this->api->put('/produits/' . $id, $donnees, Auth::jeton()),
                'stocks.produit_modifie'
            );
        }

        Auth::rediriger('/back/stocks');
    }

    /**
     * GET /back/emplacements
     */
    public function emplacements(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $emplacements = $this->extraire($this->api->get('/emplacements/', Auth::jeton()));

        Vue::afficher('back/emplacements', [
            'config' => $this->config,
            'emplacements' => $emplacements,
        ], Langue::t('menu.emplacements'), [
            'fil' => [
                ['libelle' => Langue::t('menu.stocks'), 'url' => '/back/stocks'],
                ['libelle' => Langue::t('menu.emplacements'), 'url' => null],
            ],
            'sous_titre' => Langue::t('emplacements.sous_titre'),
            'recherche' => false,
        ]);
    }

    /**
     * POST /back/emplacements — creer un emplacement.
     */
    public function creerEmplacement(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $emplacement = [
            'entrepot' => trim($_POST['entrepot'] ?? ''),
            'zone' => trim($_POST['zone'] ?? ''),
            'rayon' => trim($_POST['rayon'] ?? ''),
            'etagere' => trim($_POST['etagere'] ?? ''),
        ];

        if ($emplacement['entrepot'] === '') {
            $_SESSION['message_erreur'] = Langue::t('emplacements.entrepot_obligatoire');
        } else {
            $this->message(
                $this->api->post('/emplacements/', $emplacement, Auth::jeton()),
                'emplacements.cree'
            );
        }

        Auth::rediriger('/back/emplacements');
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
