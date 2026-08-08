<?php

/**
 * Routes du BACK-OFFICE : la partie interne, reservee au personnel de
 * NO MORE WASTE (roles admin_back et staff_back).
 *
 * Toutes les adresses commencent par /back. Cette separation par prefixe est
 * volontaire et rend la securite verifiable d'un coup d'oeil : si une adresse
 * commence par /back, elle doit etre protegee.
 *
 * La protection elle-meme est appliquee dans chaque controleur, en premiere
 * ligne (Auth::exigerStaff). Pourquoi la repeter plutot que de la mettre une
 * fois pour toutes ? Pour la meme raison que cote Go avec utils.RequireRole :
 * on voit la protection en lisant le controleur, sans avoir a se souvenir
 * qu'un mecanisme invisible s'en charge ailleurs. Un oubli devient visible.
 *
 * Variables disponibles (fournies par public/index.php) :
 *   $api    : le client de l'API Go
 *   $config : la configuration
 */

use App\Controllers\Back\BenevolesController;
use App\Controllers\Back\CollectesController;
use App\Controllers\Back\StocksController;
use App\Controllers\Back\TourneesController;
use App\Controllers\Back\AdhesionsController;
use App\Controllers\Back\BeneficiairesController;
use App\Controllers\Back\UtilisateursController;
use App\Controllers\Back\CampagnesController;
use App\Controllers\Back\ServicesController;
use App\Controllers\Back\CommercantsController;
use App\Controllers\Back\TraductionsController;
use App\Controllers\Back\TableauDeBordController;

$tableauDeBord = new TableauDeBordController($config);
$commercants = new CommercantsController($api, $config);
$benevoles = new BenevolesController($api, $config);
$collectes = new CollectesController($api, $config);
$stocks = new StocksController($api, $config);
$tournees = new TourneesController($api, $config);
$services = new ServicesController($api, $config);
$adhesions = new AdhesionsController($api, $config);
$beneficiaires = new BeneficiairesController($api, $config);
$utilisateurs = new UtilisateursController($api, $config);
$campagnes = new CampagnesController($api, $config);
$traductions = new TraductionsController($api, $config);

Flight::route('GET /back', [$tableauDeBord, 'index']);
Flight::route('GET /back/', [$tableauDeBord, 'index']);

// Benevoles : le module le plus detaille par le sujet.
//
// @id capture un segment variable et le passe en argument a la methode
// (syntaxe FlightPHP). GET affiche la fiche, POST traite les actions de
// cette meme fiche -- validation d'un document, de la candidature, ajout ou
// retrait d'une competence.
Flight::route('GET /back/benevoles', [$benevoles, 'liste']);
Flight::route('GET /back/benevoles/@id', [$benevoles, 'detail']);
Flight::route('POST /back/benevoles/@id', [$benevoles, 'traiter']);

// Collectes : liste, detail avec scan des produits ramasses.
Flight::route('GET /back/collectes', [$collectes, 'liste']);
Flight::route('GET /back/collectes/@id', [$collectes, 'detail']);
Flight::route('POST /back/collectes/@id', [$collectes, 'traiter']);

// Stocks : la recherche par code-barre est une route GET (donc partageable
// et rechargeable), les emplacements ont leur propre ecran.
Flight::route('GET /back/stocks', [$stocks, 'liste']);
Flight::route('POST /back/stocks/@id', [$stocks, 'traiter']);
Flight::route('GET /back/emplacements', [$stocks, 'emplacements']);
Flight::route('POST /back/emplacements', [$stocks, 'creerEmplacement']);

// Tournees : la cloture d'une livraison genere le recapitulatif PDF exige
// par le sujet. Le PDF est FABRIQUE par l'API, mais SERVI par le front :
// un lien vers /api/... n'emporterait pas le jeton range en session, et
// l'API repondrait 401. Voir TourneesController::pdf().
Flight::route('GET /back/tournees', [$tournees, 'liste']);
Flight::route('GET /back/tournees/@id', [$tournees, 'detail']);
Flight::route('POST /back/tournees/@id', [$tournees, 'traiter']);
Flight::route('GET /back/livraisons/@id/pdf', [$tournees, 'pdf']);

