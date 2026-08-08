package app

import (
	"encoding/json"
	"net/http"
	"strconv"

	"nomorewaste/db"
	"nomorewaste/models"
	"nomorewaste/utils"
)

// PoserCandidature est une route PUBLIQUE (pas de RequireRole) : n'importe
// qui peut candidater pour devenir benevole, sans etre deja connecte.
func PoserCandidature(w http.ResponseWriter, r *http.Request) {
	var b models.Benevole
	err := json.NewDecoder(r.Body).Decode(&b)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if b.Nom == "" || b.Prenom == "" {
		http.Error(w, "nom et prenom sont obligatoires", http.StatusBadRequest)
		return
	}

	// Le client ne decide ni de son statut, ni du compte auquel la fiche est
	// rattachee. On efface donc ce qu'il aurait pu envoyer.
	b.Statut = "candidat"
	b.UtilisateurId = nil

	// Si la candidature est deposee par quelqu'un de CONNECTE, on rattache la
	// fiche a son compte : son espace benevole fonctionnera des la validation.
	// L'identite vient du jeton, jamais du corps de la requete -- cette route
	// est publique, un identifiant envoye par le client permettrait de
	// s'accrocher au compte de n'importe qui.
	//
	// Sans jeton, la candidature reste anonyme : c'est le cas normal d'un
	// visiteur qui decouvre l'association.
	if emailConnecte, _, err := utils.VerifyJWT(r.Header.Get("Authorization")); err == nil {
		utilisateur, err := db.GetUtilisateurByEmail(emailConnecte)
		if err != nil {
			utils.ErreurServeur(w, r, "Erreur de recuperation de l'utilisateur", err)
			return
		}
		if utilisateur != nil {
			b.UtilisateurId = &utilisateur.Id
		}
	}

	id, err := db.CreateBenevole(b)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de la candidature", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerBenevoles(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var statut *string
	if valeur := r.URL.Query().Get("statut"); valeur != "" {
		statut = &valeur
	}

	benevoles, err := db.ListBenevoles(statut)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des benevoles", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(benevoles)
}

func ObtenirBenevole(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	benevole, err := db.GetBenevoleById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du benevole", err)
		return
	}
	if benevole == nil {
		http.Error(w, "Benevole introuvable", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(benevole)
}

type validationBenevoleDto struct {
	Statut string `json:"statut"`
}

// ValiderBenevole est LE point cle de la Phase 6 : on ne peut passer un
// benevole au statut "valide" que si TOUS ses documents (conditions) ont
// deja ete valides par le staff (voir db.TousLesDocumentsSontValides).
func ValiderBenevole(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	benevole, err := db.GetBenevoleById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du benevole", err)
		return
	}
	if benevole == nil {
		http.Error(w, "Benevole introuvable", http.StatusNotFound)
		return
	}

	var dto validationBenevoleDto
	err = json.NewDecoder(r.Body).Decode(&dto)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}
	if dto.Statut != "valide" && dto.Statut != "refuse" && dto.Statut != "en_validation" {
		http.Error(w, "statut doit etre 'en_validation', 'valide' ou 'refuse'", http.StatusBadRequest)
		return
	}

	if dto.Statut == "valide" {
		documentsOk, err := db.TousLesDocumentsSontValides(id)
		if err != nil {
			utils.ErreurServeur(w, r, "Erreur de verification des documents", err)
			return
		}
		if !documentsOk {
			http.Error(w, "Impossible de valider : tous les documents du benevole doivent d'abord etre valides", http.StatusBadRequest)
			return
		}
	}

	err = db.UpdateStatutBenevole(id, dto.Statut)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de mise a jour du benevole", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

// --- Documents ---

func AjouterDocumentBenevole(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	benevoleId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	benevole, err := db.GetBenevoleById(benevoleId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du benevole", err)
		return
	}
	if benevole == nil {
		http.Error(w, "Benevole introuvable", http.StatusNotFound)
		return
	}

	var d models.BenevoleDocument
	err = json.NewDecoder(r.Body).Decode(&d)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}
	if d.TypeDocument == "" {
		http.Error(w, "type_document est obligatoire", http.StatusBadRequest)
		return
	}

	d.BenevoleId = benevoleId

	id, err := db.CreateBenevoleDocument(d)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation du document", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerDocumentsBenevole(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	benevoleId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	documents, err := db.ListDocumentsBenevole(benevoleId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des documents", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(documents)
}

func ValiderDocumentBenevole(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	documentId, err := strconv.Atoi(r.PathValue("docId"))
	if err != nil {
		http.Error(w, "Id de document invalide", http.StatusBadRequest)
		return
	}

	err = db.ValiderDocument(documentId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de validation du document", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

// --- Competences ---

func ListerCompetences(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	competences, err := db.ListCompetences()
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des competences", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(competences)
}

func ListerCompetencesBenevole(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	benevoleId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	competences, err := db.ListCompetencesBenevole(benevoleId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des competences", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(competences)
}

func AjouterCompetenceBenevole(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	benevoleId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}
	competenceId, err := strconv.Atoi(r.PathValue("competenceId"))
	if err != nil {
		http.Error(w, "Id de competence invalide", http.StatusBadRequest)
		return
	}

	benevole, err := db.GetBenevoleById(benevoleId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du benevole", err)
		return
	}
	if benevole == nil {
		http.Error(w, "Benevole introuvable", http.StatusNotFound)
		return
	}

	competence, err := db.GetCompetenceById(competenceId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de la competence", err)
		return
	}
	if competence == nil {
		http.Error(w, "Competence introuvable", http.StatusNotFound)
		return
	}

	dejaPresente, err := db.BenevoleADejaCompetence(benevoleId, competenceId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de verification", err)
		return
	}
	if dejaPresente {
		http.Error(w, "Cette competence est deja associee a ce benevole", http.StatusConflict)
		return
	}

	err = db.AjouterCompetenceBenevole(benevoleId, competenceId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur d'ajout de la competence", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

func RetirerCompetenceBenevole(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	benevoleId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}
	competenceId, err := strconv.Atoi(r.PathValue("competenceId"))
	if err != nil {
		http.Error(w, "Id de competence invalide", http.StatusBadRequest)
		return
	}

	err = db.RetirerCompetenceBenevole(benevoleId, competenceId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de suppression de la competence", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}
