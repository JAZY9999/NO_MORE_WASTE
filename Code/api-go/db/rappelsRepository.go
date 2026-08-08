package db

import (
	"fmt"

	"nomorewaste/models"
)

func ListAdhesionsARenouveler(joursAvant int) ([]models.AdhesionARenouveler, error) {
	rows, err := Conn.Query(`
		SELECT a.id, a.commercant_id, c.raison_sociale, c.email, a.date_fin,
		       (a.date_fin - CURRENT_DATE) AS jours_restants
		FROM adhesions a
		JOIN commercants c ON c.id = a.commercant_id
		WHERE a.statut = 'active'
		  AND (a.date_fin - CURRENT_DATE) = $1
	`, joursAvant)
	if err != nil {
		return nil, fmt.Errorf("ListAdhesionsARenouveler : %w", err)
	}
	defer rows.Close()

	var resultats []models.AdhesionARenouveler
	for rows.Next() {
		var a models.AdhesionARenouveler
		err := rows.Scan(&a.AdhesionId, &a.CommercantId, &a.RaisonSociale, &a.Email, &a.DateFin, &a.JoursRestants)
		if err != nil {
			return nil, fmt.Errorf("ListAdhesionsARenouveler (scan) : %w", err)
		}
		resultats = append(resultats, a)
	}
	return resultats, nil
}

func ListExAbonnesDepuis(joursDepuisExpiration int) ([]models.AdhesionARenouveler, error) {
	rows, err := Conn.Query(`
		SELECT a.id, a.commercant_id, c.raison_sociale, c.email, a.date_fin,
		       (CURRENT_DATE - a.date_fin) AS jours_restants
		FROM adhesions a
		JOIN commercants c ON c.id = a.commercant_id
		WHERE a.statut IN ('expiree', 'resiliee')
		  AND (CURRENT_DATE - a.date_fin) = $1
	`, joursDepuisExpiration)
	if err != nil {
		return nil, fmt.Errorf("ListExAbonnesDepuis : %w", err)
	}
	defer rows.Close()

	var resultats []models.AdhesionARenouveler
	for rows.Next() {
		var a models.AdhesionARenouveler
		err := rows.Scan(&a.AdhesionId, &a.CommercantId, &a.RaisonSociale, &a.Email, &a.DateFin, &a.JoursRestants)
		if err != nil {
			return nil, fmt.Errorf("ListExAbonnesDepuis (scan) : %w", err)
		}
		resultats = append(resultats, a)
	}
	return resultats, nil
}

func RappelDejaEnvoye(adhesionId int, typeRappel string) (bool, error) {
	var count int
	err := Conn.QueryRow(
		"SELECT COUNT(*) FROM adhesion_rappels WHERE adhesion_id = $1 AND type_rappel = $2",
		adhesionId, typeRappel,
	).Scan(&count)
	if err != nil {
		return false, fmt.Errorf("RappelDejaEnvoye : %w", err)
	}
	return count > 0, nil
}

func EnregistrerRappelEnvoye(adhesionId int, typeRappel string, emailDestinataire string) error {
	_, err := Conn.Exec(
		"INSERT INTO adhesion_rappels (adhesion_id, type_rappel, email_destinataire) VALUES ($1, $2, $3)",
		adhesionId, typeRappel, emailDestinataire,
	)
	if err != nil {
		return fmt.Errorf("EnregistrerRappelEnvoye : %w", err)
	}
	return nil
}

func ListHistoriqueRappels(adhesionId int) ([]models.RappelHistorique, error) {
	rows, err := Conn.Query(
		"SELECT id, adhesion_id, type_rappel, date_envoi, email_destinataire FROM adhesion_rappels WHERE adhesion_id = $1 ORDER BY date_envoi",
		adhesionId,
	)
	if err != nil {
		return nil, fmt.Errorf("ListHistoriqueRappels : %w", err)
	}
	defer rows.Close()

	var resultats []models.RappelHistorique
	for rows.Next() {
		var h models.RappelHistorique
		err := rows.Scan(&h.Id, &h.AdhesionId, &h.TypeRappel, &h.DateEnvoi, &h.EmailDestinataire)
		if err != nil {
			return nil, fmt.Errorf("ListHistoriqueRappels (scan) : %w", err)
		}
		resultats = append(resultats, h)
	}
	return resultats, nil
}
