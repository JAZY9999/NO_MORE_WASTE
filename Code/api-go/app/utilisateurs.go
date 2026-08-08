package app

import (
	"encoding/json"
	"net/http"
	"strings"

	"golang.org/x/crypto/bcrypt"

	"nomorewaste/db"
	"nomorewaste/utils"
)

// ---------------------------------------------------------------------------
//  GESTION DES COMPTES
// ---------------------------------------------------------------------------
//
// Ce fichier comble le dernier trou connu de l'API.
//
// Jusqu'ici, POST /auth/register creait TOUJOURS un compte "adherent" : le
// role etait ecrit en dur. Creer un compte pour un membre du personnel
// imposait donc une requete SQL a la main :
//
//	UPDATE utilisateurs SET role='admin_back' WHERE email='...';
//
// Ce qui veut dire qu'installer l'application sur un serveur neuf demandait
// d'ouvrir un client PostgreSQL. Inacceptable pour un produit "packagé pour
// pouvoir etre aisement deploye", comme le demande le sujet.

// Les seuls roles acceptes. On ne fait jamais confiance a la chaine envoyee
// par le client : sans cette liste, il suffirait d'inventer un role
// ("super_admin") pour creer un compte qu'aucune garde ne reconnaitrait --
// ou pire, de se voir refuser l'acces partout sans comprendre pourquoi.
var rolesAutorises = []string{"admin_back", "staff_back", "adherent", "benevole"}

func roleValide(role string) bool {
	for _, r := range rolesAutorises {
		if r == role {
			return true
		}
	}
	return false
}

// ListerUtilisateurs : GET /utilisateurs
//
// Reserve a admin_back : la liste des comptes et de leurs roles renseigne sur
// qui peut faire quoi dans l'application.
func ListerUtilisateurs(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back")
	if !ok {
		return
	}

	utilisateurs, err := db.ListUtilisateurs()
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des utilisateurs", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(utilisateurs)
}

// CreerUtilisateur : POST /utilisateurs
//
// Cree un compte AVEC choix du role. C'est ce qui manquait.
//
// Reserve a admin_back, et pas a staff_back : pouvoir creer des comptes, c'est
// pouvoir se fabriquer un acces. Cette capacite ne se delegue pas.
func CreerUtilisateur(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back")
	if !ok {
		return
	}

	var demande struct {
		Email      string `json:"email"`
		MotDePasse string `json:"mot_de_passe"`
		Role       string `json:"role"`
	}

	if err := json.NewDecoder(r.Body).Decode(&demande); err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	demande.Email = strings.ToLower(strings.TrimSpace(demande.Email))
	demande.Role = strings.TrimSpace(demande.Role)

	// Les memes regles qu'a l'inscription : un compte cree par un
	// administrateur n'a pas a etre moins bien controle qu'un compte cree par
	// l'interessé.
	if !strings.Contains(demande.Email, "@") {
		http.Error(w, "Email invalide", http.StatusBadRequest)
		return
	}
	if len(demande.MotDePasse) < 5 || len(demande.MotDePasse) > 50 {
		http.Error(w, "Le mot de passe doit contenir entre 5 et 50 caracteres", http.StatusBadRequest)
		return
	}
	if !roleValide(demande.Role) {
		http.Error(w,
			"Role invalide (attendu : admin_back, staff_back, adherent ou benevole)",
			http.StatusBadRequest)
		return
	}

	existant, err := db.GetUtilisateurByEmail(demande.Email)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de l'utilisateur", err)
		return
	}
	if existant != nil {
		http.Error(w, "Email deja utilise", http.StatusConflict)
		return
	}

	// bcrypt, comme a l'inscription : le mot de passe n'est jamais stocke en
	// clair, et le hachage est volontairement lent pour rendre les attaques
	// par force brute couteuses.
	hache, err := bcrypt.GenerateFromPassword([]byte(demande.MotDePasse), bcrypt.DefaultCost)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de hashage du mot de passe", err)
		return
	}

	if err := db.CreateUtilisateur(demande.Email, string(hache), demande.Role); err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de l'utilisateur", err)
		return
	}

	w.WriteHeader(http.StatusCreated)
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{
		"email": demande.Email,
		"role":  demande.Role,
	})
}
