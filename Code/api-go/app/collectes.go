package app

import (
	"encoding/json"
	"net/http"
	"strconv"

	"nomorewaste/db"
	"nomorewaste/models"
	"nomorewaste/utils"
)

func CreerCollecte(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var c models.Collecte
	err := json.NewDecoder(r.Body).Decode(&c)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if c.CommercantId == nil && c.ParticulierNom == nil {
		http.Error(w, "commercant_id ou particulier_nom est obligatoire", http.StatusBadRequest)
		return
	}
	if c.Statut == "" {
		c.Statut = "demandee"
	}

	id, err := db.CreateCollecte(c)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de la collecte", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerCollectes(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var statut *string
	if valeur := r.URL.Query().Get("statut"); valeur != "" {
		statut = &valeur
	}

	collectes, err := db.ListCollectes(statut)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des collectes", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(collectes)
}

func ObtenirCollecte(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	collecte, err := db.GetCollecteById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la collecte", err)
		return
	}
	if collecte == nil {
		http.Error(w, "Collecte introuvable", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(collecte)
}

type statutCollecteDto struct {
	Statut     string `json:"statut"`
	BenevoleId *int   `json:"benevole_id"`
}

func ModifierStatutCollecte(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	existante, err := db.GetCollecteById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la collecte", err)
		return
	}
	if existante == nil {
		http.Error(w, "Collecte introuvable", http.StatusNotFound)
		return
	}

	var dto statutCollecteDto
	err = json.NewDecoder(r.Body).Decode(&dto)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}
	if dto.Statut == "" {
		http.Error(w, "statut est obligatoire", http.StatusBadRequest)
		return
	}

	err = db.UpdateStatutCollecte(id, dto.Statut, dto.BenevoleId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de mise a jour de la collecte", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

// AjouterProduitCollecte enregistre un produit DIRECTEMENT rattache a cette
// collecte -- c'est le flux reel : le benevole scanne le code-barre d'un
// produit AU MOMENT de la collecte, il n'existe pas avant.
func AjouterProduitCollecte(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	collecteId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	collecte, err := db.GetCollecteById(collecteId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la collecte", err)
		return
	}
	if collecte == nil {
		http.Error(w, "Collecte introuvable", http.StatusNotFound)
		return
	}

	var p models.Produit
	err = json.NewDecoder(r.Body).Decode(&p)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if p.CodeBarre == "" || p.Libelle == "" {
		http.Error(w, "code_barre et libelle sont obligatoires", http.StatusBadRequest)
		return
	}
	if p.Quantite == 0 {
		p.Quantite = 1
	}
	if p.Statut == "" {
		p.Statut = "en_stock"
	}

	existant, err := db.GetProduitByCodeBarre(p.CodeBarre)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de verification du code-barre", err)
		return
	}
	if existant != nil {
		http.Error(w, "Ce code-barre est deja utilise", http.StatusConflict)
		return
	}

	p.CollecteId = &collecteId

	id, err := db.CreateProduit(p)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation du produit", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerProduitsCollecte(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	collecteId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	collecte, err := db.GetCollecteById(collecteId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la collecte", err)
		return
	}
	if collecte == nil {
		http.Error(w, "Collecte introuvable", http.StatusNotFound)
		return
	}

	produits, err := db.ListProduitsParCollecte(collecteId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des produits", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(produits)
}
