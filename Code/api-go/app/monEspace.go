package app

import (
	"encoding/json"
	"net/http"
	"strings"

	"nomorewaste/db"
	"nomorewaste/models"
	"nomorewaste/utils"
)

// ---------------------------------------------------------------------------
//  L'ESPACE CLIENT
// ---------------------------------------------------------------------------
//
// Le sujet parle d'un "front office (utilise par les clients de NO MORE
// WASTE)". Jusqu'ici, un commercant ou un benevole connecte ne voyait rien de
// plus qu'un visiteur anonyme : toutes les routes metier etaient reservees au
// personnel. Ce fichier corrige ca.
//
// LA REGLE DE SECURITE QUI GOUVERNE TOUT LE FICHIER
//
// Aucune de ces routes n'accepte d'identifiant venant du client. On part
// TOUJOURS du jeton : jeton -> email -> compte -> sa fiche -> ses donnees.
//
// Si on acceptait un identifiant en parametre (par exemple
// GET /mon-espace/collectes?commercant_id=7), n'importe qui pourrait essayer
// un autre numero et lire les donnees de quelqu'un d'autre. C'est une faille
// classique, appelee "reference directe non securisee".

// utilisateurConnecte retrouve le compte a partir du jeton deja verifie.
//
// RequireRole a valide le jeton et nous a donne l'email ; il reste a charger
// le compte pour disposer de son identifiant. Retourne nil si le compte a
// disparu entre-temps (cas rare : compte supprime alors que le jeton est
// encore valide).
func utilisateurConnecte(w http.ResponseWriter, r *http.Request, email string) *models.Utilisateur {
	utilisateur, err := db.GetUtilisateurByEmail(email)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de l'utilisateur", err)
		return nil
	}
	if utilisateur == nil {
		http.Error(w, "Utilisateur introuvable", http.StatusNotFound)
		return nil
	}
	return utilisateur
}

// monCommercant fait le chemin complet jeton -> fiche commercant.
// Retourne nil si une reponse d'erreur a deja ete envoyee.
func monCommercant(w http.ResponseWriter, r *http.Request, email string) *models.Commercant {
	utilisateur := utilisateurConnecte(w, r, email)
	if utilisateur == nil {
		return nil
	}

	commercant, err := db.GetCommercantByUtilisateurId(utilisateur.Id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du commercant", err)
		return nil
	}
	if commercant == nil {
		// 404 et non 403 : le compte est legitime, il n'a simplement pas
		// encore de fiche commercant rattachee.
		http.Error(w, "Aucune fiche commercant n'est rattachee a votre compte", http.StatusNotFound)
		return nil
	}
	return commercant
}

// monBenevole fait le chemin complet jeton -> fiche benevole.
func monBenevole(w http.ResponseWriter, r *http.Request, email string) *models.Benevole {
	utilisateur := utilisateurConnecte(w, r, email)
	if utilisateur == nil {
		return nil
	}

	benevole, err := db.GetBenevoleByUtilisateurId(utilisateur.Id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du benevole", err)
		return nil
	}
	if benevole == nil {
		http.Error(w, "Aucune fiche benevole n'est rattachee a votre compte", http.StatusNotFound)
		return nil
	}
	return benevole
}

// ---------------------------------------------------------------------------
//  ESPACE COMMERCANT
// ---------------------------------------------------------------------------

// MonEspaceCommercant : GET /mon-espace/commercant
//
// Retourne la fiche du commercant connecte ET son adhesion en cours : c'est
// l'information qui conditionne tout le reste, puisqu'un adherent expire ne
// peut plus etre collecte. Les renvoyer ensemble evite un second appel.
func MonEspaceCommercant(w http.ResponseWriter, r *http.Request) {
	email, ok := utils.RequireRole(w, r, "adherent")
	if !ok {
		return
	}

	commercant := monCommercant(w, r, email)
	if commercant == nil {
		return
	}

	adhesions, err := db.ListAdhesionsByCommercant(commercant.Id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des adhesions", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"commercant": commercant,
		"adhesions":  adhesions,
	})
}

