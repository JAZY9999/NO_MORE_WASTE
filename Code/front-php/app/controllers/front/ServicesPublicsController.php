<?php

namespace App\Controllers\Front;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Le catalogue public des services, et l'inscription a un creneau.
 *
 * Le sujet parle de services "accessibles aux adherents" : cours de cuisine
 * anti-gaspillage, conseils, partage de vehicule, petites reparations.
 *
 * La CONSULTATION est ouverte a tous, y compris aux visiteurs non connectes --
 * c'est la vitrine de l'association, et les routes de l'API sont publiques.
 * Seule l'INSCRIPTION demande un compte.
 */
class ServicesPublicsController
{
    private ApiClient $api;
    private array $config;

    private const TYPES = [
        'conseil_anti_gaspi',
        'cours_cuisine',
        'partage_vehicule',
        'echange_service',
        'reparation',
        'gardiennage',
        'autre',
    ];

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /services[?type=...]
     */
    public function liste(): void
    {
        $type = $_GET['type'] ?? '';
        if (!in_array($type, self::TYPES, true)) {
            $type = '';
        }

        $chemin = '/services/' . ($type !== '' ? '?type=' . urlencode($type) : '');

        // Pas de jeton : la route est publique. En envoyer un ne changerait
        // rien, mais l'omettre dit clairement que cette page est ouverte.
        $services = $this->extraire($this->api->get($chemin));

        // Le nombre de creneaux a venir, par service. C'est l'information qui
        // decide si la page vaut le clic : un service sans creneau ouvert n'a
        // rien a proposer aujourd'hui.
        $creneaux = [];
        foreach ($services as $s) {
            $creneaux[$s['id']] = count($this->creneauxOuverts((int) $s['id']));
        }

        // Seuls les types reellement presents sont proposes en filtre : une
        // pastille "Gardiennage" qui ne renvoie jamais rien donne l'impression
        // d'un site casse.
        $tousLesServices = $type === ''
            ? $services
            : $this->extraire($this->api->get('/services/'));

        $typesPresents = [];
        foreach ($tousLesServices as $s) {
            if (!empty($s['type'])) {
                $typesPresents[$s['type']] = true;
            }
        }

        Vue::afficher('front/services', [
            'config' => $this->config,
            'services' => $services,
            'creneaux' => $creneaux,
            'typesPresents' => array_keys($typesPresents),
            'typeActif' => $type,
        ], Langue::t('nav.services'));
    }

    /**
     * GET /services/@id
     */
    public function detail(string $id): void
    {
        $id = (int) $id;

        $reponse = $this->api->get('/services/' . $id);

        if (!ApiClient::estSucces($reponse)) {
            // 404 compris : un service supprime ou un identifiant invente ne
            // doit pas afficher une page vide.
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            Auth::rediriger('/services');
            return;
        }

        $service = $reponse['corps'];

        Vue::afficher('front/service_detail', [
            'config' => $this->config,
            'service' => $service,
            'creneaux' => $this->creneauxOuverts($id),
            'peutSInscrire' => Auth::estAdherent($this->config),
            'estConnecte' => Auth::estConnecte(),
        ], $service['nom'] ?? Langue::t('nav.services'));
    }

    /**
     * POST /services/@id/inscription
     *
     * On n'envoie AUCUN identifiant : l'API deduit du jeton qui s'inscrit.
     * C'est ce qui empeche un adherent d'inscrire quelqu'un d'autre a sa
     * place -- la requete etait acceptee avant que cette regle soit ajoutee
     * cote API.
     */
    public function inscrire(string $id): void
    {
        if (!Auth::exigerAdherent($this->config)) {
            return;
        }

        $creneauId = (int) ($_POST['creneau_id'] ?? 0);

        if ($creneauId <= 0) {
            $_SESSION['message_erreur'] = Langue::t('services_publics.creneau_invalide');
            Auth::rediriger('/services/' . (int) $id);
            return;
        }

        $reponse = $this->api->post('/creneaux/' . $creneauId . '/inscriptions', [], Auth::jeton());

        if (ApiClient::estSucces($reponse)) {
            $_SESSION['message_succes'] = Langue::t('services_publics.inscription_ok');
        } else {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
        }

        Auth::rediriger('/services/' . (int) $id);
    }

    // -----------------------------------------------------------------

    /**
     * Les creneaux d'un service qu'on peut encore proposer.
     *
     * On ecarte les creneaux annules et ceux qui sont deja passes : afficher
     * "Cours du 3 mars" en aout ferait douter que le site soit a jour.
     */
    private function creneauxOuverts(int $serviceId): array
    {
        $aujourdhui = date('Y-m-d');
        $retenus = [];

        foreach ($this->extraire($this->api->get('/services/' . $serviceId . '/creneaux')) as $c) {
            if (($c['statut'] ?? '') === 'annule') {
                continue;
            }
            if (substr($c['date_creneau'] ?? '', 0, 10) < $aujourdhui) {
                continue;
            }
            $retenus[] = $c;
        }

        return $retenus;
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
