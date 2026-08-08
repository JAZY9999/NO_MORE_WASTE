package main

import (
	"fmt"
	"net/http"

	"nomorewaste/app"
	"nomorewaste/config"
	"nomorewaste/db"
	"nomorewaste/utils"
)

// healthCheck sert a verifier que l'API est vivante ET que la base repond.
// C'est la route qu'on appelle en premier quand quelque chose ne marche pas.
func healthCheck(w http.ResponseWriter, r *http.Request) {
	err := db.Conn.Ping()
	if err != nil {
		// Avant, on faisait panic(err) ici : le client recevait une connexion
		// coupee sans aucun message, ce qui est le pire cas possible pour
		// une route dont le seul but est de dire si tout va bien.
		// Maintenant on repond proprement 503 (service indisponible) et on
		// ecrit la vraie cause dans les logs du serveur.
		utils.ErreurBaseIndisponible(w, r, err)
		return
	}
	fmt.Fprintf(w, "NO MORE WASTE api - ok")
}

func main() {
	db.Conn = db.NewDB()

	http.HandleFunc("GET /{$}", healthCheck)
	http.HandleFunc("POST /auth/register/{$}", app.Register)
	http.HandleFunc("POST /auth/login/{$}", app.Login)
	http.HandleFunc("GET /auth/me/{$}", app.Me)
	http.HandleFunc("GET /admin/ping/{$}", app.AdminPing)
	http.HandleFunc("POST /commercants/{$}", app.CreerCommercant)
	http.HandleFunc("GET /commercants/{$}", app.ListerCommercants)
	http.HandleFunc("GET /commercants/{id}", app.ObtenirCommercant)
	http.HandleFunc("PUT /commercants/{id}", app.ModifierCommercant)
	http.HandleFunc("POST /commercants/{id}/adhesions", app.CreerAdhesion)
	http.HandleFunc("GET /adhesions/{$}", app.ListerAdhesions)
	http.HandleFunc("PUT /adhesions/{id}", app.ModifierAdhesion)
	http.HandleFunc("GET /adhesions/a-renouveler/{$}", app.ListerAdhesionsARenouveler)
	http.HandleFunc("POST /adhesions/{id}/relancer", app.RelancerAdhesion)
	http.HandleFunc("GET /adhesions/{id}/historique-rappels", app.ObtenirHistoriqueRappels)
	http.HandleFunc("POST /admin/jobs/rappels-adhesions/{$}", app.DeclencherJobRappels)
	http.HandleFunc("POST /campagnes/{$}", app.CreerCampagne)
	http.HandleFunc("GET /campagnes/{$}", app.ListerCampagnes)
	http.HandleFunc("GET /campagnes/{id}/destinataires", app.PrevisualiserCampagne)
	http.HandleFunc("POST /campagnes/{id}/declencher", app.DeclencherCampagne)
	http.HandleFunc("POST /emplacements/{$}", app.CreerEmplacement)
	http.HandleFunc("GET /emplacements/{$}", app.ListerEmplacements)
	http.HandleFunc("GET /emplacements/{id}", app.ObtenirEmplacement)
	http.HandleFunc("POST /produits/{$}", app.CreerProduit)
	http.HandleFunc("GET /produits/{$}", app.ListerProduits)
	http.HandleFunc("GET /produits/{id}", app.ObtenirProduit)
	http.HandleFunc("PUT /produits/{id}", app.DeplacerProduit)
	http.HandleFunc("POST /collectes/{$}", app.CreerCollecte)
	http.HandleFunc("GET /collectes/{$}", app.ListerCollectes)
	http.HandleFunc("GET /collectes/{id}", app.ObtenirCollecte)
	http.HandleFunc("PUT /collectes/{id}", app.ModifierStatutCollecte)
	http.HandleFunc("POST /collectes/{id}/produits", app.AjouterProduitCollecte)
	http.HandleFunc("GET /collectes/{id}/produits", app.ListerProduitsCollecte)
	http.HandleFunc("POST /benevoles/candidature/{$}", app.PoserCandidature)
	http.HandleFunc("GET /benevoles/{$}", app.ListerBenevoles)
	http.HandleFunc("GET /benevoles/{id}", app.ObtenirBenevole)
	http.HandleFunc("PUT /benevoles/{id}/validation", app.ValiderBenevole)
	http.HandleFunc("POST /benevoles/{id}/documents", app.AjouterDocumentBenevole)
	http.HandleFunc("GET /benevoles/{id}/documents", app.ListerDocumentsBenevole)
	http.HandleFunc("PUT /benevoles/{id}/documents/{docId}/validation", app.ValiderDocumentBenevole)
	http.HandleFunc("GET /competences/{$}", app.ListerCompetences)
	http.HandleFunc("GET /benevoles/{id}/competences", app.ListerCompetencesBenevole)
	http.HandleFunc("POST /benevoles/{id}/competences/{competenceId}", app.AjouterCompetenceBenevole)
	http.HandleFunc("DELETE /benevoles/{id}/competences/{competenceId}", app.RetirerCompetenceBenevole)
	http.HandleFunc("POST /services/{$}", app.CreerService)
	http.HandleFunc("GET /services/{$}", app.ListerServices)
	http.HandleFunc("GET /services/{id}", app.ObtenirService)
	http.HandleFunc("POST /services/{id}/creneaux", app.CreerCreneau)
	http.HandleFunc("GET /services/{id}/creneaux", app.ListerCreneauxService)
	http.HandleFunc("PUT /creneaux/{id}/affectation", app.AffecterBenevoleCreneau)
	http.HandleFunc("POST /creneaux/{id}/inscriptions", app.InscrireACreneau)
	http.HandleFunc("GET /creneaux/{id}/inscriptions", app.ListerInscriptionsCreneau)
	http.HandleFunc("GET /plannings/{$}", app.TelechargerPlanning)
	http.HandleFunc("POST /admin/jobs/plannings/{$}", app.DeclencherJobPlannings)
	http.HandleFunc("POST /beneficiaires/{$}", app.CreerBeneficiaire)
	http.HandleFunc("GET /beneficiaires/{$}", app.ListerBeneficiaires)
	http.HandleFunc("POST /tournees/{$}", app.CreerTournee)
	http.HandleFunc("GET /tournees/{$}", app.ListerTournees)
	http.HandleFunc("GET /tournees/{id}", app.ObtenirTournee)
	http.HandleFunc("PUT /tournees/{id}", app.ModifierStatutTournee)
	http.HandleFunc("POST /tournees/{id}/etapes", app.AjouterEtapeTournee)
	http.HandleFunc("GET /tournees/{id}/etapes", app.ListerEtapesTournee)
	http.HandleFunc("POST /tournee-etapes/{id}/livraison", app.CloturerLivraison)
	http.HandleFunc("GET /livraisons/{id}", app.ObtenirRecapLivraison)
	http.HandleFunc("GET /livraisons/{id}/pdf", app.TelechargerRecapLivraison)

	// --- Langues et traductions (multilingue gere depuis le back-office) ---
	//
	// Les deux routes GET sont PUBLIQUES : le selecteur de langue et les
	// libelles du site doivent s'afficher pour un visiteur non connecte.
	// Tout le reste (creation, modification, suppression, import) est reserve
	// au personnel.
	http.HandleFunc("GET /langues/{$}", app.ListerLangues)
	http.HandleFunc("POST /langues/{$}", app.CreerLangue)
	http.HandleFunc("DELETE /langues/{code}", app.SupprimerLangue)
	http.HandleFunc("GET /traductions/{$}", app.ListerTraductions)
	http.HandleFunc("POST /traductions/{$}", app.CreerTraduction)
	http.HandleFunc("POST /traductions/import", app.ImporterTraductions)
	http.HandleFunc("PUT /traductions/{id}", app.ModifierTraduction)
	http.HandleFunc("DELETE /traductions/{id}", app.SupprimerTraduction)

	// --- Espace client (front-office) ---
	//
	// Le sujet parle d'un "front office utilise par les clients". Ces routes
	// permettent a un commercant ou a un benevole connecte de voir SES donnees.
	//
	// Toutes partent du jeton pour retrouver la fiche : aucune n'accepte
	// d'identifiant fourni par le client, sinon il suffirait d'en essayer un
	// autre pour lire les donnees de quelqu'un d'autre.
	http.HandleFunc("GET /mon-espace/commercant", app.MonEspaceCommercant)
	http.HandleFunc("GET /mon-espace/collectes", app.MesCollectes)
	http.HandleFunc("POST /mon-espace/collectes", app.DemanderCollecte)
	http.HandleFunc("GET /mon-espace/benevole", app.MonEspaceBenevole)
	http.HandleFunc("GET /mon-espace/planning", app.MonPlanning)

	// --- Gestion des comptes (admin_back uniquement) ---
	//
	// Comble le dernier trou de l'API : creer un compte staff imposait
	// jusqu'ici une requete SQL a la main.
	http.HandleFunc("GET /utilisateurs/{$}", app.ListerUtilisateurs)
	http.HandleFunc("POST /utilisateurs/{$}", app.CreerUtilisateur)

	utils.DemarrerSchedulerRappels()

	fmt.Println("listening at : http://localhost:" + config.ApiPort())
	http.ListenAndServe(":"+config.ApiPort(), nil)
}
