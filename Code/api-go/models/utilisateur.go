package models

import "time"

type Utilisateur struct {
	Id             int        `json:"id"`
	Email          string     `json:"email"`
	MotDePasseHash string     `json:"-"`
	Role           string     `json:"role"`
	Nom            *string    `json:"nom"`
	Prenom         *string    `json:"prenom"`
	DateNaissance  *time.Time `json:"date_naissance"`
	Telephone      *string    `json:"telephone"`
	Actif          bool       `json:"actif"`
}

// Identifiants represente ce que le client envoie pour s'inscrire ou se connecter.
// C'est un "DTO" : une structure qui ne sert qu'a recevoir des donnees, elle ne
// correspond a aucune table de la base.
//
// Les noms de champs JSON sont en francais comme partout ailleurs dans l'API
// (raison_sociale, date_debut, code_barre...). Cette structure s'appelait
// "Credentials" avec un champ "password" : c'etait la seule exception anglaise
// de tout le projet, corrigee lors de la consolidation (Phase 10).
type Identifiants struct {
	Email      string `json:"email"`
	MotDePasse string `json:"mot_de_passe"`
}
