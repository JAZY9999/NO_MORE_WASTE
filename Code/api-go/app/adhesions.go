package app

import (
	"encoding/json"
	"net/http"
	"strconv"

	"nomorewaste/db"
	"nomorewaste/models"
	"nomorewaste/utils"
)

// ListerAdhesions : GET /adhesions/[?statut=...]
//
// Route ajoutee en portant l'ecran des adhesions : jusque-la, le back-office
// n'avait aucun moyen de voir les adhesions qu'il gere. Seule
// /adhesions/a-renouveler existait, et elle ne montre que celles qui tombent
// a J-30 ou J-7 EXACTEMENT.
var statutsAdhesion = []string{"active", "expiree", "resiliee", "en_attente"}

func ListerAdhesions(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var statut *string
	if valeur := r.URL.Query().Get("statut"); valeur != "" {
		// Liste blanche : un statut invente n'irait pas jusqu'a la base, mais
		// autant repondre 400 plutot qu'une liste vide qui ferait croire
		// qu'aucune adhesion ne correspond.
		valide := false
		for _, s := range statutsAdhesion {
			if valeur == s {
				valide = true
				break
			}
		}
		if !valide {
			http.Error(w, "Statut invalide", http.StatusBadRequest)
			return
		}
		statut = &valeur
	}

	adhesions, err := db.ListAdhesions(statut)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des adhesions", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(adhesions)
}

func ModifierAdhesion(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	existing, err := db.GetAdhesionById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de l'adhesion", err)
		return
	}
	if existing == nil {
		http.Error(w, "Adhesion introuvable", http.StatusNotFound)
		return
	}

	var a models.Adhesion
	err = json.NewDecoder(r.Body).Decode(&a)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if a.DateDebut == "" || a.DateFin == "" || a.Statut == "" {
		http.Error(w, "date_debut, date_fin et statut sont obligatoires", http.StatusBadRequest)
		return
	}

	err = db.UpdateAdhesion(id, a)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de modification de l'adhesion", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}
