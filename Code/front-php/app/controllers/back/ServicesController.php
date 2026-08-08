<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Back-office : les services et leurs creneaux.
 *
 * Le sujet demande de gerer des services "accessibles aux adherents" (cours de
 * cuisine anti-gaspillage, conseils, partage de vehicule, petites
 * reparations...) et l'"affectation a un service donne" des benevoles.
 *
 * L'ecran est organise autour du CRENEAU et non du service : c'est le creneau
 * qui a une date, un lieu, un benevole affecte et des inscrits. Un service seul
 * ("Cours de cuisine") ne se passe nulle part et n'a besoin de personne.
 */
class ServicesController
{
    private ApiClient $api;
    private array $config;

    /**
     * Les types de service acceptes.
     *
     * Ce n'est PAS une liste decorative : la base impose la meme par une
     * contrainte CHECK sur la colonne "type". Une valeur hors liste est
     * refusee par PostgreSQL.
     *
     * D'ou un menu deroulant et non un champ libre. Avec un champ libre,
     * quelqu'un qui tape "cuisine" au lieu de "cours_cuisine" recevait une
     * erreur serveur incomprehensible pour ce qui est, de son point de vue,
     * une faute de frappe. Trouve en testant l'ecran.
     */
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
     * GET /back/services
     *
     * Une seule page : la liste a plat de tous les creneaux, tous services
     * confondus, plus les formulaires de creation.
     */
    public function liste(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $services = $this->extraire($this->api->get('/services/', Auth::jeton()));

        // Les competences, pour afficher "requiert : cuisinier" a cote d'un
        // creneau non affecte. Sans cette indication, on ne comprendrait pas
        // pourquoi l'API refuse certains benevoles.
        $competences = [];
        foreach ($this->extraire($this->api->get('/competences/', Auth::jeton())) as $c) {
            $competences[$c['id']] = $c['libelle'] ?? '';
        }

        // Seuls les benevoles VALIDES peuvent etre affectes : inutile de
        // proposer les autres, l'API les refuserait.
        $benevoles = $this->extraire($this->api->get('/benevoles/?statut=valide', Auth::jeton()));

        // ---------------------------------------------------------------
        // La liste a plat des creneaux.
        //
        // L'API n'expose pas de route "tous les creneaux" : elle les donne
        // service par service. On boucle donc sur les services.
        //
        // C'est assume a cette echelle : une association a une poignee de
        // services, pas des centaines. Si la liste grandissait, il faudrait
        // une route GET /creneaux/ cote API plutot que de multiplier les
        // appels ici.
        // ---------------------------------------------------------------
        $creneaux = [];
        foreach ($services as $service) {
            $liste = $this->extraire(
                $this->api->get('/services/' . (int) $service['id'] . '/creneaux', Auth::jeton())
            );

            foreach ($liste as $creneau) {
                // On recopie le nom du service et sa competence dans le
                // creneau : la vue affiche des LIGNES, elle n'a pas a
                // rechercher a quel service chacune appartient.
                $creneau['service_nom'] = $service['nom'] ?? '';
                $creneau['competence_requise'] = isset($service['competence_requise_id'])
                    ? ($competences[$service['competence_requise_id']] ?? '')
                    : '';

                // Le nombre d'inscrits, un appel par creneau. Meme remarque
                // que ci-dessus : acceptable a cette echelle, a revoir si la
                // liste grossit.
                $inscriptions = $this->extraire(
                    $this->api->get('/creneaux/' . (int) $creneau['id'] . '/inscriptions', Auth::jeton())
                );
                $creneau['inscrits'] = count($inscriptions);

                $creneaux[] = $creneau;
            }
        }

        // Tri chronologique : le planning se lit dans l'ordre du temps, pas
        // dans l'ordre de creation des services.
        usort($creneaux, function ($a, $b) {
            return strcmp(
                ($a['date_creneau'] ?? '') . ($a['heure_debut'] ?? ''),
                ($b['date_creneau'] ?? '') . ($b['heure_debut'] ?? '')
            );
        });

        $nomsBenevoles = [];
        foreach ($benevoles as $b) {
            $nomsBenevoles[$b['id']] = trim(($b['prenom'] ?? '') . ' ' . ($b['nom'] ?? ''));
        }

        $aAffecter = 0;
        foreach ($creneaux as $c) {
            if (empty($c['benevole_id'])) {
                $aAffecter++;
            }
        }

        Vue::afficher('back/services', [
            'config' => $this->config,
            'services' => $services,
            'creneaux' => $creneaux,
            'benevoles' => $benevoles,
            'nomsBenevoles' => $nomsBenevoles,
            'competences' => $competences,
            'types' => self::TYPES,
            'aujourdhui' => date('Y-m-d'),
        ], Langue::t('menu.services'), [
            'sous_titre' => $aAffecter > 0
                ? $aAffecter . ' ' . Langue::t('services.a_affecter')
                : '',
            'recherche' => false,
        ]);
    }

    /**
     * POST /back/services
     */
    public function traiter(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        switch ($_POST['action'] ?? '') {

            case 'creer_service':
                $this->creerService();
                break;

            case 'creer_creneau':
                $this->creerCreneau();
                break;

            case 'affecter':
                $this->affecter();
                break;

            default:
                $_SESSION['message_erreur'] = Langue::t('commun.action_inconnue');
        }

        Auth::rediriger('/back/services');
    }

