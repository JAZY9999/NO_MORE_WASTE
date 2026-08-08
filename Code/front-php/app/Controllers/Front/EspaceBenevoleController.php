<?php

namespace App\Controllers\Front;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * L'espace d'un benevole.
 *
 * Le sujet dit qu'un benevole s'inscrit "a condition de valider un certain
 * nombre de conditions". Un benevole bloque au statut "candidat" doit pouvoir
 * voir QUEL justificatif manque -- sinon il ne comprend pas pourquoi on ne lui
 * confie aucune mission, et il abandonne.
 *
 * C'est la raison d'etre de cet ecran, plus encore que le planning.
 */
class EspaceBenevoleController
{
    private ApiClient $api;
    private array $config;

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /mon-espace/benevole
     */
    public function index(): void
    {
        if (!Auth::exigerBenevole($this->config)) {
            return;
        }

        $reponse = $this->api->get('/mon-espace/benevole', Auth::jeton());

        // Meme cas que pour le commercant : compte legitime, fiche absente.
        if ($reponse['code'] === 404) {
            Vue::afficher('front/espace_benevole_sans_fiche', [
                'config' => $this->config,
            ], Langue::t('espace.titre_benevole'));
            return;
        }

        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            Auth::rediriger('/');
            return;
        }

        $corps = $reponse['corps'] ?? [];
        $benevole = $corps['benevole'] ?? [];
        $documents = $corps['documents'] ?? [];
        $competences = $corps['competences'] ?? [];

        // Le planning n'existe que pour un benevole valide : un candidat n'est
        // affecte a rien. On evite donc un appel qui rendrait toujours une
        // liste vide.
        $planning = [];
        if (($benevole['statut'] ?? '') === 'valide') {
            $planning = $this->extraire($this->api->get('/mon-espace/planning', Auth::jeton()));
        }

        // La progression des justificatifs, calculee ici : c'est elle qui
        // explique le statut, et la vue ne doit avoir qu'a l'afficher.
        $valides = 0;
        foreach ($documents as $d) {
            if (!empty($d['valide'])) {
                $valides++;
            }
        }

        Vue::afficher('front/espace_benevole', [
            'config' => $this->config,
            'benevole' => $benevole,
            'documents' => $documents,
            'competences' => $competences,
            'planning' => $planning,
            'documentsValides' => $valides,
            'documentsTotal' => count($documents),
        ], Langue::t('espace.titre_benevole'));
    }

    private function extraire(array $reponse): array
    {
        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            return [];
        }

        return $reponse['corps'] ?? [];
    }
}
