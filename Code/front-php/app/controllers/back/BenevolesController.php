<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Back-office : les benevoles.
 *
 * C'est le module que le sujet detaille le plus : candidature, validation
 * sous conditions, competences. La regle centrale -- "un benevole ne peut
 * etre valide que si TOUS ses documents le sont" -- est appliquee par l'API ;
 * l'ecran se contente de la rendre visible AVANT que l'utilisateur clique,
 * plutot que de le laisser se prendre un refus.
 */
class BenevolesController
{
    private ApiClient $api;
    private array $config;

    /** Les statuts que l'API accepte, dans l'ordre du parcours d'un benevole. */
    private const STATUTS = ['candidat', 'en_validation', 'valide', 'refuse', 'inactif'];

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /back/benevoles[?statut=...]
     */
    public function liste(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        // On ne fait pas confiance au parametre d'URL : un statut inconnu
        // serait transmis tel quel a l'API. On le compare a la liste connue.
        $statut = $_GET['statut'] ?? '';
        if (!in_array($statut, self::STATUTS, true)) {
            $statut = '';
        }

        // Le filtre est fait par l'API (elle expose ?statut=), contrairement
        // aux commercants ou il fallait le faire en PHP.
        $chemin = '/benevoles/' . ($statut !== '' ? '?statut=' . urlencode($statut) : '');

        $reponse = $this->api->get($chemin, Auth::jeton());
        $benevoles = $this->extraire($reponse);

        // Les compteurs des onglets demandent de connaitre TOUS les benevoles,
        // pas seulement ceux du filtre courant. Un second appel, sans filtre.
        $tous = $statut === ''
            ? $benevoles
            : $this->extraire($this->api->get('/benevoles/', Auth::jeton()));

        $parStatut = [];
        foreach (self::STATUTS as $s) {
            $parStatut[$s] = 0;
        }
        foreach ($tous as $b) {
            $cle = $b['statut'] ?? '';
            if (isset($parStatut[$cle])) {
                $parStatut[$cle]++;
            }
        }

        $onglets = [[
            'libelle' => Langue::t('commun.tous'),
            'url' => '/back/benevoles',
            'compteur' => count($tous),
            'actif' => $statut === '',
        ]];
        foreach (self::STATUTS as $s) {
            $onglets[] = [
                'libelle' => Langue::t('benevoles.statut_' . $s),
                'url' => '/back/benevoles?statut=' . $s,
                'compteur' => $parStatut[$s],
                'actif' => $statut === $s,
            ];
        }

        Vue::afficher('back/benevoles', [
            'config' => $this->config,
            'benevoles' => $benevoles,
        ], Langue::t('menu.benevoles'), [
            'sous_titre' => $parStatut['candidat'] > 0
                ? $parStatut['candidat'] . ' ' . Langue::t('benevoles.candidatures_attente')
                : '',
            'onglets' => $onglets,
        ]);
    }

    /**
     * GET /back/benevoles/@id
     *
     * Rassemble trois appels : la fiche, ses documents, ses competences.
     * Les documents conditionnent la validation, les competences conditionnent
     * les affectations : les trois se lisent ensemble.
     */
    public function detail(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;

        $reponse = $this->api->get('/benevoles/' . $id, Auth::jeton());
        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            Auth::rediriger('/back/benevoles');
            return;
        }

        $benevole = $reponse['corps'];
        $documents = $this->extraire($this->api->get('/benevoles/' . $id . '/documents', Auth::jeton()));
        $competences = $this->extraire($this->api->get('/benevoles/' . $id . '/competences', Auth::jeton()));
        $catalogue = $this->extraire($this->api->get('/competences/', Auth::jeton()));

        // La regle du sujet, calculee ici pour que la vue n'ait qu'a l'afficher :
        // tous les documents valides, et au moins un document fourni.
        $valides = 0;
        foreach ($documents as $d) {
            if (!empty($d['valide'])) {
                $valides++;
            }
        }
        $total = count($documents);
        $peutValider = ($total > 0 && $valides === $total);

