<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Services\Traductions;
use App\Vue;

/**
 * Back-office : gestion du multilingue.
 *
 * C'est l'ecran qui rend le site reellement multilingue "cote gestion" : les
 * libelles ne sont plus figes dans le code, ils se modifient depuis
 * l'interface, sans redeployer l'application.
 */
class TraductionsController
{
    private ApiClient $api;
    private array $config;

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /** GET /back/traductions */
    public function index(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $jeton = Auth::jeton();

        $reponseLangues = $this->api->get('/langues/', $jeton);
        $langues = ApiClient::estSucces($reponseLangues) ? ($reponseLangues['corps'] ?? []) : [];

        // Filtre par langue (?langue=it). Vide = toutes.
        $langueFiltre = strtolower(trim($_GET['langue'] ?? ''));
        $chemin = '/traductions/';
        if ($langueFiltre !== '') {
            $chemin .= '?langue=' . urlencode($langueFiltre);
        }

        $reponse = $this->api->get($chemin, $jeton);
        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            $traductions = [];
        } else {
            $traductions = $reponse['corps'] ?? [];
        }

        // Recherche libre sur la cle ou la valeur, cote PHP.
        // Avec quelques centaines de libelles, c'est instantane et ca evite
        // d'ajouter un parametre de recherche a l'API.
        $recherche = trim($_GET['q'] ?? '');
        if ($recherche !== '') {
            $traductions = array_values(array_filter(
                $traductions,
                function ($t) use ($recherche) {
                    return stripos($t['cle'] ?? '', $recherche) !== false
                        || stripos($t['valeur'] ?? '', $recherche) !== false;
                }
            ));
        }

        // Regroupement par cle : une ligne du tableau = une cle, avec une
        // colonne par langue. C'est bien plus lisible que 4 lignes separees
        // pour le meme libelle, et on repere d'un coup d'oeil les traductions
        // manquantes (cellule vide).
        $parCle = [];
        foreach ($traductions as $t) {
            $cle = $t['cle'] ?? '';
            if ($cle === '') {
                continue;
            }
            $parCle[$cle][$t['code_langue'] ?? ''] = [
                'id' => $t['id'] ?? null,
                'valeur' => $t['valeur'] ?? '',
            ];
        }
        ksort($parCle);

        Vue::afficher('back/traductions', [
            'config' => $this->config,
            'langues' => $langues,
            'parCle' => $parCle,
            'langueFiltre' => $langueFiltre,
            'recherche' => $recherche,
            'total' => count($parCle),
        ], Langue::t('traductions.titre'));
    }

    /** POST /back/traductions — toutes les actions de l'ecran. */
    public function traiter(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $jeton = Auth::jeton();
        $action = $_POST['action'] ?? '';

        switch ($action) {

            case 'exporter':
                // Base -> fichiers JSON. A faire apres avoir modifie des libelles.
                $resultat = (new Traductions($this->api))->exporter($jeton);
                $this->message($resultat);
                break;

            case 'importer':
                // Fichiers JSON -> base.
                $resultat = (new Traductions($this->api))->importer($jeton);
                $this->message($resultat);
                break;

            case 'creer':
                $reponse = $this->api->post('/traductions/', [
                    'cle' => trim($_POST['cle'] ?? ''),
                    'valeur' => trim($_POST['valeur'] ?? ''),
                    'code_langue' => trim($_POST['code_langue'] ?? ''),
                ], $jeton);
                $this->messageApi($reponse, "Libelle ajoute.");
                break;

            case 'modifier':
                $id = (int) ($_POST['id'] ?? 0);
                $reponse = $this->api->put('/traductions/' . $id, [
                    'cle' => trim($_POST['cle'] ?? ''),
                    'valeur' => trim($_POST['valeur'] ?? ''),
                    'code_langue' => trim($_POST['code_langue'] ?? ''),
                ], $jeton);
                $this->messageApi($reponse, "Libelle modifie.");
                break;

            case 'supprimer':
                $id = (int) ($_POST['id'] ?? 0);
                $reponse = $this->api->delete('/traductions/' . $id, $jeton);
                $this->messageApi($reponse, "Libelle supprime.");
                break;

            case 'creer_langue':
                $reponse = $this->api->post('/langues/', [
                    'code' => trim($_POST['code'] ?? ''),
                    'libelle' => trim($_POST['libelle'] ?? ''),
                ], $jeton);
                $this->messageApi($reponse, "Langue ajoutee. Pensez a saisir ses libelles.");
                break;

            case 'supprimer_langue':
                $code = trim($_POST['code'] ?? '');
                $reponse = $this->api->delete('/langues/' . urlencode($code), $jeton);
                $this->messageApi($reponse, "Langue supprimee, ainsi que ses libelles.");
                break;

            default:
                $_SESSION['message_erreur'] = "Action inconnue.";
        }

        // On redirige apres traitement plutot que d'afficher directement la
        // page. Sans ca, un rafraichissement du navigateur renverrait le
        // formulaire une seconde fois (et recreerait le libelle).
        Auth::rediriger('/back/traductions');
    }

    /** Depose en session le message d'un resultat de synchronisation. */
    private function message(array $resultat): void
    {
        $cle = $resultat['succes'] ? 'message_succes' : 'message_erreur';
        $_SESSION[$cle] = $resultat['message'];
    }

    /** Depose en session le message correspondant a une reponse de l'API. */
    private function messageApi(array $reponse, string $succes): void
    {
        if (ApiClient::estSucces($reponse)) {
            $_SESSION['message_succes'] = $succes;
        } else {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
        }
    }
}
