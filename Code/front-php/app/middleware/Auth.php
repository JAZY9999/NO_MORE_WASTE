<?php

namespace App\Middleware;

/**
 * Gere la connexion de l'utilisateur cote front.
 *
 * Point important a comprendre : le front ne verifie JAMAIS un mot de passe
 * lui-meme, et ne parle jamais a la base de donnees. Il demande a l'API Go de
 * verifier, recoit un jeton (JWT), et le range dans la session PHP.
 *
 * Ensuite, a chaque page, il ressort ce jeton et le joint a ses appels d'API.
 *
 *   navigateur  ->  front PHP  ->  API Go  ->  PostgreSQL
 *                   (session)      (JWT)
 *
 * La session PHP est un espace de stockage cote serveur, lie a un visiteur par
 * un cookie. C'est la que vivent le jeton, le role et l'email de l'utilisateur.
 */
class Auth
{
    /** Enregistre l'utilisateur comme connecte. */
    public static function connecter(string $jeton, array $utilisateur): void
    {
        // session_regenerate_id cree un nouvel identifiant de session au moment
        // de la connexion. C'est une protection contre la "fixation de session" :
        // sans ca, un attaquant qui aurait impose un identifiant de session
        // connu de lui avant la connexion resterait connecte avec la victime.
        session_regenerate_id(true);

        $_SESSION['jeton'] = $jeton;
        $_SESSION['utilisateur'] = $utilisateur;
    }

    public static function deconnecter(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function estConnecte(): bool
    {
        return isset($_SESSION['jeton']) && $_SESSION['jeton'] !== '';
    }

    public static function jeton(): ?string
    {
        return $_SESSION['jeton'] ?? null;
    }

    public static function utilisateur(): ?array
    {
        return $_SESSION['utilisateur'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['utilisateur']['role'] ?? null;
    }

    /**
     * Vrai si l'utilisateur fait partie du personnel de l'association.
     * C'est ce qui separe le back-office du front-office (exigence du sujet).
     */
    public static function estStaff(array $config): bool
    {
        return in_array(self::role(), $config['roles_back_office'], true);
    }

    /**
     * Garde : exige d'etre connecte, sinon renvoie vers la page de connexion.
     *
     * Retourne true si l'acces est autorise. Les controleurs l'utilisent ainsi :
     *
     *     if (!Auth::exigerConnexion()) { return; }
     *
     * C'est volontairement le meme style que utils.RequireRole cote Go : un
     * appel explicite au debut du controleur, plutot qu'un mecanisme cache.
     * On voit d'un coup d'oeil quelles pages sont protegees.
     */
    public static function exigerConnexion(): bool
    {
        if (self::estConnecte()) {
            return true;
        }

        $_SESSION['message_erreur'] = Langue::t('connexion.obligatoire');
        self::rediriger('/connexion');

        return false;
    }

    /** Vrai si le compte connecte est un commercant adherent. */
    public static function estAdherent(array $config): bool
    {
        return self::role() === $config['role_adherent'];
    }

    /** Vrai si le compte connecte est un benevole. */
    public static function estBenevole(array $config): bool
    {
        return self::role() === $config['role_benevole'];
    }

    /**
     * Le coeur commun a toutes les gardes : connecte, puis role autorise.
     *
     * Les trois gardes publiques ci-dessous ne different que par la liste des
     * roles acceptes et le message de refus. Ecrire trois fois la meme
     * mecanique serait trois occasions d'oublier le http_response_code(403),
     * ou de renvoyer un 401 la ou il faut un 403.
     *
     * @param string[] $rolesAutorises un ou plusieurs roles acceptes
     * @param string   $cleMessage     cle de traduction du message de refus
     */
    private static function exigerRoles(array $rolesAutorises, string $cleMessage): bool
    {
        if (!self::exigerConnexion()) {
            return false;
        }

        if (!in_array(self::role(), $rolesAutorises, true)) {
            // 403 = "je sais qui tu es, mais tu n'as pas le droit".
            // Different de 401 ("je ne sais pas qui tu es"), qui est gere
            // par exigerConnexion ci-dessus.
            http_response_code(403);
            $_SESSION['message_erreur'] = Langue::t($cleMessage);
            self::rediriger('/');

            return false;
        }

        return true;
    }

    /**
     * Garde du back-office : exige d'etre connecte ET membre du personnel.
     */
    public static function exigerStaff(array $config): bool
    {
        return self::exigerRoles($config['roles_back_office'], 'back.acces_refuse');
    }

    /** Garde de l'espace commercant. */
    public static function exigerAdherent(array $config): bool
    {
        return self::exigerRoles([$config['role_adherent']], 'espace.reserve_adherent');
    }

    /** Garde de l'espace benevole. */
    public static function exigerBenevole(array $config): bool
    {
        return self::exigerRoles([$config['role_benevole']], 'espace.reserve_benevole');
    }

    /**
     * L'adresse de l'espace personnel du compte connecte, ou null s'il n'y en
     * a pas (visiteur non connecte).
     *
     * Sert au lien "Mon espace" de l'en-tete public. Sans cette methode, le
     * gabarit devrait contenir un if/elseif sur le role -- de la logique
     * metier dans un fichier dont le seul travail est de mettre en forme.
     */
    public static function urlEspace(array $config): ?string
    {
        if (!self::estConnecte()) {
            return null;
        }
        if (self::estStaff($config)) {
            return '/back';
        }
        if (self::estAdherent($config)) {
            // "/mon-espace/commercant" et non "/mon-espace" : cette derniere
            // adresse n'existe pas et rendait un 404. Le lien avait ete ecrit
            // en vague 1, avant que l'ecran existe -- et rien ne l'avait
            // signale, puisque personne n'etait connecte en adherent a ce
            // moment-la. Trouve en portant l'espace client.
            return '/mon-espace/commercant';
        }
        if (self::estBenevole($config)) {
            return '/mon-espace/benevole';
        }

        return null;
    }

    /**
     * Redirige puis arrete le script.
     *
     * Le "exit" est indispensable : sans lui, PHP continuerait d'executer le
     * reste de la page apres avoir envoye l'en-tete de redirection, et pourrait
     * afficher du contenu reserve. C'est un oubli classique et dangereux.
     */
    public static function rediriger(string $chemin): void
    {
        header('Location: ' . $chemin);
        exit;
    }
}
