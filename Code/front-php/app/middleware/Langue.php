<?php

namespace App\Middleware;

/**
 * Gere la langue du site (exigence du sujet : "le site devra etre multilingue").
 *
 * Le principe est simple : les textes affiches ne sont jamais ecrits en dur
 * dans les pages. On ecrit une CLE (ex: 'connexion.titre'), et cette classe
 * va chercher le texte correspondant dans le fichier de la langue choisie.
 *
 *   <?= Langue::t('connexion.titre') ?>   ->  "Connexion" / "Sign in" / "Accedi"...
 */
class Langue
{
    /** Les traductions de la langue active, chargees une seule fois. */
    private static array $traductions = [];

    /** Les traductions francaises, utilisees comme filet de securite. */
    private static array $traductionsDefaut = [];

    private static string $langueActive = 'fr';
    private static array $config = [];

    /**
     * Determine la langue a utiliser et charge les traductions.
     *
     * L'ordre de priorite est important :
     *   1. le parametre d'URL (?lang=en)  -> l'utilisateur vient de cliquer sur un drapeau
     *   2. la session                      -> il avait deja choisi une langue
     *   3. l'en-tete du navigateur         -> devine sa langue automatiquement
     *   4. le francais                     -> valeur par defaut
     *
     * On va du plus explicite au plus devine : un choix volontaire doit
     * toujours l'emporter sur une deduction automatique.
     */
    public static function initialiser(array $config): void
    {
        self::$config = $config;
        $disponibles = array_keys($config['langues_disponibles']);
        $langue = null;

        // 1. Choix explicite dans l'URL
        if (isset($_GET['lang']) && in_array($_GET['lang'], $disponibles, true)) {
            $langue = $_GET['lang'];
            $_SESSION['langue'] = $langue;   // on memorise pour les pages suivantes
        }

        // 2. Choix deja memorise en session
        if ($langue === null && isset($_SESSION['langue']) && in_array($_SESSION['langue'], $disponibles, true)) {
            $langue = $_SESSION['langue'];
        }

        // 3. Langue du navigateur
        if ($langue === null) {
            $langue = self::detecterDepuisNavigateur($disponibles);
        }

        // 4. Valeur par defaut
        self::$langueActive = $langue ?? $config['langue_par_defaut'];

        self::chargerTraductions();
    }

    /**
     * Lit l'en-tete Accept-Language envoye par le navigateur.
     *
     * Il ressemble a : "fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7"
     * Les "q=" sont des niveaux de preference. On reste simple : on prend les
     * deux premieres lettres de chaque entree et on garde la premiere langue
     * qu'on sait afficher.
     */
    private static function detecterDepuisNavigateur(array $disponibles): ?string
    {
        $entete = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($entete === '') {
            return null;
        }

        foreach (explode(',', $entete) as $morceau) {
            $code = strtolower(substr(trim($morceau), 0, 2));
            if (in_array($code, $disponibles, true)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Emplacement des fichiers de traduction.
     *
     * Ce sont des fichiers JSON REGENERES depuis la base de donnees par le
     * back-office (ecran "Traductions"). On ne les modifie pas a la main :
     * la prochaine synchronisation ecraserait les modifications.
     */
    public static function dossierLocales(): string
    {
        return __DIR__ . '/../locales/';
    }

    /**
     * Charge les traductions de la langue active depuis son fichier JSON.
     *
     * POURQUOI UN FICHIER ET PAS LA BASE ?
     *
     * Une page affiche facilement 30 a 50 libelles. Aller les chercher un par
     * un dans la base (ou pire, via l'API) ferait 50 allers-retours reseau
     * pour afficher un seul ecran. Ce serait lent, et le site tomberait des
     * que l'API a un souci.
     *
     * On procede donc en deux temps :
     *   - la BASE sert a EDITER les traductions (back-office, plusieurs
     *     personnes, historique) ;
     *   - un FICHIER JSON par langue sert a les LIRE (un seul acces disque,
     *     mis en cache par PHP).
     *
     * Le back-office regenere les fichiers a la demande. C'est exactement le
     * fonctionnement d'un cache : la source de verite est la base, le fichier
     * n'est qu'une copie rapide a lire.
     */
    private static function chargerTraductions(): void
    {
        $dossier = self::dossierLocales();

        self::$traductionsDefaut = self::lireFichierLangue($dossier . 'fr.json');

        if (self::$langueActive === 'fr') {
            self::$traductions = self::$traductionsDefaut;
            return;
        }

        self::$traductions = self::lireFichierLangue($dossier . self::$langueActive . '.json');
    }

    /**
     * Lit un fichier de traduction JSON. Retourne un tableau vide si le
     * fichier n'existe pas ou est illisible.
     *
     * On ne provoque jamais d'erreur ici : un fichier manquant doit degrader
     * l'affichage (on retombera sur le francais, puis sur la cle elle-meme),
     * jamais faire tomber la page entiere.
     */
    private static function lireFichierLangue(string $chemin): array
    {
        if (!file_exists($chemin)) {
            return [];
        }

        // true en 2e argument : on veut un tableau associatif PHP, pas un objet.
        $donnees = json_decode((string) file_get_contents($chemin), true);

        return is_array($donnees) ? $donnees : [];
    }

    /**
     * Traduit une cle. C'est LA fonction utilisee dans toutes les vues.
     *
     * Si la cle n'existe pas dans la langue active, on retombe sur le francais.
     * Si elle n'existe nulle part, on affiche la cle elle-meme : ca rend
     * l'oubli visible a l'ecran au lieu de laisser un trou blanc.
     */
    public static function t(string $cle): string
    {
        return self::$traductions[$cle]
            ?? self::$traductionsDefaut[$cle]
            ?? $cle;
    }

    public static function actuelle(): string
    {
        return self::$langueActive;
    }

    public static function disponibles(): array
    {
        return self::$config['langues_disponibles'] ?? [];
    }
}
