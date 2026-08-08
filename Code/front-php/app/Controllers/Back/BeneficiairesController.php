<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Back-office : les beneficiaires des tournees de distribution.
 *
 * Le sujet les nomme explicitement : les tournees vont vers des "associations
 * caritatives, particuliers en detresse...".
 *
 * Jusqu'ici ils n'etaient creables que par l'API. Or on ne peut pas planifier
 * une tournee sans arret, et on ne peut pas creer un arret sans beneficiaire :
 * l'ecran des tournees dependait d'une donnee que le back-office ne savait pas
 * produire.
 */
class BeneficiairesController
{
    private ApiClient $api;
    private array $config;

    /**
     * Les deux types acceptes, ceux de la contrainte CHECK de la base.
     *
     * Menu deroulant et non champ libre : c'est la lecon de l'ecran des
     * services, ou un champ texte transformait une faute de frappe en erreur
     * serveur.
     */
    private const TYPES = ['association_caritative', 'particulier_detresse'];

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /back/beneficiaires[?type=...]
     */
    public function liste(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $type = $_GET['type'] ?? '';
        if (!in_array($type, self::TYPES, true)) {
            $type = '';
        }

        // L'API n'expose pas de filtre par type : on le fait ici, sur une
        // liste qui reste petite. C'est le meme compromis que le filtre par
        // ville des commercants.
        $tous = $this->extraire($this->api->get('/beneficiaires/', Auth::jeton()));

        $beneficiaires = $type === ''
            ? $tous
            : array_values(array_filter($tous, function ($b) use ($type) {
                return ($b['type'] ?? '') === $type;
            }));

        $parType = array_fill_keys(self::TYPES, 0);
        foreach ($tous as $b) {
            $cle = $b['type'] ?? '';
            if (isset($parType[$cle])) {
                $parType[$cle]++;
            }
        }

        $onglets = [[
            'libelle' => Langue::t('commun.tous'),
            'url' => '/back/beneficiaires',
            'compteur' => count($tous),
            'actif' => $type === '',
        ]];
        foreach (self::TYPES as $t) {
            $onglets[] = [
                'libelle' => Langue::t('beneficiaires.type_' . $t),
                'url' => '/back/beneficiaires?type=' . $t,
                'compteur' => $parType[$t],
                'actif' => $type === $t,
            ];
        }

        Vue::afficher('back/beneficiaires', [
            'config' => $this->config,
            'beneficiaires' => $beneficiaires,
            'types' => self::TYPES,
        ], Langue::t('menu.beneficiaires'), [
            'sous_titre' => Langue::t('beneficiaires.sous_titre'),
            'onglets' => $onglets,
            'recherche' => false,
        ]);
    }

    /**
     * POST /back/beneficiaires
     */
    public function creer(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $nom = trim($_POST['nom'] ?? '');
        $type = trim($_POST['type'] ?? '');

        if ($nom === '' || $type === '') {
            $_SESSION['message_erreur'] = Langue::t('beneficiaires.nom_type_obligatoires');
            Auth::rediriger('/back/beneficiaires');
            return;
        }

        // Le menu ne propose que des valeurs valides, mais rien n'empeche
        // d'en forger une autre. Sans cette verification, la base repondrait
        // par une violation de contrainte -- desormais traduite en 400, mais
        // avec un message bien moins clair que celui-ci.
        if (!in_array($type, self::TYPES, true)) {
            $_SESSION['message_erreur'] = Langue::t('beneficiaires.type_invalide');
            Auth::rediriger('/back/beneficiaires');
            return;
        }

        $beneficiaire = ['nom' => $nom, 'type' => $type];

        // Champs facultatifs : on ne transmet que ce qui est rempli.
        foreach (['adresse', 'ville', 'telephone', 'contact'] as $champ) {
            $valeur = trim($_POST[$champ] ?? '');
            if ($valeur !== '') {
                $beneficiaire[$champ] = $valeur;
            }
        }

        $this->message(
            $this->api->post('/beneficiaires/', $beneficiaire, Auth::jeton()),
            'beneficiaires.cree'
        );

        Auth::rediriger('/back/beneficiaires');
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
