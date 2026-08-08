package app

import (
	"encoding/json"
	"net/http"
	"strconv"
	"strings"

	"nomorewaste/db"
	"nomorewaste/models"
	"nomorewaste/utils"
)

func CreerCampagne(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var c models.Campagne
	err := json.NewDecoder(r.Body).Decode(&c)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if c.Nom == "" || c.SujetEmail == "" || c.CorpsEmail == "" {
		http.Error(w, "nom, sujet_email et corps_email sont obligatoires", http.StatusBadRequest)
		return
	}

	id, err := db.CreateCampagne(c)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de la campagne", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerCampagnes(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	campagnes, err := db.ListCampagnes()
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des campagnes", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(campagnes)
}

func PrevisualiserCampagne(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	campagne, err := db.GetCampagneById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la campagne", err)
		return
	}
	if campagne == nil {
		http.Error(w, "Campagne introuvable", http.StatusNotFound)
		return
	}

	destinataires, err := db.ResoudreDestinatairesCampagne(*campagne)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de resolution des destinataires", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(destinataires)
}

func DeclencherCampagne(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	campagne, err := db.GetCampagneById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la campagne", err)
		return
	}
	if campagne == nil {
		http.Error(w, "Campagne introuvable", http.StatusNotFound)
		return
	}

	destinataires, err := db.ResoudreDestinatairesCampagne(*campagne)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de resolution des destinataires", err)
		return
	}

	nombreEnvoyes := 0
	for _, d := range destinataires {
		if d.Email == nil {
			continue
		}

		corpsPersonnalise := strings.ReplaceAll(campagne.CorpsEmail, "{{raison_sociale}}", d.RaisonSociale)

		err = utils.EnvoyerEmail(*d.Email, campagne.SujetEmail, corpsPersonnalise)
		if err != nil {
			continue
		}

		err = db.EnregistrerCampagneEnvoi(campagne.Id, d.CommercantId)
		if err != nil {
			continue
		}
		nombreEnvoyes++
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]int{"nombre_envoyes": nombreEnvoyes})
}
