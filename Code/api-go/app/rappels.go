package app

import (
	"encoding/json"
	"net/http"
	"strconv"

	"nomorewaste/db"
	"nomorewaste/utils"
)

func ListerAdhesionsARenouveler(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	renouvelerJ30, err := db.ListAdhesionsARenouveler(30)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des adhesions a renouveler", err)
		return
	}
	renouvelerJ7, err := db.ListAdhesionsARenouveler(7)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des adhesions a renouveler", err)
		return
	}

	resultat := append(renouvelerJ30, renouvelerJ7...)

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(resultat)
}

func RelancerAdhesion(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	adhesion, err := db.GetAdhesionById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de l'adhesion", err)
		return
	}
	if adhesion == nil {
		http.Error(w, "Adhesion introuvable", http.StatusNotFound)
		return
	}

	commercant, err := db.GetCommercantById(adhesion.CommercantId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du commercant", err)
		return
	}
	if commercant == nil || commercant.Email == nil {
		http.Error(w, "Ce commercant n'a pas d'adresse email enregistree", http.StatusBadRequest)
		return
	}

	sujet := "Rappel : votre adhesion NO MORE WASTE"
	corps := "Bonjour " + commercant.RaisonSociale + ",\n\nCeci est un rappel manuel concernant votre adhesion (date de fin : " + adhesion.DateFin + ").\n\nMerci de votre confiance."

	err = utils.EnvoyerEmail(*commercant.Email, sujet, corps)
	if err != nil {
		// 502 et non 500 : c'est le service d'envoi qui refuse, pas notre
		// code qui plante. Voir utils.ErreurEmail.
		utils.ErreurEmail(w, r, err)
		return
	}

	err = db.EnregistrerRappelEnvoye(id, "manuel", *commercant.Email)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur d'enregistrement du rappel", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

func ObtenirHistoriqueRappels(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	historique, err := db.ListHistoriqueRappels(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de l'historique", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(historique)
}

func DeclencherJobRappels(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	utils.ExecuterJobRappels()

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "job de rappels execute"})
}
