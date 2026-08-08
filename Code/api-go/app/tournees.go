package app

import (
	"encoding/json"
	"net/http"
	"strconv"

	"nomorewaste/db"
	"nomorewaste/models"
	"nomorewaste/utils"
)

// --- Beneficiaires ---

func CreerBeneficiaire(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var b models.Beneficiaire
	err := json.NewDecoder(r.Body).Decode(&b)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if b.Nom == "" || b.Type == "" {
		http.Error(w, "nom et type sont obligatoires", http.StatusBadRequest)
		return
	}

	id, err := db.CreateBeneficiaire(b)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation du beneficiaire", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerBeneficiaires(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var typeBeneficiaire *string
	if valeur := r.URL.Query().Get("type"); valeur != "" {
		typeBeneficiaire = &valeur
	}

	beneficiaires, err := db.ListBeneficiaires(typeBeneficiaire)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des beneficiaires", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(beneficiaires)
}

// --- Tournees ---

func CreerTournee(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var t models.Tournee
	err := json.NewDecoder(r.Body).Decode(&t)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if t.DateTournee == "" {
		http.Error(w, "date_tournee est obligatoire", http.StatusBadRequest)
		return
	}
	if t.Statut == "" {
		t.Statut = "planifiee"
	}

	if t.BenevoleId != nil {
		benevole, err := db.GetBenevoleById(*t.BenevoleId)
		if err != nil {
			utils.ErreurServeur(w, r, "Erreur de recuperation du benevole", err)
			return
		}
		if benevole == nil {
			http.Error(w, "Benevole introuvable", http.StatusNotFound)
			return
		}
		if benevole.Statut != "valide" {
			http.Error(w, "Impossible d'affecter : ce benevole n'est pas valide", http.StatusBadRequest)
			return
		}
	}

	id, err := db.CreateTournee(t)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de la tournee", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerTournees(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var statut *string
	if valeur := r.URL.Query().Get("statut"); valeur != "" {
		statut = &valeur
	}

	tournees, err := db.ListTournees(statut)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des tournees", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(tournees)
}

func ObtenirTournee(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	tournee, err := db.GetTourneeById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la tournee", err)
		return
	}
	if tournee == nil {
		http.Error(w, "Tournee introuvable", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(tournee)
}

type statutTourneeDto struct {
	Statut     string `json:"statut"`
	BenevoleId *int   `json:"benevole_id"`
}

func ModifierStatutTournee(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	tournee, err := db.GetTourneeById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la tournee", err)
		return
	}
	if tournee == nil {
		http.Error(w, "Tournee introuvable", http.StatusNotFound)
		return
	}

	var dto statutTourneeDto
	err = json.NewDecoder(r.Body).Decode(&dto)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}
	if dto.Statut == "" {
		http.Error(w, "statut est obligatoire", http.StatusBadRequest)
		return
	}

	err = db.UpdateStatutTournee(id, dto.Statut, dto.BenevoleId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de mise a jour de la tournee", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

// --- Etapes ---

func AjouterEtapeTournee(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	tourneeId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	tournee, err := db.GetTourneeById(tourneeId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la tournee", err)
		return
	}
	if tournee == nil {
		http.Error(w, "Tournee introuvable", http.StatusNotFound)
		return
	}

	var e models.TourneeEtape
	err = json.NewDecoder(r.Body).Decode(&e)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if e.BeneficiaireId == 0 {
		http.Error(w, "beneficiaire_id est obligatoire", http.StatusBadRequest)
		return
	}

	beneficiaire, err := db.GetBeneficiaireById(e.BeneficiaireId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du beneficiaire", err)
		return
	}
	if beneficiaire == nil {
		http.Error(w, "Beneficiaire introuvable", http.StatusNotFound)
		return
	}

	if e.Statut == "" {
		e.Statut = "a_faire"
	}
	e.TourneeId = tourneeId

	id, err := db.CreateTourneeEtape(e)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de l'etape", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerEtapesTournee(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	tourneeId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	etapes, err := db.ListEtapesParTournee(tourneeId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des etapes", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(etapes)
}

// --- Livraisons ---

type produitLivreDto struct {
	ProduitId int `json:"produit_id"`
	Quantite  int `json:"quantite"`
}

type cloturerLivraisonDto struct {
	Produits []produitLivreDto `json:"produits"`
}

// CloturerLivraison est LE point central de la Phase 5 : quand une etape de
// tournee est livree, on cree la livraison, on y rattache les produits donnes
// (qui passent au statut "distribue" dans le stock), on marque l'etape comme
// livree, et on genere le recapitulatif PDF exige par le sujet.
func CloturerLivraison(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	etapeId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	etape, err := db.GetTourneeEtapeById(etapeId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de l'etape", err)
		return
	}
	if etape == nil {
		http.Error(w, "Etape introuvable", http.StatusNotFound)
		return
	}

	existante, err := db.GetLivraisonParEtape(etapeId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de verification de la livraison", err)
		return
	}
	if existante != nil {
		http.Error(w, "Cette etape a deja fait l'objet d'une livraison", http.StatusConflict)
		return
	}

	var dto cloturerLivraisonDto
	err = json.NewDecoder(r.Body).Decode(&dto)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}
	if len(dto.Produits) == 0 {
		http.Error(w, "au moins un produit est obligatoire", http.StatusBadRequest)
		return
	}

	for _, p := range dto.Produits {
		produit, err := db.GetProduitById(p.ProduitId)
		if err != nil {
			utils.ErreurServeur(w, r, "Erreur de recuperation d'un produit", err)
			return
		}
		if produit == nil {
			http.Error(w, "Produit introuvable : "+strconv.Itoa(p.ProduitId), http.StatusNotFound)
			return
		}
	}

	livraisonId, err := db.CreateLivraison(etapeId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de la livraison", err)
		return
	}

	for _, p := range dto.Produits {
		quantite := p.Quantite
		if quantite == 0 {
			quantite = 1
		}
		err = db.AjouterProduitLivraison(livraisonId, p.ProduitId, quantite)
		if err != nil {
			utils.ErreurServeur(w, r, "Erreur d'ajout d'un produit a la livraison", err)
			return
		}
	}

	err = db.MarquerEtapeLivree(etapeId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de mise a jour de l'etape", err)
		return
	}

	err = db.EnregistrerCheminPdf(livraisonId, "/livraisons/"+strconv.Itoa(livraisonId)+"/pdf")
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur d'enregistrement du PDF", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"id":      livraisonId,
		"pdf_url": "/livraisons/" + strconv.Itoa(livraisonId) + "/pdf",
	})
}

// TelechargerRecapLivraison genere et renvoie le recapitulatif PDF de la
// livraison. Le PDF est produit a la demande a partir des donnees en base,
// il n'est pas stocke sur le disque.
func TelechargerRecapLivraison(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	livraisonId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	recap, err := db.GetRecapLivraison(livraisonId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du recapitulatif", err)
		return
	}
	if recap == nil {
		http.Error(w, "Livraison introuvable", http.StatusNotFound)
		return
	}

	contenuPdf := utils.GenererRecapLivraisonPDF(*recap)

	w.Header().Set("Content-Type", "application/pdf")
	w.Header().Set("Content-Disposition", "attachment; filename=\"recapitulatif-livraison-"+strconv.Itoa(livraisonId)+".pdf\"")
	w.Write(contenuPdf)
}

func ObtenirRecapLivraison(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	livraisonId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	recap, err := db.GetRecapLivraison(livraisonId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du recapitulatif", err)
		return
	}
	if recap == nil {
		http.Error(w, "Livraison introuvable", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(recap)
}
