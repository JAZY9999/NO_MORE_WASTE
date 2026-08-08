<?php

namespace App\Services;

use App\Middleware\Langue;

/**
 * Synchronisation des traductions entre la BASE DE DONNEES et les FICHIERS JSON.
 *
 * POURQUOI DEUX ENDROITS POUR LA MEME CHOSE ?
 *
 * Les deux ne servent pas au meme usage :
 *
 *   BASE DE DONNEES  ->  pour EDITER
 *       C'est la source de verite. Le back-office y ajoute, modifie et
 *       supprime des libelles. Plusieurs personnes peuvent y travailler, et
 *       les donnees survivent a une remise a zero des conteneurs.
 *
 *   FICHIERS JSON    ->  pour LIRE
 *       Une page affiche 30 a 50 libelles. Les chercher un par un dans la
 *       base ferait autant d'allers-retours reseau pour afficher un seul
 *       ecran : lent, et le site tomberait des que l'API a un souci.
 *       Un fichier JSON par langue = un seul acces disque.
 *
 * C'est le principe d'un CACHE : la base fait autorite, le fichier est une
 * copie rapide a lire, regeneree a la demande depuis le back-office.
 *
 * Deux sens de synchronisation, correspondant a deux besoins reels :
 *   - exporter() : base -> fichiers. Apres avoir modifie des libelles.
 *   - importer() : fichiers -> base. Pour charger un lot de traductions
 *     preparees dans les fichiers (c'est ce qui a servi a la mise en place).
 */
class Traductions
{
    private ApiClient $api;

    public function __construct(ApiClient $api)
    {
        $this->api = $api;
    }