        // Les competences que le benevole n'a pas encore, pour le menu d'ajout.
        $dejaIds = array_column($competences, 'id');
        $competencesRestantes = array_values(array_filter(
            $catalogue,
            fn($c) => !in_array($c['id'], $dejaIds, true)
        ));

        $nom = trim(($benevole['prenom'] ?? '') . ' ' . ($benevole['nom'] ?? ''));

        Vue::afficher('back/benevole_detail', [
            'config' => $this->config,
            'benevole' => $benevole,
            'documents' => $documents,
            'competences' => $competences,
            'competencesRestantes' => $competencesRestantes,
            'documentsValides' => $valides,
            'documentsTotal' => $total,
            'peutValider' => $peutValider,
        ], $nom, [
            'fil' => [
                ['libelle' => Langue::t('menu.benevoles'), 'url' => '/back/benevoles'],
                ['libelle' => $nom, 'url' => null],
            ],
            'sous_titre' => Langue::t('benevoles.statut_' . ($benevole['statut'] ?? 'candidat')),
            'actions' => [[
                'libelle' => Langue::t('commun.retour'),
                'url' => '/back/benevoles',
                'style' => 'light',
                'icone' => 'bi-arrow-left',
            ]],
            'recherche' => false,
        ]);
    }

    /**
     * POST /back/benevoles/@id
     *
     * Un seul point d'entree pour toutes les actions de la fiche, distinguees
     * par le champ "action". Evite d'ouvrir cinq routes POST pour cinq boutons.
     */
    public function traiter(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;
        $action = $_POST['action'] ?? '';
        $retour = '/back/benevoles/' . $id;

        switch ($action) {

            case 'valider_document':
                $doc = (int) ($_POST['document_id'] ?? 0);
                $this->message(
                    $this->api->put('/benevoles/' . $id . '/documents/' . $doc . '/validation', [], Auth::jeton()),
                    'benevoles.document_valide'
                );
                break;

            case 'valider':
            case 'refuser':
                $statut = ($action === 'valider') ? 'valide' : 'refuse';
                $this->message(
                    $this->api->put('/benevoles/' . $id . '/validation', ['statut' => $statut], Auth::jeton()),
                    'benevoles.statut_change'
                );
                break;

            case 'ajouter_competence':
                $competence = (int) ($_POST['competence_id'] ?? 0);
                $this->message(
                    $this->api->post('/benevoles/' . $id . '/competences/' . $competence, [], Auth::jeton()),
                    'benevoles.competence_ajoutee'
                );
                break;

            case 'retirer_competence':
                $competence = (int) ($_POST['competence_id'] ?? 0);
                $this->message(
                    $this->api->delete('/benevoles/' . $id . '/competences/' . $competence, Auth::jeton()),
                    'benevoles.competence_retiree'
                );
                break;

            default:
                $_SESSION['message_erreur'] = Langue::t('commun.action_inconnue');
        }

        // Redirection apres un POST : sans elle, rafraichir la page rejouerait
        // l'action (on validerait le document deux fois). C'est le motif
        // "POST puis redirection", deja utilise pour les traductions.
        Auth::rediriger($retour);
    }

    /**
     * Normalise la reponse d'une route de liste.
     *
     * Go renvoie "null" et non "[]" pour une slice vide : sans ce ?? [], un
     * foreach sur le resultat declencherait un avertissement PHP.
     */
    private function extraire(array $reponse): array
    {
        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            return [];
        }

        return $reponse['corps'] ?? [];
    }

    /** Depose un message de succes ou l'erreur renvoyee par l'API. */
    private function message(array $reponse, string $cleSucces): void
    {
        if (ApiClient::estSucces($reponse)) {
            $_SESSION['message_succes'] = Langue::t($cleSucces);
        } else {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
        }
    }
}