// MesCollectes : GET /mon-espace/collectes
func MesCollectes(w http.ResponseWriter, r *http.Request) {
	email, ok := utils.RequireRole(w, r, "adherent")
	if !ok {
		return
	}

	commercant := monCommercant(w, r, email)
	if commercant == nil {
		return
	}

	collectes, err := db.ListCollectesParCommercant(commercant.Id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des collectes", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(collectes)
}

// DemanderCollecte : POST /mon-espace/collectes
//
// L'action principale d'un commercant : signaler qu'il a des invendus.
//
// La collecte est creee au statut "demandee" et SANS benevole : c'est
// l'association qui planifie ensuite, depuis le back-office. Laisser le
// commercant choisir sa date ferme ou son benevole reviendrait a lui donner
// la main sur l'organisation interne.
func DemanderCollecte(w http.ResponseWriter, r *http.Request) {
	email, ok := utils.RequireRole(w, r, "adherent")
	if !ok {
		return
	}

	commercant := monCommercant(w, r, email)
	if commercant == nil {
		return
	}

	var demande struct {
		DatePrevue string `json:"date_prevue"`
	}
	if err := json.NewDecoder(r.Body).Decode(&demande); err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	demande.DatePrevue = strings.TrimSpace(demande.DatePrevue)
	if demande.DatePrevue == "" {
		http.Error(w, "La date souhaitee est obligatoire", http.StatusBadRequest)
		return
	}

	// On ignore volontairement tout autre champ que le client aurait envoye
	// (statut, benevole_id, commercant_id...) : il ne decide ni de son statut,
	// ni du benevole affecte, ni du commercant concerne.
	collecte := models.Collecte{
		CommercantId: &commercant.Id,
		DatePrevue:   &demande.DatePrevue,
		Statut:       "demandee",
	}

	id, err := db.CreateCollecte(collecte)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de la demande de collecte", err)
		return
	}

	w.WriteHeader(http.StatusCreated)
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"id":     id,
		"statut": "demandee",
	})
}

// ---------------------------------------------------------------------------
//  ESPACE BENEVOLE
// ---------------------------------------------------------------------------

// MonEspaceBenevole : GET /mon-espace/benevole
//
// Retourne la fiche du benevole, ses documents et ses competences.
//
// Les documents sont renvoyes avec la fiche parce que ce sont eux qui
// expliquent le statut : un benevole bloque en "candidat" doit pouvoir voir
// QUEL justificatif manque, sinon il ne comprend pas pourquoi il n'est
// affecte a aucune mission.
func MonEspaceBenevole(w http.ResponseWriter, r *http.Request) {
	email, ok := utils.RequireRole(w, r, "benevole")
	if !ok {
		return
	}

	benevole := monBenevole(w, r, email)
	if benevole == nil {
		return
	}

	documents, err := db.ListDocumentsBenevole(benevole.Id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des documents", err)
		return
	}

	competences, err := db.ListCompetencesBenevole(benevole.Id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des competences", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"benevole":    benevole,
		"documents":   documents,
		"competences": competences,
	})
}

// MonPlanning : GET /mon-espace/planning
//
// Les creneaux A VENIR du benevole connecte. Reutilise la requete a trois
// tables jointes du planning quotidien (db.ListPlanning), en la filtrant sur
// le benevole -- plutot que d'ecrire une seconde requete a maintenir.
func MonPlanning(w http.ResponseWriter, r *http.Request) {
	email, ok := utils.RequireRole(w, r, "benevole")
	if !ok {
		return
	}

	benevole := monBenevole(w, r, email)
	if benevole == nil {
		return
	}

	// date = nil : on veut les creneaux a venir, pas seulement ceux du jour.
	lignes, err := db.ListPlanning(nil, &benevole.Id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du planning", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(lignes)
}
