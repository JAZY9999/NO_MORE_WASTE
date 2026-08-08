<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Back-office : les adhesions et leurs rappels de renouvellement.
 *
 * C'EST L'ECRAN LE PLUS IMPORTANT DE LA VAGUE 4
 *
 * Le sujet insiste sur le "rappel automatique de renouvellement" plus que sur
 * n'importe quelle autre fonctionnalite. Le mecanisme etait code et teste
 * depuis longtemps -- une goroutine qui tourne chaque jour -- mais il etait
 * INVISIBLE : impossible de le montrer en demonstration autrement qu'en
 * lisant les journaux du serveur.
 *
 * Cet ecran le rend visible et pilotable.
 */
class AdhesionsController
{
    private ApiClient $api;
    private array $config;

    private const STATUTS = ['active', 'expiree', 'resiliee', 'en_attente'];

    /**
     * Les delais du job de rappel, tels qu'ils sont ecrits dans
     * api-go/utils/scheduler.go.
     *
     * Ils sont RECOPIES ici, ce qui est un defaut assume : les changer
     * demande de toucher deux fichiers. La seule facon propre de l'eviter
     * serait une table de parametres et des routes pour la lire -- c'est la
     * piste d'amelioration notee dans la todo, pas une exigence du sujet.
     *
     * En attendant, mieux vaut afficher les vraies valeurs que de laisser
     * l'utilisateur ignorer quand partent les emails.
     */
    private const DELAIS = ['j30' => 30, 'j7' => 7, 'ex_abonne' => 180];

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /back/adhesions[?statut=...]
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

        $chemin = '/adhesions/' . ($statut !== '' ? '?statut=' . urlencode($statut) : '');
        $adhesions = $this->extraire($this->api->get($chemin, Auth::jeton()));

        $toutes = $statut === ''
            ? $adhesions
            : $this->extraire($this->api->get('/adhesions/', Auth::jeton()));

        Vue::afficher('back/adhesions', [
            'config' => $this->config,
            'adhesions' => $adhesions,
            'delais' => self::DELAIS,
            'compteurs' => $this->compteurs($toutes),
        ], Langue::t('menu.adhesions'), [
            'sous_titre' => Langue::t('adhesions.sous_titre'),
            'onglets' => $this->onglets($toutes, $statut),
            'recherche' => false,
        ]);
    }

    /**
     * GET /back/adhesions/@id — la fiche d'une adhesion et son historique.
     *
     * L'historique est la PREUVE que le rappel automatique fonctionne : il
     * montre quel type de rappel est parti, quand, et a quelle adresse.
     */
    public function detail(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;

        // L'API n'expose pas GET /adhesions/{id}. On retrouve donc la ligne
        // dans la liste complete plutot que d'ajouter une route pour un
        // ecran de detail qui affiche surtout... l'historique.
        $adhesion = null;
        foreach ($this->extraire($this->api->get('/adhesions/', Auth::jeton())) as $a) {
            if ((int) $a['id'] === $id) {
                $adhesion = $a;
                break;
            }
        }

        if ($adhesion === null) {
            $_SESSION['message_erreur'] = Langue::t('adhesions.introuvable');
            Auth::rediriger('/back/adhesions');
            return;
        }

        $historique = $this->extraire(
            $this->api->get('/adhesions/' . $id . '/historique-rappels', Auth::jeton())
        );

        // Le plus recent en premier : c'est le dernier rappel parti qui dit
        // s'il faut relancer a la main.
        usort($historique, function ($a, $b) {
            return strcmp($b['date_envoi'] ?? '', $a['date_envoi'] ?? '');
        });

        Vue::afficher('back/adhesion_detail', [
            'config' => $this->config,
            'adhesion' => $adhesion,
            'historique' => $historique,
            'delais' => self::DELAIS,
        ], Langue::t('adhesions.titre_detail'), [
            'fil' => [
                ['libelle' => Langue::t('menu.adhesions'), 'url' => '/back/adhesions'],
                ['libelle' => $adhesion['raison_sociale'] ?? '', 'url' => null],
            ],
            'sous_titre' => $adhesion['raison_sociale'] ?? '',
            'actions' => [[
                'libelle' => Langue::t('commun.retour'),
                'url' => '/back/adhesions',
                'style' => 'light',
                'icone' => 'bi-arrow-left',
            ]],
            'recherche' => false,
        ]);
    }

    /**
     * POST /back/adhesions — declencher le job de rappels.
     *
     * Le job tourne tout seul chaque jour. Ce bouton existe pour pouvoir le
     * DEMONTRER sans attendre l'heure dite -- et pour rejouer un envoi rate.
     */
    public function declencherJob(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $this->message(
            $this->api->post('/admin/jobs/rappels-adhesions/', [], Auth::jeton()),
            'adhesions.job_lance'
        );

        Auth::rediriger('/back/adhesions');
    }

    /**
     * POST /back/adhesions/@id — relancer un commercant a la main.
     */
    public function relancer(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;

        $this->message(
            $this->api->post('/adhesions/' . $id . '/relancer', [], Auth::jeton()),
            'adhesions.relance_envoyee'
        );

        Auth::rediriger('/back/adhesions/' . $id);
    }

    // -----------------------------------------------------------------

    /**
     * Les chiffres affiches en haut de l'ecran.
     *
     * On ne compte QUE ce que la liste contient reellement. La maquette
     * montrait aussi "rappels ce mois" : l'obtenir demanderait un appel
     * d'historique par adhesion. Trois chiffres justes valent mieux que
     * quatre dont un approximatif.
     */
    private function compteurs(array $adhesions): array
    {
        $actives = 0;
        $aRenouveler = 0;
        $expirees = 0;

        foreach ($adhesions as $a) {
            $jours = (int) ($a['jours_restants'] ?? 0);
            $statut = $a['statut'] ?? '';

            if ($statut === 'active') {
                $actives++;

                // Le seuil de 30 jours est celui du PREMIER rappel automatique.
                // Utiliser un autre chiffre ici ferait dire deux choses
                // differentes a l'ecran et aux emails.
                if ($jours >= 0 && $jours <= self::DELAIS['j30']) {
                    $aRenouveler++;
                }
            }

            // Une adhesion peut avoir depasse son echeance sans que le statut
            // ait ete mis a jour. On compte les deux cas.
            if ($statut === 'expiree' || $jours < 0) {
                $expirees++;
            }
        }

        return [
            'actives' => $actives,
            'a_renouveler' => $aRenouveler,
            'expirees' => $expirees,
        ];
    }

    private function onglets(array $toutes, string $statutActif): array
    {
        $parStatut = array_fill_keys(self::STATUTS, 0);
        foreach ($toutes as $a) {
            $cle = $a['statut'] ?? '';
            if (isset($parStatut[$cle])) {
                $parStatut[$cle]++;
            }
        }

        $onglets = [[
            'libelle' => Langue::t('commun.tous'),
            'url' => '/back/adhesions',
            'compteur' => count($toutes),
            'actif' => $statutActif === '',
        ]];

        foreach (self::STATUTS as $s) {
            $onglets[] = [
                'libelle' => Langue::t('adhesions.statut_' . $s),
                'url' => '/back/adhesions?statut=' . $s,
                'compteur' => $parStatut[$s],
                'actif' => $statutActif === $s,
            ];
        }

        return $onglets;
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
