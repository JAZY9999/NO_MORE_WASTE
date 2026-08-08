package db

import (
	"database/sql"
	"fmt"

	"nomorewaste/models"
)

func CreateCollecte(c models.Collecte) (int, error) {
	var id int
	err := Conn.QueryRow(
		`INSERT INTO collectes (commercant_id, particulier_nom, particulier_adresse, benevole_id, date_prevue, statut)
		 VALUES ($1, $2, $3, $4, $5, $6) RETURNING id`,
		c.CommercantId, c.ParticulierNom, c.ParticulierAdresse, c.BenevoleId, c.DatePrevue, c.Statut,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateCollecte : %w", err)
	}
	return id, nil
}

func GetCollecteById(id int) (*models.Collecte, error) {
	var c models.Collecte
	row := Conn.QueryRow(
		`SELECT id, commercant_id, particulier_nom, particulier_adresse, benevole_id, date_prevue, date_realisee, statut
		 FROM collectes WHERE id = $1`,
		id,
	)
	err := row.Scan(&c.Id, &c.CommercantId, &c.ParticulierNom, &c.ParticulierAdresse, &c.BenevoleId, &c.DatePrevue, &c.DateRealisee, &c.Statut)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetCollecteById (id=%v) : %w", id, err)
	}
	return &c, nil
}

func ListCollectes(statut *string) ([]models.Collecte, error) {
	requete := `SELECT id, commercant_id, particulier_nom, particulier_adresse, benevole_id, date_prevue, date_realisee, statut
	            FROM collectes WHERE 1=1`
	var arguments []interface{}
	if statut != nil {
		requete += " AND statut = $1"
		arguments = append(arguments, *statut)
	}
	requete += " ORDER BY id"

	rows, err := Conn.Query(requete, arguments...)
	if err != nil {
		return nil, fmt.Errorf("ListCollectes : %w", err)
	}
	defer rows.Close()

	var resultats []models.Collecte
	for rows.Next() {
		var c models.Collecte
		err := rows.Scan(&c.Id, &c.CommercantId, &c.ParticulierNom, &c.ParticulierAdresse, &c.BenevoleId, &c.DatePrevue, &c.DateRealisee, &c.Statut)
		if err != nil {
			return nil, fmt.Errorf("ListCollectes (scan) : %w", err)
		}
		resultats = append(resultats, c)
	}
	return resultats, nil
}

// UpdateStatutCollecte change le statut d'une collecte, et optionnellement le
// benevole affecte. Quand le statut passe a "realisee", date_realisee est
// automatiquement rempli avec la date/heure actuelle (now()) directement en SQL.
func UpdateStatutCollecte(id int, statut string, benevoleId *int) error {
	var err error
	if statut == "realisee" {
		_, err = Conn.Exec(
			"UPDATE collectes SET statut = $1, benevole_id = $2, date_realisee = now() WHERE id = $3",
			statut, benevoleId, id,
		)
	} else {
		_, err = Conn.Exec(
			"UPDATE collectes SET statut = $1, benevole_id = $2 WHERE id = $3",
			statut, benevoleId, id,
		)
	}
	if err != nil {
		return fmt.Errorf("UpdateStatutCollecte (id=%v) : %w", id, err)
	}
	return nil
}

func ListProduitsParCollecte(collecteId int) ([]models.Produit, error) {
	rows, err := Conn.Query(
		`SELECT id, code_barre, libelle, categorie, dlc, collecte_id, poids_kg, quantite, emplacement_id, statut
		 FROM produits WHERE collecte_id = $1 ORDER BY id`,
		collecteId,
	)
	if err != nil {
		return nil, fmt.Errorf("ListProduitsParCollecte : %w", err)
	}
	defer rows.Close()

	var resultats []models.Produit
	for rows.Next() {
		var p models.Produit
		err := rows.Scan(&p.Id, &p.CodeBarre, &p.Libelle, &p.Categorie, &p.Dlc, &p.CollecteId, &p.PoidsKg, &p.Quantite, &p.EmplacementId, &p.Statut)
		if err != nil {
			return nil, fmt.Errorf("ListProduitsParCollecte (scan) : %w", err)
		}
		resultats = append(resultats, p)
	}
	return resultats, nil
}

// ListCollectesParCommercant retourne les collectes d'un commercant precis.
//
// Sert a l'espace client : un commercant connecte voit SES collectes.
// L'identifiant vient toujours de sa fiche (retrouvee via son compte), jamais
// d'un parametre fourni par le client.
func ListCollectesParCommercant(commercantId int) ([]models.Collecte, error) {
	rows, err := Conn.Query(
		`SELECT id, commercant_id, particulier_nom, particulier_adresse, benevole_id,
		        date_prevue, date_realisee, statut
		 FROM collectes
		 WHERE commercant_id = $1
		 ORDER BY date_prevue DESC`,
		commercantId,
	)
	if err != nil {
		return nil, fmt.Errorf("ListCollectesParCommercant (commercant=%v) : %w", commercantId, err)
	}
	defer rows.Close()

	collectes := make([]models.Collecte, 0)
	for rows.Next() {
		var c models.Collecte
		err := rows.Scan(&c.Id, &c.CommercantId, &c.ParticulierNom, &c.ParticulierAdresse,
			&c.BenevoleId, &c.DatePrevue, &c.DateRealisee, &c.Statut)
		if err != nil {
			return nil, fmt.Errorf("ListCollectesParCommercant (scan) : %w", err)
		}
		collectes = append(collectes, c)
	}
	return collectes, nil
}
