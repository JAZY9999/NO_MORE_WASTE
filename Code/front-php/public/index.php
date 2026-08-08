<?php

/**
 * Point d'entree unique du front ("front controller").
 *
 * TOUTES les adresses du site passent par ce fichier : nginx est configure
 * pour renvoyer ici tout ce qui ne correspond pas a un fichier reel
 * (try_files ... /index.php). C'est ce qui permet d'avoir des adresses propres
 * comme /back/commercants sans qu'aucun dossier de ce nom existe sur le disque.
 *
 * Ce fichier ne contient aucune logique metier. Il se contente de :
 *   1. demarrer la session,
 *   2. charger la configuration,
 *   3. preparer la langue et le client d'API,
 *   4. brancher les routes,
 *   5. lancer FlightPHP.
 *
 * L'equivalent cote API est app.go, qui joue exactement le meme role.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Middleware\Langue;
use App\Services\ApiClient;

// --- 1. Session ---
//
// A demarrer AVANT tout affichage : la session s'appuie sur un cookie, et un
// cookie voyage dans les en-tetes HTTP, qui sont envoyes avant le contenu.
// Le moindre espace affiche avant cette ligne casserait le mecanisme.
session_start();

// --- 2. Configuration ---
$config = require __DIR__ . '/../app/config/config.php';

// --- 3. Langue et client d'API ---
Langue::initialiser($config);

$api = new ApiClient($config['api_base_url']);

// --- 4. Routes ---
//
// Les deux espaces du site sont declares dans deux fichiers distincts
// (exigence du sujet : back-office et front-office separes).
require __DIR__ . '/../app/routes/front_routes.php';
require __DIR__ . '/../app/routes/back_routes.php';

// Adresse inconnue : on renvoie un code 404 SANS rien afficher.
// nginx intercepte alors la reponse (fastcgi_intercept_errors on) et affiche
// la page 404 personnalisee du site. Les fichiers de ces pages vivent dans le
// conteneur nginx, pas ici : ce serait donc une erreur d'essayer de les lire
// depuis PHP.
//
// ATTENTION au piege : la fonction PHP habituelle http_response_code(404) ne
// suffit PAS ici. Flight construit sa propre reponse et l'envoie ensuite avec
// son statut a lui (200 par defaut), ce qui ecrase le notre. Il faut donc
// passer par l'objet reponse de Flight pour que le 404 arrive vraiment au
// navigateur.
Flight::map('notFound', function () {
    Flight::response()->status(404)->send();
});

// --- 5. Demarrage ---
Flight::start();
