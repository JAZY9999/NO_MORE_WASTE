<?php

namespace App\Controllers\Front;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * L'espace d'un commercant adherent.
 *
 * Le sujet distingue un back-office "utilise par NO MORE WASTE" et un front
 * office "utilise par les clients de NO MORE WASTE". Cet ecran est le coeur du
 * second : sans lui, un adherent connecte ne verrait rien de plus qu'un
 * visiteur de passage.
 *
 * TOUT PASSE PAR LES ROUTES /mon-espace
 *
 * Aucune methode de ce controleur n'envoie d'identifiant de commercant a
 * l'API. Les routes /mon-espace font elles-memes le chemin
 * jeton -> compte -> fiche. C'est ce qui garantit qu'un adherent ne peut pas
 * lire le dossier d'un autre en changeant un numero dans l'URL.
 */
class EspaceCommercantController
{
    private ApiClient $api;
    private array $config;

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /mon-espace/commercant
     */
    public function index(): void
    {
        if (!Auth::exigerAdherent($this->config)) {
            return;
        }

        $reponse = $this->api->get('/mon-espace/commercant', Auth::jeton());

        // 404 : le compte est legitime, mais aucune boutique ne lui est
        // rattachee. Ce n'est pas une panne -- c'est un dossier incomplet, et
        // l'utilisateur doit comprendre qu'il faut contacter l'association
        // plutot que croire que le site est casse.
        if ($reponse['code'] === 404) {
            Vue::afficher('front/espace_commercant_sans_fiche', [
                'config' => $this->config,
            ], Langue::t('espace.titre_commercant'));
            return;
        }

        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            Auth::rediriger('/');
            return;
        }

        $corps = $reponse['corps'] ?? [];
        $commercant = $corps['commercant'] ?? [];
        $adhesions = $corps['adhesions'] ?? [];

        $collectes = $this->extraire($this->api->get('/mon-espace/collectes', Auth::jeton()));

        Vue::afficher('front/espace_commercant', [
            'config' => $this->config,
            'commercant' => $commercant,
            'adhesion' => $this->adhesionCourante($adhesions),
            'collectes' => $this->trierParDateDecroissante($collectes),
            'statistiques' => $this->statistiques($collectes, $adhesions),
            'aujourdhui' => date('Y-m-d'),
        ], Langue::t('espace.titre_commercant'));
    }

    /**
     * POST /mon-espace/collectes — demander une collecte.
     *
     * L'action principale d'un commercant : signaler qu'il a des invendus.
     *
     * On n'envoie QUE la date souhaitee. Ni le statut, ni le benevole, ni
     * l'identifiant de la boutique : le commercant ne decide pas de
     * l'organisation interne de l'association. L'API l'impose de son cote,
     * mais autant ne pas envoyer ce qui sera ignore.
     */
    public function demanderCollecte(): void
    {
        if (!Auth::exigerAdherent($this->config)) {
            return;
        }

        $date = trim($_POST['date_prevue'] ?? '');

        if ($date === '') {
            $_SESSION['message_erreur'] = Langue::t('espace.date_obligatoire');
            Auth::rediriger('/mon-espace/commercant');
            return;
        }

        // Une collecte se demande pour plus tard. Verifie ici parce que
        // l'erreur est evidente pour l'utilisateur -- il s'est trompe de mois
        // dans le calendrier -- alors qu'un refus venu de l'API serait plus
        // sec a lire.
        if ($date < date('Y-m-d')) {
            $_SESSION['message_erreur'] = Langue::t('espace.date_passee');
            Auth::rediriger('/mon-espace/commercant');
            return;
        }

        $this->message(
            $this->api->post('/mon-espace/collectes', ['date_prevue' => $date], Auth::jeton()),
            'espace.collecte_demandee'
        );

        Auth::rediriger('/mon-espace/commercant');
    }

    // -----------------------------------------------------------------

    /**
     * Choisit l'adhesion a mettre en avant.
     *
     * Un commercant fidele en accumule plusieurs au fil des annees. C'est la
     * plus recente qui compte : c'est elle qui dit s'il est en regle
     * aujourd'hui.
     */
    private function adhesionCourante(array $adhesions): ?array
    {
        $courante = null;

        foreach ($adhesions as $a) {
            if ($courante === null || ($a['date_fin'] ?? '') > ($courante['date_fin'] ?? '')) {
                $courante = $a;
            }
        }

        if ($courante === null) {
            return null;
        }

        // Les jours restants, calcules ici pour que la vue n'ait qu'a afficher.
        $fin = strtotime($courante['date_fin'] ?? '');
        $courante['jours_restants'] = $fin
            ? (int) floor(($fin - strtotime(date('Y-m-d'))) / 86400)
            : 0;

        return $courante;
    }

    private function trierParDateDecroissante(array $collectes): array
    {
        // La plus recente en premier : c'est celle qui est encore a venir ou
        // qui vient de se passer, donc celle qu'on consulte.
        usort($collectes, function ($a, $b) {
            return strcmp($b['date_prevue'] ?? '', $a['date_prevue'] ?? '');
        });

        return $collectes;
    }

    /**
     * Les chiffres affiches en haut de page.
     *
     * On ne compte QUE ce que l'API renvoie reellement. La maquette montrait
     * aussi un nombre d'articles donnes ; il faudrait un appel par collecte
     * pour l'obtenir, et l'espace client n'a pas acces a la route des
     * produits. Mieux vaut trois chiffres justes que quatre dont un invente.
     */
    private function statistiques(array $collectes, array $adhesions): array
    {
        $realisees = 0;
        foreach ($collectes as $c) {
            if (($c['statut'] ?? '') === 'realisee') {
                $realisees++;
            }
        }

        return [
            'collectes' => count($collectes),
            'realisees' => $realisees,
            'annees' => count($adhesions),
        ];
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
