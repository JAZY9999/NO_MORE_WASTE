package app

import (
	"encoding/json"
	"net/http"
	"strconv"

	"nomorewaste/db"
	"nomorewaste/models"
	"nomorewaste/utils"
)

func CreerEmplacement(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var e models.EmplacementStock
	err := json.NewDecoder(r.Body).Decode(&e)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if e.Entrepot == "" {
		http.Error(w, "L'entrepot est obligatoire", http.StatusBadRequest)
		return
	}

	id, err := db.CreateEmplacement(e)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de l'emplacement", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerEmplacements(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	emplacements, err := db.ListEmplacements()
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des emplacements", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(emplacements)
}

func ObtenirEmplacement(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	emplacement, err := db.GetEmplacementById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de l'emplacement", err)
		return
	}
	if emplacement == nil {
		http.Error(w, "Emplacement introuvable", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(emplacement)
}
