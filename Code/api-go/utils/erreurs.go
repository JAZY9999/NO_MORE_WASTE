package utils

import (
	"errors"
	"log"
	"net/http"

	"github.com/lib/pq"
)

// ErreurServeur gere une erreur imprevue du serveur (erreur 500).
//
// Le probleme qu'elle resout : quand une requete SQL echoue, on repondait
// juste "Erreur de recuperation" au client, et la vraie cause de l'erreur
// etait perdue pour toujours. Impossible de savoir pourquoi ca a plante.
// C'est ce qu'on appelle une "erreur 500 silencieuse".
//
// Cette fonction fait donc deux choses en meme temps :
//  1. elle ECRIT la vraie erreur dans les logs du serveur (pour le developpeur),
//  2. elle RENVOIE un message simple au client (pour l'utilisateur).
//
// On ne renvoie jamais la vraie erreur au client : un message SQL peut
// contenir des noms de tables ou de colonnes, ce qui aide un attaquant.
// Toutes les erreurs renvoyees par PostgreSQL portent un code standard a
// 5 caracteres. On en utilise trois ici :
//
//	23503 = foreign_key_violation : on a envoye l'identifiant d'un element
//	        qui n'existe pas (ex: emplacement_id = 99999).
//	23505 = unique_violation : on a envoye une donnee qui existe deja alors
//	        qu'elle doit etre unique (ex: deux fois la meme cle de traduction
//	        pour la meme langue).
//	23514 = check_violation : la valeur envoyee n'est pas dans la liste
//	        autorisee par la table (ex: un type de service invente, alors que
//	        la colonne n'accepte que sept valeurs precises).
//
// La liste complete est dans la documentation PostgreSQL ("Error Codes").
const (
	codeViolationCleEtrangere = "23503"
	codeViolationUnicite      = "23505"
	codeViolationContrainte   = "23514"
)

func ErreurServeur(w http.ResponseWriter, r *http.Request, message string, err error) {
	// --- Cas particulier : la faute vient du client, pas du serveur ---
	//
	// Si le client envoie "emplacement_id": 99999 alors que cet emplacement
	// n'existe pas, PostgreSQL refuse l'insertion. Avant, on repondait 500,
	// ce qui veut dire "le serveur a un bug" -- alors que le serveur va tres
	// bien : c'est la donnee envoyee qui est fausse. On repond donc 400
	// ("Bad Request"), avec un message qui dit quoi corriger.
	//
	// errors.As sert a demander : "cette erreur est-elle (ou contient-elle)
	// une erreur PostgreSQL ?". On l'utilise plutot qu'une comparaison
	// directe car les repositories enveloppent souvent l'erreur d'origine
	// dans un message a eux (ex: "CreateProduit : ...").
	var erreurPostgres *pq.Error
	if errors.As(err, &erreurPostgres) {

		switch erreurPostgres.Code {

		case codeViolationCleEtrangere:
			log.Printf("[ERREUR 400] %s %s -> %s | reference inexistante | cause : %v",
				r.Method, r.URL.Path, message, err)

			http.Error(w,
				message+" : un des elements references n'existe pas (verifiez les identifiants envoyes)",
				http.StatusBadRequest)
			return

		case codeViolationUnicite:
			// 409 Conflict : la demande est valide en soi, mais elle entre en
			// conflit avec ce qui existe deja. C'est different d'un 400 (la
			// donnee envoyee serait correcte si l'autre n'existait pas), et
			// surtout d'un 500 (le serveur va tres bien).
			//
			// Cas typique : creer deux fois la meme cle de traduction pour la
			// meme langue, ou reutiliser un code-barre deja enregistre.
			log.Printf("[ERREUR 409] %s %s -> %s | doublon | cause : %v",
				r.Method, r.URL.Path, message, err)

			http.Error(w,
				message+" : cette valeur existe deja",
				http.StatusConflict)
			return

		case codeViolationContrainte:
			// La table n'accepte qu'une liste fermee de valeurs, et celle
			// envoyee n'en fait pas partie. C'est encore une faute du client :
			// 400, pas 500.
			//
			// Trouve en portant l'ecran des services : la colonne "type"
			// n'accepte que sept valeurs, et en saisir une huitieme repondait
			// "Erreur de creation du service" avec un code 500 -- de quoi
			// chercher un bug la ou il n'y en avait pas.
			log.Printf("[ERREUR 400] %s %s -> %s | valeur hors liste autorisee | cause : %v",
				r.Method, r.URL.Path, message, err)

			http.Error(w,
				message+" : une des valeurs envoyees n'est pas autorisee pour ce champ",
				http.StatusBadRequest)
			return
		}
	}

	// --- Cas general : vraie erreur serveur ---
	// Exemple de ligne ecrite dans les logs :
	// [ERREUR 500] POST /collectes/ -> Erreur de creation de la collecte | cause : pq: null value in column "date_prevue"
	log.Printf("[ERREUR 500] %s %s -> %s | cause : %v", r.Method, r.URL.Path, message, err)

	http.Error(w, message, http.StatusInternalServerError)
}

// ErreurBaseIndisponible sert quand la base de donnees ne repond plus.
//
// On utilise le code 503 (Service Unavailable) plutot que 500 car ce n'est
// pas un bug du code : c'est une panne temporaire d'un service exterieur.
// La difference compte pour un outil de supervision, qui peut alors decider
// de reessayer au lieu de declarer l'application cassee.
func ErreurBaseIndisponible(w http.ResponseWriter, r *http.Request, err error) {
	log.Printf("[ERREUR 503] %s %s -> base de donnees injoignable | cause : %v", r.Method, r.URL.Path, err)

	http.Error(w, "Base de donnees indisponible", http.StatusServiceUnavailable)
}

// ErreurEmail sert quand l'envoi d'un email echoue.
//
// # POURQUOI 502 ET PAS 500
//
// Un 500 veut dire "le serveur a un bug". Or ici le serveur va tres bien :
// c'est le service d'envoi (Brevo) qui refuse, le plus souvent parce que les
// identifiants SMTP ne sont pas renseignes dans le fichier .env. 502 Bad
// Gateway dit exactement cela -- un service EXTERIEUR n'a pas repondu comme
// attendu. Meme raisonnement que ErreurBaseIndisponible avec son 503.
//
// Le message renvoye au client le dit aussi. Avant, un membre du personnel
// qui cliquait "Relancer" lisait "Erreur d'envoi de l'email" et n'avait aucun
// moyen de savoir s'il devait reessayer, prevenir un developpeur, ou verifier
// une configuration. C'est le genre de detail qui compte le jour de la
// demonstration.
func ErreurEmail(w http.ResponseWriter, r *http.Request, err error) {
	log.Printf("[ERREUR 502] %s %s -> envoi d'email impossible | cause : %v", r.Method, r.URL.Path, err)

	http.Error(w,
		"Le service d'envoi d'emails n'a pas repondu. Verifiez les identifiants SMTP du fichier .env.",
		http.StatusBadGateway)
}
