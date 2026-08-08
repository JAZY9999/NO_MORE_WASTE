package db

import (
	"database/sql"
	"fmt"

	"nomorewaste/models"
)

func CreateProduit(p models.Produit) (int, error) {
	var id int
	err := Conn.QueryRow(
		`INSERT INTO produits (code_barre, libelle, categorie, dlc, collecte_id, poids_kg, quantite, emplacement_id, statut)
		 VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9) RETURNING id`,
		p.CodeBarre, p.Libelle, p.Categorie, p.Dlc, p.CollecteId, p.PoidsKg, p.Quantite, p.EmplacementId, p.Statut,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateProduit : %w", err)
	}
	return id, nil
}

func GetProduitById(id int) (*models.Produit, error) {
	var p models.Produit
	row := Conn.QueryRow(
		`SELECT id, code_barre, libelle, categorie, dlc, collecte_id, poids_kg, quantite, emplacement_id, statut
		 FROM produits WHERE id = $1`,
		id,
	)
	err := row.Scan(&p.Id, &p.CodeBarre, &p.Libelle, &p.Categorie, &p.Dlc, &p.CollecteId, &p.PoidsKg, &p.Quantite, &p.EmplacementId, &p.Statut)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetProduitById (id=%v) : %w", id, err)
	}
	return &p, nil
}

// GetProduitByCodeBarre est la recherche rapide exigee par le sujet
// ("stocke et retrouvable tres rapidement") : une simple recherche par
// egalite exacte sur une colonne indexee (voir schema.sql, idx_produits_code_barre).
func GetProduitByCodeBarre(codeBarre string) (*models.Produit, error) {
	var p models.Produit
	row := Conn.QueryRow(
		`SELECT id, code_barre, libelle, categorie, dlc, collecte_id, poids_kg, quantite, emplacement_id, statut
		 FROM produits WHERE code_barre = $1`,
		codeBarre,
	)
	err := row.Scan(&p.Id, &p.CodeBarre, &p.Libelle, &p.Categorie, &p.Dlc, &p.CollecteId, &p.PoidsKg, &p.Quantite, &p.EmplacementId, &p.Statut)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetProduitByCodeBarre (code_barre=%v) : %w", codeBarre, err)
	}
	return &p, nil
}

func ListProduits(categorie *string, statut *string) ([]models.Produit, error) {
	requete := "SELECT id, code_barre, libelle, categorie, dlc, collecte_id, poids_kg, quantite, emplacement_id, statut FROM produits WHERE 1=1"
	var arguments []interface{}
	numeroParametre := 1

	if categorie != nil {
		requete += fmt.Sprintf(" AND categorie = $%d", numeroParametre)
		arguments = append(arguments, *categorie)
		numeroParametre++
	}
	if statut != nil {
		requete += fmt.Sprintf(" AND statut = $%d", numeroParametre)
		arguments = append(arguments, *statut)
		numeroParametre++
	}
	requete += " ORDER BY id"

	rows, err := Conn.Query(requete, arguments...)
	if err != nil {
		return nil, fmt.Errorf("ListProduits : %w", err)
	}
	defer rows.Close()

	var resultats []models.Produit
	for rows.Next() {
		var p models.Produit
		err := rows.Scan(&p.Id, &p.CodeBarre, &p.Libelle, &p.Categorie, &p.Dlc, &p.CollecteId, &p.PoidsKg, &p.Quantite, &p.EmplacementId, &p.Statut)
		if err != nil {
			return nil, fmt.Errorf("ListProduits (scan) : %w", err)
		}
		resultats = append(resultats, p)
	}
	return resultats, nil
}

func UpdateProduitEmplacementEtStatut(id int, emplacementId *int, statut string) error {
	_, err := Conn.Exec(
		"UPDATE produits SET emplacement_id = $1, statut = $2 WHERE id = $3",
		emplacementId, statut, id,
	)
	if err != nil {
		return fmt.Errorf("UpdateProduitEmplacementEtStatut (id=%v) : %w", id, err)
	}
	return nil
}
