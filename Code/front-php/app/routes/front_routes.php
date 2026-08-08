<?php

/**
 * Routes du FRONT-OFFICE : la partie publique du site, utilisee par les
 * commercants adherents, les benevoles et les visiteurs.
 *
 * Le sujet demande explicitement "a la fois un back-office (utilise par NO MORE
 * WASTE) et un front office (utilise par les clients)". La separation se voit
 * ici : ce fichier ne contient que des pages publiques ou destinees aux
 * adherents, jamais des ecrans de gestion interne.
 *
 * Variables disponibles (fournies par public/index.php) :
 *   $api    : le client de l'API Go
 *   $config : la configuration
 */

use App\Controllers\Front\AccueilController;
use App\Controllers\Front\AuthController;
use App\Controllers\Front\CandidatureController;
use App\Controllers\Front\EspaceBenevoleController;
use App\Controllers\Front\EspaceCommercantController;
use App\Controllers\Front\ServicesPublicsController;

$accueil = new AccueilController($config);
$auth = new AuthController($api, $config);
$services = new ServicesPublicsController($api, $config);
$candidature = new CandidatureController($api, $config);
$espaceCommercant = new EspaceCommercantController($api, $config);
$espaceBenevole = new EspaceBenevoleController($api, $config);

// --- Pages publiques ---
Flight::route('GET /', [$accueil, 'index']);

// Le catalogue des services : consultable SANS connexion (les routes de l'API
// sont publiques). C'est la vitrine de l'association.
Flight::route('GET /services', [$services, 'liste']);
Flight::route('GET /services/@id', [$services, 'detail']);

// Seule l'inscription a un creneau demande un compte adherent.
Flight::route('POST /services/@id/inscription', [$services, 'inscrire']);

// La candidature benevole : "chacun peut s'inscrire", donc page publique.
//
// La page de remerciement a sa propre adresse plutot qu'un simple message :
// elle explique la suite du parcours, et elle survit a un rafraichissement.
Flight::route('GET /benevoles/candidature', [$candidature, 'formulaire']);
Flight::route('POST /benevoles/candidature', [$candidature, 'envoyer']);
Flight::route('GET /benevoles/candidature/merci', [$candidature, 'merci']);

// --- Espaces clients ---
//
// Deux espaces distincts, un par role. Aucun d'eux n'accepte d'identifiant
// dans l'URL : les routes /mon-espace de l'API font elles-memes le chemin
// jeton -> compte -> fiche. C'est ce qui empeche de lire le dossier d'autrui
// en changeant un numero.
Flight::route('GET /mon-espace/commercant', [$espaceCommercant, 'index']);
Flight::route('POST /mon-espace/collectes', [$espaceCommercant, 'demanderCollecte']);
Flight::route('GET /mon-espace/benevole', [$espaceBenevole, 'index']);

// --- Connexion / deconnexion ---
//
// Deux routes pour la meme adresse "/connexion" :
//   GET  -> afficher le formulaire
//   POST -> traiter ce qui a ete saisi
// C'est la facon habituelle de gerer un formulaire sur le web : on separe
// "montrer la page" de "recevoir les donnees".
Flight::route('GET /connexion', [$auth, 'formulaire']);
Flight::route('POST /connexion', [$auth, 'traiter']);
Flight::route('GET /deconnexion', [$auth, 'deconnexion']);
