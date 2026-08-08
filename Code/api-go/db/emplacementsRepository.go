package db

import (
	"database/sql"
	"fmt"

	"nomorewaste/models"
)

func CreateEmplacement(e models.EmplacementStock) (int, error) {
	var id int
	err := Conn.QueryRow(
		"INSERT INTO emplacements_stock (entrepot, zone, rayon, etagere) VALUES ($1, $2, $3, $4) RETURNING id",
		e.Entrepot, e.Zone, e.Rayon, e.Etagere,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateEmplacement : %w", err)
	}
	return id, nil
}

func GetEmplacementById(id int) (*models.EmplacementStock, error) {
	var e models.EmplacementStock
	row := Conn.QueryRow(
		"SELECT id, entrepot, zone, rayon, etagere FROM emplacements_stock WHERE id = $1",
		id,
	)
	err := row.Scan(&e.Id, &e.Entrepot, &e.Zone, &e.Rayon, &e.Etagere)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetEmplacementById (id=%v) : %w", id, err)
	}
	return &e, nil
}

func ListEmplacements() ([]models.EmplacementStock, error) {
	rows, err := Conn.Query("SELECT id, entrepot, zone, rayon, etagere FROM emplacements_stock ORDER BY id")
	if err != nil {
		return nil, fmt.Errorf("ListEmplacements : %w", err)
	}
	defer rows.Close()

	var resultats []models.EmplacementStock
	for rows.Next() {
		var e models.EmplacementStock
		err := rows.Scan(&e.Id, &e.Entrepot, &e.Zone, &e.Rayon, &e.Etagere)
		if err != nil {
			return nil, fmt.Errorf("ListEmplacements (scan) : %w", err)
		}
		resultats = append(resultats, e)
	}
	return resultats, nil
}
