<?php

namespace App\Services;

/**
 * ApiClient : le seul endroit du front qui parle a l'API Go.
 *
 * Pourquoi une classe dediee plutot que des appels un peu partout dans les
 * controleurs ? Parce que tout appel a l'API a besoin des memes choses :
 * l'adresse de base, le jeton de connexion, le decodage du JSON, la gestion
 * des erreurs reseau. Ecrire ca 40 fois serait 40 occasions de se tromper.
 *
 * On utilise cURL, l'outil HTTP integre a PHP (aucune librairie a installer).
 */
class ApiClient
{
    private string $urlDeBase;

    public function __construct(string $urlDeBase)
    {
        // rtrim enleve le "/" final s'il y en a un, pour eviter les
        // adresses du genre "http://api-go:8080//commercants".
        $this->urlDeBase = rtrim($urlDeBase, '/');
    }

    /**
     * Envoie une requete a l'API et retourne un tableau :
     *   ['code' => 200, 'corps' => [...donnees decodees...], 'brut' => '...']
     *
     * On retourne le code HTTP plutot que de lever une exception, parce qu'un
     * 404 ou un 401 ne sont pas des accidents ici : ce sont des reponses
     * normales que les controleurs doivent pouvoir traiter (afficher "introuvable",
     * rediriger vers la connexion...).
     */
    public function requete(string $methode, string $chemin, ?array $donnees = null, ?string $jeton = null): array
    {
        $url = $this->urlDeBase . '/' . ltrim($chemin, '/');

        $ch = curl_init($url);

        // CURLOPT_RETURNTRANSFER : sans cette ligne, cURL afficherait la reponse
        // directement a l'ecran au lieu de nous la donner. C'est l'oubli classique.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($methode));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $entetes = [];

        if ($donnees !== null) {
            // POURQUOI CE CAST SUR LE TABLEAU VIDE
            //
            // En PHP, un tableau vide est a la fois une liste et un
            // dictionnaire : json_encode([]) produit "[]", pas "{}".
            //
            // Or l'API attend TOUJOURS un objet JSON dans un corps de requete,
            // jamais une liste. Un "[]" lui arrive donc comme une valeur du
            // mauvais type, et elle repond "JSON invalide" -- pour une requete
            // qui n'avait simplement aucun champ a envoyer.
            //
            // C'est exactement le cas de l'inscription a un creneau : tout
            // vient du jeton, il n'y a rien a transmettre.
            //
            // Un tableau NON vide n'a pas ce probleme : des qu'il a des cles,
            // json_encode produit bien un objet.
            $corpsJson = json_encode($donnees === [] ? new \stdClass() : $donnees);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $corpsJson);
            $entetes[] = 'Content-Type: application/json';
        }

        if ($jeton !== null && $jeton !== '') {
            // RAPPEL IMPORTANT : le jeton part BRUT, sans le prefixe "Bearer ".
            // C'est la version simplifiee enseignee dans le cours ESGI, et
            // utils/jwt.go cote API lit le header tel quel. Ajouter "Bearer "
            // ferait echouer toutes les requetes authentifiees.
            $entetes[] = 'Authorization: ' . $jeton;
        }

        if ($entetes) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $entetes);
        }

        $brut = curl_exec($ch);
        $codeHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreurReseau = curl_error($ch);
        curl_close($ch);

        // curl_exec renvoie false si l'API est injoignable (conteneur arrete,
        // mauvaise adresse...). Ce n'est pas une reponse HTTP : il n'y a meme
        // pas eu de dialogue. On le signale avec un code 0, impossible a confondre.
        if ($brut === false) {
            return [
                'code' => 0,
                'corps' => null,
                'brut' => 'API injoignable : ' . $erreurReseau,
            ];
        }

        // L'API renvoie du JSON quand tout va bien, mais du texte simple pour
        // les messages d'erreur (ex: "Commercant introuvable"). json_decode
        // renvoie null dans ce cas : on garde donc toujours la version brute.
        $corps = json_decode($brut, true);

        return [
            'code' => $codeHttp,
            'corps' => $corps,
            'brut' => $brut,
        ];
    }

    // --- Raccourcis de lecture, pour que les controleurs restent lisibles ---

    public function get(string $chemin, ?string $jeton = null): array
    {
        return $this->requete('GET', $chemin, null, $jeton);
    }

    public function post(string $chemin, array $donnees, ?string $jeton = null): array
    {
        return $this->requete('POST', $chemin, $donnees, $jeton);
    }

    public function put(string $chemin, array $donnees, ?string $jeton = null): array
    {
        return $this->requete('PUT', $chemin, $donnees, $jeton);
    }

    public function delete(string $chemin, ?string $jeton = null): array
    {
        return $this->requete('DELETE', $chemin, null, $jeton);
    }

    /**
     * Vrai si la requete a reussi (200, 201, 204...).
     *
     * La famille des codes 2xx signifie "succes". On teste donc un intervalle
     * plutot que d'enumerer les codes un par un.
     */
    public static function estSucces(array $reponse): bool
    {
        return $reponse['code'] >= 200 && $reponse['code'] < 300;
    }

    /**
     * Extrait un message d'erreur affichable a l'utilisateur.
     *
     * L'API renvoie ses erreurs en texte simple, sauf l'inscription qui renvoie
     * un tableau JSON de messages (ex: ["Email invalide"]). On gere les deux.
     */
    public static function messageErreur(array $reponse): string
    {
        if ($reponse['code'] === 0) {
            return "Le service est momentanement indisponible.";
        }

        if (is_array($reponse['corps'])) {
            return implode(' ', $reponse['corps']);
        }

        $texte = trim((string) $reponse['brut']);

        return $texte !== '' ? $texte : "Une erreur est survenue (code {$reponse['code']}).";
    }
}
