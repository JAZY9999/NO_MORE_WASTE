package app

import (
	"encoding/json"
	"net/http"
	"strconv"

	"nomorewaste/db"
	"nomorewaste/models"
	"nomorewaste/utils"
)

func CreerCommercant(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var c models.Commercant
	err := json.NewDecoder(r.Body).Decode(&c)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if c.RaisonSociale == "" {
		http.Error(w, "La raison sociale est obligatoire", http.StatusBadRequest)
		return
	}

	id, err := db.CreateCommercant(c)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation du commercant", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerCommercants(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	commercants, err := db.ListCommercants()
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des commercants", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(commercants)
}

func ObtenirCommercant(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	commercant, err := db.GetCommercantById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du commercant", err)
		return
	}
	if commercant == nil {
		http.Error(w, "Commercant introuvable", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(commercant)
}

// ModifierCommercant : PUT /commercants/{id}
//
// Route ajoutee en portant la fiche commercant. Elle manquait, et son absence
// avait une consequence concrete : une boutique enregistree SANS compte ne
// pouvait plus jamais etre rattachee a son proprietaire. Celui-ci se
// connectait, et son espace client repondait "aucune boutique rattachee" --
// sans aucun moyen de corriger la situation depuis l'application.
//
// # MISE A JOUR PARTIELLE
//
// On relit la fiche existante, puis on n'ecrase que les champs REELLEMENT
// envoyes. Sans cela, un formulaire qui n'affiche pas le SIRET l'effacerait
// silencieusement en enregistrant le reste. C'est un piege classique des
// routes PUT : le client doit alors renvoyer l'objet entier, et le moindre
// oubli detruit une donnee.
func ModifierCommercant(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	existant, err := db.GetCommercantById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du commercant", err)
		return
	}
	if existant == nil {
		http.Error(w, "Commercant introuvable", http.StatusNotFound)
		return
	}

	// On decode dans une structure de pointeurs : un champ ABSENT vaut nil,
	// un champ envoye VIDE vaut une chaine vide. Les deux sont distincts, et
	// c'est cette distinction qui rend la mise a jour partielle possible.
	var dto struct {
		RaisonSociale *string `json:"raison_sociale"`
		Siret         *string `json:"siret"`
		Adresse       *string `json:"adresse"`
		Ville         *string `json:"ville"`
		Pays          *string `json:"pays"`
		Email         *string `json:"email"`
		Telephone     *string `json:"telephone"`
		ContactNom    *string `json:"contact_nom"`
		UtilisateurId *int    `json:"utilisateur_id"`
	}
	if err := json.NewDecoder(r.Body).Decode(&dto); err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	modifie := *existant

	if dto.RaisonSociale != nil {
		if *dto.RaisonSociale == "" {
			// Seul champ obligatoire de la table : le vider est refuse ici
			// plutot que de laisser PostgreSQL renvoyer une erreur de
			// contrainte, moins lisible.
			http.Error(w, "raison_sociale ne peut pas etre vide", http.StatusBadRequest)
			return
		}
		modifie.RaisonSociale = *dto.RaisonSociale
	}
	if dto.Siret != nil {
		modifie.Siret = dto.Siret
	}
	if dto.Adresse != nil {
		modifie.Adresse = dto.Adresse
	}
	if dto.Ville != nil {
		modifie.Ville = dto.Ville
	}
	if dto.Pays != nil {
		modifie.Pays = dto.Pays
	}
	if dto.Email != nil {
		modifie.Email = dto.Email
	}
	if dto.Telephone != nil {
		modifie.Telephone = dto.Telephone
	}
	if dto.ContactNom != nil {
		modifie.ContactNom = dto.ContactNom
	}
	if dto.UtilisateurId != nil {
		// 0 sert a DETACHER le compte : un identifiant nul n'existe pas, et
		// c'est la seule valeur que le front puisse envoyer pour dire "plus
		// aucun compte" avec un menu deroulant.
		if *dto.UtilisateurId == 0 {
			modifie.UtilisateurId = nil
		} else {
			modifie.UtilisateurId = dto.UtilisateurId
		}
	}

	// La colonne utilisateur_id est UNIQUE : rattacher un compte deja pris
	// remonte en 409 grace au code 23505 traite dans utils.ErreurServeur.
	if err := db.UpdateCommercant(id, modifie); err != nil {
		utils.ErreurServeur(w, r, "Erreur de modification du commercant", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

func CreerAdhesion(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	commercantId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	commercant, err := db.GetCommercantById(commercantId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du commercant", err)
		return
	}
	if commercant == nil {
		http.Error(w, "Commercant introuvable", http.StatusNotFound)
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

	a.CommercantId = commercantId

	id, err := db.CreateAdhesion(a)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de l'adhesion", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}
