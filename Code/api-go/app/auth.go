package app

import (
	"encoding/json"
	"net/http"
	"strings"

	"golang.org/x/crypto/bcrypt"

	"nomorewaste/db"
	"nomorewaste/models"
	"nomorewaste/utils"
)

func verifierIdentifiants(dto models.Identifiants) []string {
	var messagesErreur []string
	if !strings.Contains(dto.Email, "@") {
		messagesErreur = append(messagesErreur, "Email invalide")
	}
	if len(dto.MotDePasse) < 5 || len(dto.MotDePasse) > 50 {
		messagesErreur = append(messagesErreur, "Le mot de passe doit contenir entre 5 et 50 caracteres")
	}
	return messagesErreur
}

func Register(w http.ResponseWriter, r *http.Request) {
	var identifiants models.Identifiants
	err := json.NewDecoder(r.Body).Decode(&identifiants)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	messagesErreur := verifierIdentifiants(identifiants)
	if len(messagesErreur) > 0 {
		erreursFormatees, _ := json.Marshal(messagesErreur)
		w.Header().Set("Content-Type", "application/json")
		http.Error(w, string(erreursFormatees), http.StatusBadRequest)
		return
	}

	utilisateurExistant, err := db.GetUtilisateurByEmail(identifiants.Email)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de l'utilisateur", err)
		return
	}
	if utilisateurExistant != nil {
		http.Error(w, "Email deja utilise", http.StatusConflict)
		return
	}

	hashed, err := bcrypt.GenerateFromPassword([]byte(identifiants.MotDePasse), bcrypt.DefaultCost)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de hashage du mot de passe", err)
		return
	}

	err = db.CreateUtilisateur(identifiants.Email, string(hashed), "adherent")
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de l'utilisateur", err)
		return
	}

	w.WriteHeader(http.StatusCreated)
}

func Login(w http.ResponseWriter, r *http.Request) {
	var identifiants models.Identifiants
	err := json.NewDecoder(r.Body).Decode(&identifiants)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	utilisateurExistant, err := db.GetUtilisateurByEmail(identifiants.Email)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de l'utilisateur", err)
		return
	}
	if utilisateurExistant == nil {
		http.Error(w, "Non autorise", http.StatusUnauthorized)
		return
	}
	if bcrypt.CompareHashAndPassword([]byte(utilisateurExistant.MotDePasseHash), []byte(identifiants.MotDePasse)) != nil {
		http.Error(w, "Non autorise", http.StatusUnauthorized)
		return
	}

	token, err := utils.GenerateJWT(utilisateurExistant.Email, utilisateurExistant.Role)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de generation du token", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"token": token})
}

func Me(w http.ResponseWriter, r *http.Request) {
	tokenString := r.Header.Get("Authorization")
	email, _, err := utils.VerifyJWT(tokenString)
	if err != nil {
		http.Error(w, "Jeton invalide", http.StatusUnauthorized)
		return
	}

	utilisateurExistant, err := db.GetUtilisateurByEmail(email)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de l'utilisateur", err)
		return
	}
	if utilisateurExistant == nil {
		http.Error(w, "Utilisateur introuvable", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(utilisateurExistant)
}