    /**
     * GET /back/plannings — telecharge le CSV du planning d'une journee.
     *
     * Comme le recapitulatif PDF des tournees, le fichier est FABRIQUE par
     * l'API mais SERVI par le front : un lien vers /api/... n'emporterait pas
     * le jeton range en session, et l'API repondrait 401.
     */
    public function planning(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $date = $_GET['date'] ?? date('Y-m-d');

        // On n'accepte qu'une date bien formee : cette valeur part dans l'URL
        // appelee ET dans le nom du fichier telecharge.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = date('Y-m-d');
        }

        $reponse = $this->api->get('/plannings/?date=' . urlencode($date), Auth::jeton());

        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            Auth::rediriger('/back/services');
            return;
        }

        // "attachment" et non "inline" : contrairement au PDF, un CSV ne
        // s'affiche pas dans le navigateur -- on veut l'ouvrir dans Excel.
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="planning-' . $date . '.csv"');

        // Le corps BRUT : json_decode aurait rendu null sur du CSV.
        echo $reponse['brut'];
        exit;
    }

    /**
     * POST /back/plannings — declenche l'envoi des plannings par e-mail.
     *
     * L'envoi est normalement automatique (une goroutine cote API tourne
     * chaque jour). Ce bouton existe pour pouvoir le DEMONTRER sans attendre
     * l'heure dite -- et pour rejouer un envoi rate.
     */
    public function envoyerPlannings(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $date = $_POST['date'] ?? date('Y-m-d');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = date('Y-m-d');
        }

        $this->message(
            $this->api->post('/admin/jobs/plannings/?date=' . urlencode($date), [], Auth::jeton()),
            'services.plannings_envoyes'
        );

        Auth::rediriger('/back/services');
    }

    // -----------------------------------------------------------------
    // Les actions, une par methode privee.
    // -----------------------------------------------------------------

    private function creerService(): void
    {
        $nom = trim($_POST['nom'] ?? '');
        $type = trim($_POST['type'] ?? '');

        if ($nom === '' || $type === '') {
            $_SESSION['message_erreur'] = Langue::t('services.nom_type_obligatoires');
            return;
        }

        // Le menu deroulant ne propose que des valeurs valides, mais rien
        // n'empeche d'en forger une autre. On revalide donc cote serveur --
        // sinon on obtiendrait une erreur de contrainte en base, illisible.
        if (!in_array($type, self::TYPES, true)) {
            $_SESSION['message_erreur'] = Langue::t('services.type_invalide');
            return;
        }

        $service = ['nom' => $nom, 'type' => $type, 'actif' => true];

        $description = trim($_POST['description'] ?? '');
        if ($description !== '') {
            $service['description'] = $description;
        }

        // 0 = "aucune competence requise" dans le menu deroulant. On ne
        // transmet le champ que s'il vaut vraiment quelque chose.
        $competence = (int) ($_POST['competence_requise_id'] ?? 0);
        if ($competence > 0) {
            $service['competence_requise_id'] = $competence;
        }

        $this->message(
            $this->api->post('/services/', $service, Auth::jeton()),
            'services.service_cree'
        );
    }

    private function creerCreneau(): void
    {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $date = trim($_POST['date_creneau'] ?? '');
        $debut = trim($_POST['heure_debut'] ?? '');
        $fin = trim($_POST['heure_fin'] ?? '');

        if ($serviceId <= 0 || $date === '' || $debut === '' || $fin === '') {
            $_SESSION['message_erreur'] = Langue::t('services.creneau_incomplet');
            return;
        }

        // Verification faite ici parce qu'elle est evidente pour l'utilisateur
        // et couteuse a comprendre si elle remonte de l'API : "16:00 -> 14:00"
        // est une faute de frappe, pas une erreur de serveur.
        if ($fin <= $debut) {
            $_SESSION['message_erreur'] = Langue::t('services.heures_incoherentes');
            return;
        }

        $creneau = [
            'date_creneau' => $date,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'statut' => 'ouvert',
        ];

        $lieu = trim($_POST['lieu'] ?? '');
        if ($lieu !== '') {
            $creneau['lieu'] = $lieu;
        }
        $capacite = (int) ($_POST['capacite_max'] ?? 0);
        if ($capacite > 0) {
            $creneau['capacite_max'] = $capacite;
        }

        $this->message(
            $this->api->post('/services/' . $serviceId . '/creneaux', $creneau, Auth::jeton()),
            'services.creneau_cree'
        );
    }

    /**
     * Affecte un benevole a un creneau.
     *
     * Deux conditions, verifiees par l'API : le benevole doit etre au statut
     * "valide", et posseder la competence requise par le service.
     *
     * On ne duplique PAS ces regles ici. Le menu ne propose deja que des
     * benevoles valides ; pour la competence, on laisse l'API refuser -- son
     * message dit exactement ce qui manque, et le dupliquer cote front
     * risquerait de faire diverger les deux verifications.
     */
    private function affecter(): void
    {
        $creneauId = (int) ($_POST['creneau_id'] ?? 0);
        $benevoleId = (int) ($_POST['benevole_id'] ?? 0);

        if ($creneauId <= 0 || $benevoleId <= 0) {
            $_SESSION['message_erreur'] = Langue::t('services.affectation_incomplete');
            return;
        }

        $this->message(
            $this->api->put('/creneaux/' . $creneauId . '/affectation',
                ['benevole_id' => $benevoleId], Auth::jeton()),
            // "_ok" : ne pas confondre avec services.benevole_affecte, qui est
            // l'en-tete de colonne du tableau.
            'services.benevole_affecte_ok'
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
