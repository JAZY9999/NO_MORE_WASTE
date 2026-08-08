package app

import (
	"encoding/json"
	"net/http"
	"strconv"

	"nomorewaste/db"
	"nomorewaste/models"
	"nomorewaste/utils"
)

func CreerProduit(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var p models.Produit
	err := json.NewDecoder(r.Body).Decode(&p)
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

	id, err := db.CreateProduit(p)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation du produit", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

// ListerProduits repond a GET /produits, avec deux usages possibles :
//   - GET /produits?code_barre=XXX  -> recherche rapide d'un produit precis (exigence du sujet)
//   - GET /produits?categorie=...&statut=...  -> liste filtree pour le back-office
func ListerProduits(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	codeBarre := r.URL.Query().Get("code_barre")
	if codeBarre != "" {
		produit, err := db.GetProduitByCodeBarre(codeBarre)
		if err != nil {
			utils.ErreurServeur(w, r, "Erreur de recherche par code-barre", err)
			return
		}
		if produit == nil {
			http.Error(w, "Aucun produit avec ce code-barre", http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(produit)
		return
	}

	var categorie *string
	if valeur := r.URL.Query().Get("categorie"); valeur != "" {
		categorie = &valeur
	}
	var statut *string
	if valeur := r.URL.Query().Get("statut"); valeur != "" {
		statut = &valeur
	}

	produits, err := db.ListProduits(categorie, statut)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des produits", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(produits)
}

func ObtenirProduit(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	produit, err := db.GetProduitById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du produit", err)
		return
	}
	if produit == nil {
		http.Error(w, "Produit introuvable", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(produit)
}

type deplacementProduitDto struct {
	EmplacementId *int   `json:"emplacement_id"`
	Statut        string `json:"statut"`
}

func DeplacerProduit(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	produit, err := db.GetProduitById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du produit", err)
		return
	}
	if produit == nil {
		http.Error(w, "Produit introuvable", http.StatusNotFound)
		return
	}

	var dto deplacementProduitDto
	err = json.NewDecoder(r.Body).Decode(&dto)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if dto.Statut == "" {
		http.Error(w, "statut est obligatoire", http.StatusBadRequest)
		return
	}

	err = db.UpdateProduitEmplacementEtStatut(id, dto.EmplacementId, dto.Statut)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de mise a jour du produit", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}
