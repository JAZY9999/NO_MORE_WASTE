package db

import (
	"database/sql"
	"fmt"

	"nomorewaste/models"
)

func GetUtilisateurByEmail(email string) (*models.Utilisateur, error) {
	var u models.Utilisateur
	row := Conn.QueryRow("SELECT id, email, mot_de_passe_hash, role, nom, prenom, date_naissance, telephone, actif FROM utilisateurs WHERE email = $1", email)
	err := row.Scan(&u.Id, &u.Email, &u.MotDePasseHash, &u.Role, &u.Nom, &u.Prenom, &u.DateNaissance, &u.Telephone, &u.Actif)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetUtilisateurByEmail (email=%v) : %w", email, err)
	}
	return &u, nil
}

func CreateUtilisateur(email string, motDePasseHash string, role string) error {
	_, err := Conn.Exec("INSERT INTO utilisateurs (email, mot_de_passe_hash, role) VALUES ($1, $2, $3)",
		email, motDePasseHash, role)
	if err != nil {
		return fmt.Errorf("CreateUtilisateur : %w", err)
	}
	return nil
}

// ListUtilisateurs retourne tous les comptes, sans les mots de passe haches
// (le champ porte `json:"-"` dans le modele : il n'est jamais serialise).
func ListUtilisateurs() ([]models.Utilisateur, error) {
	rows, err := Conn.Query(
		`SELECT id, email, role, nom, prenom, date_naissance, telephone, actif
		 FROM utilisateurs ORDER BY id`,
	)
	if err != nil {
		return nil, fmt.Errorf("ListUtilisateurs : %w", err)
	}
	defer rows.Close()

	utilisateurs := make([]models.Utilisateur, 0)
	for rows.Next() {
		var u models.Utilisateur
		err := rows.Scan(&u.Id, &u.Email, &u.Role, &u.Nom, &u.Prenom,
			&u.DateNaissance, &u.Telephone, &u.Actif)
		if err != nil {
			return nil, fmt.Errorf("ListUtilisateurs (scan) : %w", err)
		}
		utilisateurs = append(utilisateurs, u)
	}
	return utilisateurs, nil
}
