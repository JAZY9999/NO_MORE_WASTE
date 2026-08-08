package db

import (
	"database/sql"
	"fmt"

	"nomorewaste/models"
)

func CreateCommercant(c models.Commercant) (int, error) {
	var id int
	err := Conn.QueryRow(
		// utilisateur_id : le compte qui pourra consulter l'espace commercant.
		//
		// Il manquait. Consequence : une boutique creee par l'API n'etait
		// rattachee a AUCUN compte, et son proprietaire ne pouvait pas ouvrir
		// son espace client -- une exigence du sujet. La seule facon de faire
		// la liaison etait une requete SQL a la main (c'est ce que faisaient
		// les scripts de test). Trouve en portant l'espace commercant.
		//
		// La colonne est UNIQUE : rattacher un compte deja pris repond 409,
		// grace au code 23505 traite dans utils.ErreurServeur.
		`INSERT INTO commercants
		   (raison_sociale, siret, adresse, ville, pays, email, telephone, contact_nom, utilisateur_id)
		 VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9) RETURNING id`,
		c.RaisonSociale, c.Siret, c.Adresse, c.Ville, c.Pays, c.Email, c.Telephone, c.ContactNom,
		c.UtilisateurId,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateCommercant : %w", err)
	}
	return id, nil
}

// UpdateCommercant remplace la fiche d'un commercant.
//
// # POURQUOI UN REMPLACEMENT COMPLET ET NON UNE MISE A JOUR PARTIELLE
//
// Le handler relit d'abord la fiche existante et n'ecrase que les champs
// reellement fournis. Quand la requete arrive ici, la structure contient donc
// deja l'etat final voulu : il n'y a plus qu'a l'ecrire. Une requete SQL
// construite dynamiquement selon les champs presents serait plus longue, plus
// difficile a lire, et n'apporterait rien.
//
// utilisateur_id fait partie des champs modifiables : c'est ce qui permet de
// rattacher une boutique DEJA CREEE au compte de son proprietaire. Sans cette
// fonction, le rattachement n'etait possible qu'a la creation -- et une
// boutique enregistree sans compte ne pouvait plus jamais ouvrir son espace
// client autrement qu'avec une requete SQL a la main.
func UpdateCommercant(id int, c models.Commercant) error {
	_, err := Conn.Exec(
		`UPDATE commercants
		 SET raison_sociale = $1, siret = $2, adresse = $3, ville = $4, pays = $5,
		     email = $6, telephone = $7, contact_nom = $8, utilisateur_id = $9
		 WHERE id = $10`,
		c.RaisonSociale, c.Siret, c.Adresse, c.Ville, c.Pays,
		c.Email, c.Telephone, c.ContactNom, c.UtilisateurId, id,
	)
	if err != nil {
		return fmt.Errorf("UpdateCommercant (id=%v) : %w", id, err)
	}
	return nil
}

func GetCommercantById(id int) (*models.Commercant, error) {
	var c models.Commercant
	row := Conn.QueryRow(
		"SELECT id, raison_sociale, siret, adresse, ville, pays, email, telephone, contact_nom, utilisateur_id FROM commercants WHERE id = $1",
		id,
	)
	err := row.Scan(&c.Id, &c.RaisonSociale, &c.Siret, &c.Adresse, &c.Ville, &c.Pays, &c.Email, &c.Telephone, &c.ContactNom, &c.UtilisateurId)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetCommercantById (id=%v) : %w", id, err)
	}
	return &c, nil
}

func ListCommercants() ([]models.Commercant, error) {
	rows, err := Conn.Query(
		"SELECT id, raison_sociale, siret, adresse, ville, pays, email, telephone, contact_nom, utilisateur_id FROM commercants ORDER BY id",
	)
	if err != nil {
		return nil, fmt.Errorf("ListCommercants : %w", err)
	}
	defer rows.Close()

	var commercants []models.Commercant
	for rows.Next() {
		var c models.Commercant
		err := rows.Scan(&c.Id, &c.RaisonSociale, &c.Siret, &c.Adresse, &c.Ville, &c.Pays, &c.Email, &c.Telephone, &c.ContactNom, &c.UtilisateurId)
		if err != nil {
			return nil, fmt.Errorf("ListCommercants (scan) : %w", err)
		}
		commercants = append(commercants, c)
	}
	return commercants, nil
}

// GetCommercantByUtilisateurId retrouve le commercant rattache a un compte.
//
// C'est la fonction qui rend l'espace client possible : on part du compte
// connecte (issu du jeton) pour retrouver SA fiche. Le client ne fournit
// jamais d'identifiant de commercant -- sinon il suffirait d'en essayer un
// autre pour lire les donnees de quelqu'un d'autre.
//
// Retourne (nil, nil) si le compte n'est rattache a aucun commercant : c'est
// le cas d'un adherent inscrit qui n'a pas encore de fiche.
func GetCommercantByUtilisateurId(utilisateurId int) (*models.Commercant, error) {
	var c models.Commercant
	row := Conn.QueryRow(
		`SELECT id, raison_sociale, siret, adresse, ville, pays, email, telephone,
		        contact_nom, utilisateur_id
		 FROM commercants WHERE utilisateur_id = $1`,
		utilisateurId,
	)
	err := row.Scan(&c.Id, &c.RaisonSociale, &c.Siret, &c.Adresse, &c.Ville, &c.Pays,
		&c.Email, &c.Telephone, &c.ContactNom, &c.UtilisateurId)

	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetCommercantByUtilisateurId (utilisateur=%v) : %w", utilisateurId, err)
	}
	return &c, nil
}