    /**
     * BASE -> FICHIERS : regenere un fichier JSON par langue.
     *
     * Retourne un compte rendu :
     *   ['succes' => bool, 'message' => string, 'fichiers' => int, 'cles' => int]
     */
    public function exporter(?string $jeton): array
    {
        $reponseLangues = $this->api->get('/langues/', $jeton);
        if (!ApiClient::estSucces($reponseLangues)) {
            return $this->echec(ApiClient::messageErreur($reponseLangues));
        }

        $reponseTraductions = $this->api->get('/traductions/', $jeton);
        if (!ApiClient::estSucces($reponseTraductions)) {
            return $this->echec(ApiClient::messageErreur($reponseTraductions));
        }

        $langues = $reponseLangues['corps'] ?? [];
        $traductions = $reponseTraductions['corps'] ?? [];

        // GARDE-FOU IMPORTANT.
        //
        // Si la base est vide (mauvaise connexion, base fraichement reinitialisee,
        // erreur de manipulation), poursuivre ecraserait tous les fichiers JSON
        // par du vide -> le site s'afficherait avec des cles techniques partout,
        // et les traductions seraient definitivement perdues.
        //
        // On refuse donc l'operation plutot que de detruire ce qui existe.
        if (empty($traductions)) {
            return $this->echec(
                "Aucune traduction en base : export annule pour ne pas effacer les fichiers existants."
            );
        }

        $dossier = Langue::dossierLocales();
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true)) {
            return $this->echec("Impossible de creer le dossier " . $dossier);
        }

        // On regroupe les traductions par langue en UN seul passage.
        // Refaire une boucle sur toutes les traductions pour chaque langue
        // multiplierait le travail sans raison.
        $parLangue = [];
        foreach ($traductions as $t) {
            $code = $t['code_langue'] ?? '';
            $cle = $t['cle'] ?? '';
            if ($code === '' || $cle === '') {
                continue;
            }
            $parLangue[$code][$cle] = $t['valeur'] ?? '';
        }

        $fichiersEcrits = 0;
        $clesEcrites = 0;
        $codesEcrits = [];

        foreach ($langues as $langue) {
            $code = $langue['code'] ?? '';
            if ($code === '' || empty($parLangue[$code])) {
                continue;
            }

            $donnees = $parLangue[$code];

            // ksort : les cles sont triees par ordre alphabetique. Sans ca,
            // l'ordre dependrait de la base et le fichier changerait a chaque
            // export meme sans modification reelle -- impossible de voir ce
            // qui a change dans un outil de comparaison.
            ksort($donnees);

            // JSON_UNESCAPED_UNICODE garde les accents lisibles ("Bénévoles"
            // et non "Bénévoles"). JSON_PRETTY_PRINT met un libelle
            // par ligne, ce qui rend le fichier consultable a l'oeil.
            $contenu = json_encode(
                $donnees,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if (@file_put_contents($dossier . $code . '.json', $contenu . "\n") === false) {
                return $this->echec(
                    "Ecriture impossible dans " . $dossier . " (verifiez les droits du dossier)."
                );
            }

            $fichiersEcrits++;
            $clesEcrites += count($donnees);
            $codesEcrits[] = strtoupper($code);
        }

        return [
            'succes' => true,
            'message' => sprintf(
                "%d fichier(s) regenere(s) (%s), %d libelle(s) au total.",
                $fichiersEcrits,
                implode(', ', $codesEcrits),
                $clesEcrites
            ),
            'fichiers' => $fichiersEcrits,
            'cles' => $clesEcrites,
        ];
    }

    /**
     * FICHIERS -> BASE : charge chaque fichier JSON dans la base.
     *
     * L'API enregistre chaque libelle en "creer ou mettre a jour" (voir
     * EnregistrerTraduction cote Go). Relancer l'import deux fois de suite ne
     * cree donc aucun doublon : l'operation est repetable sans risque.
     */
    public function importer(?string $jeton): array
    {
        $dossier = Langue::dossierLocales();
        $fichiers = glob($dossier . '*.json') ?: [];

        if (empty($fichiers)) {
            return $this->echec("Aucun fichier .json trouve dans " . $dossier);
        }

        $reponseLangues = $this->api->get('/langues/', $jeton);
        if (!ApiClient::estSucces($reponseLangues)) {
            return $this->echec(ApiClient::messageErreur($reponseLangues));
        }

        $codesConnus = [];
        foreach ($reponseLangues['corps'] ?? [] as $langue) {
            $codesConnus[] = $langue['code'] ?? '';
        }

        $languesImportees = 0;
        $clesImportees = 0;
        $ignorees = [];

        foreach ($fichiers as $chemin) {
            // basename sans l'extension : "fr.json" -> "fr"
            $code = strtolower(pathinfo($chemin, PATHINFO_FILENAME));

            // On n'importe que les langues declarees en base. Un fichier
            // "brouillon.json" oublie dans le dossier ne doit pas creer une
            // langue fantome dans le selecteur du site.
            if (!in_array($code, $codesConnus, true)) {
                $ignorees[] = $code;
                continue;
            }

            $donnees = json_decode((string) file_get_contents($chemin), true);
            if (!is_array($donnees) || empty($donnees)) {
                $ignorees[] = $code . ' (vide ou illisible)';
                continue;
            }

            $reponse = $this->api->post('/traductions/import', [
                'code_langue' => $code,
                'traductions' => $donnees,
            ], $jeton);

            if (!ApiClient::estSucces($reponse)) {
                return $this->echec(
                    "Import de " . $code . " : " . ApiClient::messageErreur($reponse)
                );
            }

            $languesImportees++;
            $clesImportees += (int) ($reponse['corps']['enregistrees'] ?? 0);
        }

        $message = sprintf(
            "%d langue(s) importee(s), %d libelle(s) enregistre(s).",
            $languesImportees,
            $clesImportees
        );
        if (!empty($ignorees)) {
            $message .= " Fichiers ignores : " . implode(', ', $ignorees) . ".";
        }

        return [
            'succes' => true,
            'message' => $message,
            'fichiers' => $languesImportees,
            'cles' => $clesImportees,
        ];
    }

    private function echec(string $message): array
    {
        return ['succes' => false, 'message' => $message, 'fichiers' => 0, 'cles' => 0];
    }
}
