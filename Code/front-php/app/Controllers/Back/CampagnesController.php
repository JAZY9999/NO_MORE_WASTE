<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Services\ApiClient;
use App\Vue;

/**
 * Back-office : les campagnes d'emailing ciblees.
 *
 * Le sujet demande de pouvoir "relancer les anciens adherents" et de
 * communiquer avec les partenaires. Une campagne, c'est un email + des
 * criteres qui decident QUI le recoit.
 *
 * LA PREVISUALISATION EST LA PIECE MAITRESSE
 *
 * Un envoi d'emails est irreversible. On ne peut pas "annuler" un message
 * parti chez cinquante commercants. L'ecran impose donc de voir la liste
 * exacte des destinataires AVANT de declencher -- c'est la seule protection
 * possible contre un critere mal compris.
 */
class CampagnesController
{
    private ApiClient $api;
    private array $config;

    /**
     * Les statuts d'adhesion utilisables comme critere.
     * Meme liste que la contrainte CHECK de la table campagnes.
     */
    private const STATUTS_ADHESION = ['active', 'expiree', 'resiliee', 'en_attente'];

    public function __construct(ApiClient $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * GET /back/campagnes
     */
    public function liste(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $campagnes = $this->extraire($this->api->get('/campagnes/', Auth::jeton()));

        // Les villes reellement presentes chez les commercants : proposer
        // "Marseille" alors qu'aucun partenaire n'y est installe ferait
        // fabriquer une campagne sans destinataire.
        $villes = [];
        foreach ($this->extraire($this->api->get('/commercants/', Auth::jeton())) as $c) {
            $ville = $c['ville'] ?? '';
            if ($ville !== '' && !in_array($ville, $villes, true)) {
                $villes[] = $ville;
            }
        }
        sort($villes);

        Vue::afficher('back/campagnes', [
            'config' => $this->config,
            'campagnes' => $campagnes,
            'villes' => $villes,
            'statuts' => self::STATUTS_ADHESION,
        ], Langue::t('menu.campagnes'), [
            'sous_titre' => Langue::t('campagnes.sous_titre'),
            'recherche' => false,
        ]);
    }

    /**
     * GET /back/campagnes/@id — la fiche, avec la liste des destinataires.
     *
     * C'est l'ecran qu'on ouvre AVANT d'envoyer quoi que ce soit.
     */
    public function detail(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;

        // L'API n'expose pas GET /campagnes/{id} : on retrouve la ligne dans
        // la liste. Une route de plus pour un seul champ ne se justifierait
        // pas -- l'information utile est la liste des destinataires.
        $campagne = null;
        foreach ($this->extraire($this->api->get('/campagnes/', Auth::jeton())) as $c) {
            if ((int) $c['id'] === $id) {
                $campagne = $c;
                break;
            }
        }

        if ($campagne === null) {
            $_SESSION['message_erreur'] = Langue::t('campagnes.introuvable');
            Auth::rediriger('/back/campagnes');
            return;
        }

        $destinataires = $this->extraire(
            $this->api->get('/campagnes/' . $id . '/destinataires', Auth::jeton())
        );

        // Combien recevront reellement l'email. Un destinataire sans adresse
        // est ignore par l'API : autant le dire avant l'envoi plutot que de
        // laisser croire que tout le monde a ete touche.
        $avecEmail = 0;
        foreach ($destinataires as $d) {
            if (!empty($d['email'])) {
                $avecEmail++;
            }
        }

        Vue::afficher('back/campagne_detail', [
            'config' => $this->config,
            'campagne' => $campagne,
            'destinataires' => $destinataires,
            'avecEmail' => $avecEmail,
        ], $campagne['nom'] ?? Langue::t('menu.campagnes'), [
            'fil' => [
                ['libelle' => Langue::t('menu.campagnes'), 'url' => '/back/campagnes'],
                ['libelle' => $campagne['nom'] ?? '', 'url' => null],
            ],
            'sous_titre' => count($destinataires) . ' ' . Langue::t('campagnes.destinataires'),
            'actions' => [[
                'libelle' => Langue::t('commun.retour'),
                'url' => '/back/campagnes',
                'style' => 'light',
                'icone' => 'bi-arrow-left',
            ]],
            'recherche' => false,
        ]);
    }

    /**
     * POST /back/campagnes — creer une campagne.
     *
     * Creer n'envoie RIEN. C'est une etape separee du declenchement, et
     * volontairement : on redige, on verifie la cible, puis seulement on
     * envoie.
     */
    public function creer(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $nom = trim($_POST['nom'] ?? '');
        $sujet = trim($_POST['sujet_email'] ?? '');
        $corps = trim($_POST['corps_email'] ?? '');

        if ($nom === '' || $sujet === '' || $corps === '') {
            $_SESSION['message_erreur'] = Langue::t('campagnes.champs_obligatoires');
            $_SESSION['campagne_saisie'] = $_POST;
            Auth::rediriger('/back/campagnes');
            return;
        }

        $campagne = ['nom' => $nom, 'sujet_email' => $sujet, 'corps_email' => $corps];

        // Les criteres sont TOUS facultatifs : une campagne sans critere
        // s'adresse a tous les commercants. On ne transmet que ceux qui sont
        // reellement choisis -- une chaine vide serait comprise comme "ville
        // vide" et ne selectionnerait personne.
        foreach (['critere_ville', 'critere_pays'] as $champ) {
            $valeur = trim($_POST[$champ] ?? '');
            if ($valeur !== '') {
                $campagne[$champ] = $valeur;
            }
        }

        $statut = $_POST['critere_statut_adhesion'] ?? '';
        if (in_array($statut, self::STATUTS_ADHESION, true)) {
            $campagne['critere_statut_adhesion'] = $statut;
        }

        $jours = (int) ($_POST['critere_adhesion_expiree_depuis_jours'] ?? 0);
        if ($jours > 0) {
            $campagne['critere_adhesion_expiree_depuis_jours'] = $jours;
        }

        $reponse = $this->api->post('/campagnes/', $campagne, Auth::jeton());

        if (!ApiClient::estSucces($reponse)) {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
            $_SESSION['campagne_saisie'] = $_POST;
            Auth::rediriger('/back/campagnes');
            return;
        }

        // On emmene directement sur la fiche : la premiere chose a faire
        // apres avoir cree une campagne est de verifier qui elle vise.
        $_SESSION['message_succes'] = Langue::t('campagnes.creee');
        Auth::rediriger('/back/campagnes/' . (int) ($reponse['corps']['id'] ?? 0));
    }

    /**
     * POST /back/campagnes/@id — declencher l'envoi.
     *
     * Irreversible. C'est pour cela que l'action n'existe que sur la fiche,
     * apres la liste des destinataires, et jamais depuis la liste generale.
     */
    public function declencher(string $id): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        $id = (int) $id;

        // Confirmation explicite : le formulaire envoie un champ cache que
        // seul le bouton de confirmation produit. Un POST forge sans lui ne
        // declenche rien.
        if (($_POST['confirmation'] ?? '') !== 'oui') {
            $_SESSION['message_erreur'] = Langue::t('campagnes.confirmation_requise');
            Auth::rediriger('/back/campagnes/' . $id);
            return;
        }

        $reponse = $this->api->post('/campagnes/' . $id . '/declencher', [], Auth::jeton());

        if (ApiClient::estSucces($reponse)) {
            // L'API renvoie {"nombre_envoyes": N} : le nombre d'emails
            // REELLEMENT partis, pas le nombre de destinataires vises. Les
            // deux different des qu'une adresse manque ou qu'un envoi echoue.
            //
            // On affiche ce chiffre plutot qu'un "c'est fait" vague : c'est la
            // seule facon de savoir si la cible correspondait a ce qu'on
            // croyait -- et, aujourd'hui, de constater que le SMTP n'est pas
            // configure (0 envoi sur 12 destinataires).
            $nombre = (int) ($reponse['corps']['nombre_envoyes'] ?? 0);

            $_SESSION['message_succes'] = sprintf(Langue::t('campagnes.envoyee'), $nombre);
        } else {
            $_SESSION['message_erreur'] = ApiClient::messageErreur($reponse);
        }

        Auth::rediriger('/back/campagnes/' . $id);
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