// Services et creneaux : une seule page (liste + creations), donc une seule
// route POST aiguillee par un champ cache "action".
//
// Le planning a ses propres routes parce qu'il ne produit pas une page :
// GET telecharge un CSV, POST declenche des envois d'e-mails. Deux verbes
// differents pour deux natures differentes -- un GET ne doit jamais avoir
// d'effet, sinon un simple rafraichissement renverrait tous les e-mails.
Flight::route('GET /back/services', [$services, 'liste']);
Flight::route('POST /back/services', [$services, 'traiter']);
Flight::route('GET /back/plannings', [$services, 'planning']);
Flight::route('POST /back/plannings', [$services, 'envoyerPlannings']);

// Adhesions : le RAPPEL AUTOMATIQUE DE RENOUVELLEMENT, point le plus cite
// du sujet. Le mecanisme tournait deja ; ces routes le rendent visible.
//
// Les deux POST envoient reellement des emails. Ils ne peuvent donc pas etre
// des GET : un simple rafraichissement les rejouerait.
Flight::route('GET /back/adhesions', [$adhesions, 'liste']);
Flight::route('POST /back/adhesions', [$adhesions, 'declencherJob']);
Flight::route('GET /back/adhesions/@id', [$adhesions, 'detail']);
Flight::route('POST /back/adhesions/@id', [$adhesions, 'relancer']);

// Beneficiaires : les destinataires des tournees de distribution, cites par
// le sujet ("associations caritatives, particuliers en detresse..."). Liste et
// creation sur la meme page : un beneficiaire tient en cinq champs.
Flight::route('GET /back/beneficiaires', [$beneficiaires, 'liste']);
Flight::route('POST /back/beneficiaires', [$beneficiaires, 'creer']);

// Commercants : liste (item 2.4), fiche, creation et modification.
//
// ATTENTION A L'ORDRE : /back/commercants/nouveau est declaree AVANT
// /back/commercants/@id. Dans l'autre sens, FlightPHP prendrait "nouveau"
// pour un identifiant, et la page de creation afficherait "Commercant
// introuvable" -- une panne silencieuse, sans la moindre erreur PHP.
Flight::route('GET /back/commercants', [$commercants, 'liste']);
Flight::route('POST /back/commercants', [$commercants, 'creer']);
Flight::route('GET /back/commercants/nouveau', [$commercants, 'formulaireCreation']);
Flight::route('GET /back/commercants/@id', [$commercants, 'detail']);
Flight::route('POST /back/commercants/@id', [$commercants, 'traiter']);

// Campagnes d'emailing ciblees.
//
// CREER et DECLENCHER sont deux routes distinctes, et c'est deliberé :
// creer n'envoie rien. On redige, on ouvre la fiche, on lit la liste exacte
// des destinataires, et seulement ensuite on declenche. Un envoi d'emails ne
// s'annule pas.
Flight::route('GET /back/campagnes', [$campagnes, 'liste']);
Flight::route('POST /back/campagnes', [$campagnes, 'creer']);
Flight::route('GET /back/campagnes/@id', [$campagnes, 'detail']);
Flight::route('POST /back/campagnes/@id', [$campagnes, 'declencher']);

// Utilisateurs et roles : reserve a admin_back, PAS a staff_back.
//
// Pouvoir creer des comptes, c'est pouvoir se fabriquer un acces. Le
// controleur verifie donc le role exact, en plus de la garde de personnel --
// Auth::exigerStaff laisserait passer les deux.
Flight::route('GET /back/utilisateurs', [$utilisateurs, 'liste']);
Flight::route('POST /back/utilisateurs', [$utilisateurs, 'creer']);

// Traductions : gestion du multilingue depuis le back-office.
// Deux routes sur la meme adresse : GET affiche l'ecran, POST traite toutes
// les actions (ajout, modification, suppression, synchronisation).
Flight::route('GET /back/traductions', [$traductions, 'index']);
Flight::route('POST /back/traductions', [$traductions, 'traiter']);
