<?php

namespace App;

/**
 * Petit moteur de rendu de pages.
 *
 * On n'utilise pas de moteur de template (Twig, Blade...) : PHP sait deja
 * melanger du HTML et des donnees. Une "vue" est donc simplement un fichier
 * PHP qui produit du HTML.
 *
 * Cette classe fait deux choses :
 *   1. executer une vue en lui passant des donnees ;
 *   2. inserer le resultat dans le bon gabarit (layout_back ou layout_front).
 */
class Vue
{
    /**
     * Affiche une vue a l'interieur du gabarit.
     *
     * LE GABARIT EST CHOISI PAR LE DOSSIER DE LA VUE
     *
     *   'back/commercants'  -> layout_back.php  (barre laterale sombre)
     *   'front/connexion'   -> layout_front.php (en-tete horizontal aere)
     *
     * Cette convention existe deja trois fois dans le projet : les dossiers
     * views/back et views/front, les fichiers back_routes.php et
     * front_routes.php, et le prefixe d'URL /back. La reutiliser ici n'ajoute
     * aucune regle a retenir -- et surtout, on ne peut pas "oublier de
     * preciser le gabarit", puisque le chemin est obligatoire.
     *
     * @param string $chemin  chemin depuis views/, sans extension (ex: 'back/commercants')
     * @param array  $donnees variables mises a disposition de la VUE
     * @param string $titre   titre affiche dans l'onglet du navigateur
     * @param array  $options reglages du BANDEAU (voir ci-dessous)
     *
     * $donnees et $options ne se melangent pas :
     *   $donnees = ce que la vue affiche (la liste des commercants...)
     *   $options = ce que le bandeau affiche (fil d'Ariane, boutons, onglets)
     *
     * Cles reconnues dans $options, toutes facultatives :
     *   'menu'       cle de l'entree de menu a surligner (sinon deduite)
     *   'fil'        fil d'Ariane : [['libelle' =>, 'url' => ou null], ...]
     *   'titre_page' le <h1>, quand il differe du titre de l'onglet
     *   'sous_titre' la ligne grise sous le titre
     *   'actions'    boutons : [['libelle' =>, 'url' =>, 'style' =>, 'icone' =>], ...]
     *   'onglets'    [['libelle' =>, 'url' =>, 'compteur' =>, 'actif' =>], ...]
     *   'compteurs'  pastilles du menu : ['adhesions' => 2, ...]
     *   'recherche'  false pour masquer le champ de recherche
     *   'gabarit'    'back' ou 'front' pour forcer le choix (echappatoire)
     */
    public static function afficher(
        string $chemin,
        array $donnees = [],
        string $titre = '',
        array $options = []
    ): void {
        $contenu = self::rendre($chemin, $donnees);

        // Le gabarit a besoin de ces variables, plus la config.
        $config = $donnees['config'] ?? require __DIR__ . '/config/config.php';

        $estBack = $options['gabarit'] ?? null;
        if ($estBack === null) {
            $estBack = str_starts_with($chemin, 'back/');
        } else {
            $estBack = ($estBack === 'back');
        }

        if ($estBack) {
            $menu = require __DIR__ . '/config/menu_back.php';
            $menuActif = self::entreeDeMenu($chemin, $menu, $options);

            require __DIR__ . '/views/layout_back.php';
            return;
        }

        require __DIR__ . '/views/layout_front.php';
    }

    /**
     * Determine quelle entree de la barre laterale doit etre surlignee.
     *
     * Trois regles, de la plus explicite a la plus automatique :
     *
     *   1. le controleur l'a dit  -> on le croit
     *   2. c'est un ecran de detail (table 'parents' de menu_back.php)
     *      -> on surligne l'entree parente, sinon ouvrir une fiche ferait
     *         perdre toute position dans le menu
     *   3. sinon -> le dernier segment du chemin de vue
     *      'back/commercants' -> 'commercants'
     *
     * C'est la regle 3 qui rend le systeme supportable : la grande majorite
     * des ecrans porte deja le bon nom, et ne demande donc aucune
     * configuration. Seules les exceptions sont listees dans menu_back.php.
     */
    private static function entreeDeMenu(string $chemin, array $menu, array $options): string
    {
        if (isset($options['menu'])) {
            return $options['menu'];
        }

        if (isset($menu['parents'][$chemin])) {
            return $menu['parents'][$chemin];
        }

        $segments = explode('/', $chemin);

        return end($segments);
    }

    /**
     * Execute un fichier de vue et RECUPERE son HTML au lieu de l'afficher.
     *
     * C'est le role de la "temporisation de sortie" (output buffering) :
     *   - ob_start()          : "a partir de maintenant, ne montre rien a l'ecran,
     *                            garde tout de cote"
     *   - require             : la vue s'execute et produit du HTML
     *   - ob_get_clean()      : "donne-moi ce que tu as garde, et arrete"
     *
     * Sans ca, la vue s'afficherait AVANT l'en-tete du site, et la page
     * sortirait dans le desordre.
     */
    private static function rendre(string $chemin, array $donnees): string
    {
        $fichier = __DIR__ . '/views/' . $chemin . '.php';

        if (!file_exists($fichier)) {
            return '<p class="message message-erreur">Vue introuvable : '
                . htmlspecialchars($chemin) . '</p>';
        }

        // extract transforme les cles du tableau en variables.
        // ['titre' => 'X'] devient $titre = 'X' a l'interieur de la vue.
        //
        // EXTR_SKIP n'est pas un detail : sans lui, extract ECRASE les
        // variables deja presentes. Or $fichier est calcule juste au-dessus et
        // utilise juste en dessous (require $fichier). Une vue a qui l'on
        // passerait ['fichier' => ...] -- ce qui n'a rien d'absurde pour un
        // ecran de documents -- ferait donc charger un fichier arbitraire.
        // EXTR_SKIP demande a extract de ne jamais remplacer une variable
        // existante.
        extract($donnees, EXTR_SKIP);

        ob_start();
        require $fichier;

        return ob_get_clean();
    }

    /**
     * Raccourci pour afficher du texte venant de l'utilisateur ou de l'API
     * sans risque.
     *
     * htmlspecialchars transforme les caracteres speciaux du HTML : si quelqu'un
     * enregistre un commercant nomme "<script>alert(1)</script>", le navigateur
     * affichera ce texte au lieu de l'executer. C'est la protection de base
     * contre les failles XSS. On l'applique a TOUTE donnee affichee.
     */
    public static function e(?string $texte): string
    {
        return htmlspecialchars($texte ?? '', ENT_QUOTES, 'UTF-8');
    }
}
