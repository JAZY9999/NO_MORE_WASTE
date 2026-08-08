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

// ---------------------------------------------------------------------------
//  LANGUES
// ---------------------------------------------------------------------------

// ListerLangues : GET /langues
//
// Route PUBLIQUE : le selecteur de langue s'affiche sur toutes les pages du
// site, y compris pour un visiteur non connecte.
func ListerLangues(w http.ResponseWriter, r *http.Request) {
	langues, err := db.ListLangues()
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des langues", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(langues)
}

// CreerLangue : POST /langues
func CreerLangue(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var langue models.Langue
	if err := json.NewDecoder(r.Body).Decode(&langue); err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	langue.Code = strings.ToLower(strings.TrimSpace(langue.Code))
	langue.Libelle = strings.TrimSpace(langue.Libelle)

	if langue.Code == "" || langue.Libelle == "" {
		http.Error(w, "Le code et le libelle sont obligatoires", http.StatusBadRequest)
		return
	}
	if len(langue.Code) > 5 {
		http.Error(w, "Le code de langue ne doit pas depasser 5 caracteres", http.StatusBadRequest)
		return
	}

	existante, err := db.GetLangueByCode(langue.Code)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la langue", err)
		return
	}
	if existante != nil {
		http.Error(w, "Cette langue existe deja", http.StatusConflict)
		return
	}

	if err := db.CreateLangue(langue.Code, langue.Libelle); err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de la langue", err)
		return
	}

	w.WriteHeader(http.StatusCreated)
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(langue)
}

// SupprimerLangue : DELETE /langues/{code}
//
// Supprime aussi toutes les traductions de cette langue (cascade declaree
// dans le schema).
func SupprimerLangue(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back")
	if !ok {
		return
	}

	code := strings.ToLower(r.PathValue("code"))

	// Le francais est la langue de reference : c'est sur elle qu'on retombe
	// quand une cle manque ailleurs. La supprimer casserait ce filet de
	// securite et laisserait des trous dans toutes les autres langues.
	if code == "fr" {
		http.Error(w, "La langue de reference (fr) ne peut pas etre supprimee", http.StatusBadRequest)
		return
	}

	langue, err := db.GetLangueByCode(code)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la langue", err)
		return
	}
	if langue == nil {
		http.Error(w, "Langue introuvable", http.StatusNotFound)
		return
	}

	if err := db.DeleteLangue(code); err != nil {
		utils.ErreurServeur(w, r, "Erreur de suppression de la langue", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

// ---------------------------------------------------------------------------
//  TRADUCTIONS
// ---------------------------------------------------------------------------

// ListerTraductions : GET /traductions  ou  GET /traductions?langue=fr
//
// Route PUBLIQUE : c'est elle qui alimente la regeneration des fichiers JSON
// du front, et elle ne contient aucune donnee sensible (uniquement des
// libelles d'interface).
func ListerTraductions(w http.ResponseWriter, r *http.Request) {
	codeLangue := strings.ToLower(strings.TrimSpace(r.URL.Query().Get("langue")))

	traductions, err := db.ListTraductions(codeLangue)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des traductions", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(traductions)
}

// CreerTraduction : POST /traductions
func CreerTraduction(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var t models.Traduction
	if err := json.NewDecoder(r.Body).Decode(&t); err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if !validerTraduction(w, &t) {
		return
	}

	id, err := db.CreateTraduction(t.Cle, t.Valeur, t.CodeLangue)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de la traduction", err)
		return
	}

	t.Id = id
	w.WriteHeader(http.StatusCreated)
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(t)
}

// ModifierTraduction : PUT /traductions/{id}
func ModifierTraduction(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Identifiant invalide", http.StatusBadRequest)
		return
	}

	existante, err := db.GetTraductionById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la traduction", err)
		return
	}
	if existante == nil {
		http.Error(w, "Traduction introuvable", http.StatusNotFound)
		return
	}

	var t models.Traduction
	if err := json.NewDecoder(r.Body).Decode(&t); err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if !validerTraduction(w, &t) {
		return
	}

	if err := db.UpdateTraduction(id, t.Cle, t.Valeur, t.CodeLangue); err != nil {
		utils.ErreurServeur(w, r, "Erreur de modification de la traduction", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

// SupprimerTraduction : DELETE /traductions/{id}
func SupprimerTraduction(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Identifiant invalide", http.StatusBadRequest)
		return
	}

	existante, err := db.GetTraductionById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la traduction", err)
		return
	}
	if existante == nil {
		http.Error(w, "Traduction introuvable", http.StatusNotFound)
		return
	}

	if err := db.DeleteTraduction(id); err != nil {
		utils.ErreurServeur(w, r, "Erreur de suppression de la traduction", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

// ImporterTraductions : POST /traductions/import
//
// Enregistre plusieurs traductions d'un coup. C'est la route utilisee quand on
// remonte les fichiers JSON du front vers la base.
//
// Corps attendu :
//
//	{"code_langue": "fr", "traductions": {"nav.accueil": "Accueil", ...}}
//
// Chaque cle est creee, ou mise a jour si elle existe deja pour cette langue
// (voir EnregistrerTraduction). Envoyer deux fois le meme fichier ne cree donc
// aucun doublon : l'operation est repetable sans risque.
func ImporterTraductions(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var corps struct {
		CodeLangue  string            `json:"code_langue"`
		Traductions map[string]string `json:"traductions"`
	}

	if err := json.NewDecoder(r.Body).Decode(&corps); err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	corps.CodeLangue = strings.ToLower(strings.TrimSpace(corps.CodeLangue))

	if corps.CodeLangue == "" {
		http.Error(w, "Le code de langue est obligatoire", http.StatusBadRequest)
		return
	}
	if len(corps.Traductions) == 0 {
		http.Error(w, "Aucune traduction a importer", http.StatusBadRequest)
		return
	}

	langue, err := db.GetLangueByCode(corps.CodeLangue)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la langue", err)
		return
	}
	if langue == nil {
		http.Error(w, "Langue introuvable : creez-la avant d'importer ses traductions", http.StatusNotFound)
		return
	}

	enregistrees := 0
	for cle, valeur := range corps.Traductions {
		cle = strings.TrimSpace(cle)
		if cle == "" {
			continue
		}

		if err := db.EnregistrerTraduction(cle, valeur, corps.CodeLangue); err != nil {
			utils.ErreurServeur(w, r, "Erreur d'enregistrement d'une traduction", err)
			return
		}
		enregistrees++
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"code_langue":  corps.CodeLangue,
		"enregistrees": enregistrees,
	})
}

// validerTraduction verifie les champs communs a la creation et a la
// modification. Retourne false si une reponse d'erreur a deja ete envoyee.
func validerTraduction(w http.ResponseWriter, t *models.Traduction) bool {
	t.Cle = strings.TrimSpace(t.Cle)
	t.Valeur = strings.TrimSpace(t.Valeur)
	t.CodeLangue = strings.ToLower(strings.TrimSpace(t.CodeLangue))

	if t.Cle == "" || t.Valeur == "" || t.CodeLangue == "" {
		http.Error(w, "La cle, la valeur et le code de langue sont obligatoires", http.StatusBadRequest)
		return false
	}
	if len(t.Cle) > 100 {
		http.Error(w, "La cle ne doit pas depasser 100 caracteres", http.StatusBadRequest)
		return false
	}

	return true
}
