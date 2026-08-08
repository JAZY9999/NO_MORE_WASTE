package db

import (
	"database/sql"
	"fmt"

	"nomorewaste/models"
)

func CreateCampagne(c models.Campagne) (int, error) {
	var id int
	err := Conn.QueryRow(
		`INSERT INTO campagnes (nom, sujet_email, corps_email, critere_ville, critere_pays, critere_statut_adhesion, critere_adhesion_expiree_depuis_jours)
		 VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id`,
		c.Nom, c.SujetEmail, c.CorpsEmail, c.CritereVille, c.CriterePays, c.CritereStatutAdhesion, c.CritereAdhesionExpireeDepuisJours,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateCampagne : %w", err)
	}
	return id, nil
}

func GetCampagneById(id int) (*models.Campagne, error) {
	var c models.Campagne
	row := Conn.QueryRow(
		`SELECT id, nom, sujet_email, corps_email, critere_ville, critere_pays, critere_statut_adhesion, critere_adhesion_expiree_depuis_jours
		 FROM campagnes WHERE id = $1`,
		id,
	)
	err := row.Scan(&c.Id, &c.Nom, &c.SujetEmail, &c.CorpsEmail, &c.CritereVille, &c.CriterePays, &c.CritereStatutAdhesion, &c.CritereAdhesionExpireeDepuisJours)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetCampagneById (id=%v) : %w", id, err)
	}
	return &c, nil
}

func ListCampagnes() ([]models.Campagne, error) {
	rows, err := Conn.Query(
		`SELECT id, nom, sujet_email, corps_email, critere_ville, critere_pays, critere_statut_adhesion, critere_adhesion_expiree_depuis_jours
		 FROM campagnes ORDER BY id`,
	)
	if err != nil {
		return nil, fmt.Errorf("ListCampagnes : %w", err)
	}
	defer rows.Close()

	var resultats []models.Campagne
	for rows.Next() {
		var c models.Campagne
		err := rows.Scan(&c.Id, &c.Nom, &c.SujetEmail, &c.CorpsEmail, &c.CritereVille, &c.CriterePays, &c.CritereStatutAdhesion, &c.CritereAdhesionExpireeDepuisJours)
		if err != nil {
			return nil, fmt.Errorf("ListCampagnes (scan) : %w", err)
		}
		resultats = append(resultats, c)
	}
	return resultats, nil
}

// ResoudreDestinatairesCampagne applique les criteres de la campagne (chacun optionnel)
// pour trouver tous les commercants qui correspondent. Chaque critere fixe est verifie
// avec un simple "si le critere est defini (non nil), alors ajouter la condition SQL
// correspondante" -- on ne construit jamais de SQL a partir de texte libre fourni par
// l'utilisateur, seulement des valeurs inserees via des parametres $N.
func ResoudreDestinatairesCampagne(c models.Campagne) ([]models.DestinataireCampagne, error) {
	requete := `
		SELECT DISTINCT c.id, c.raison_sociale, c.email
		FROM commercants c
		LEFT JOIN adhesions a ON a.commercant_id = c.id
		WHERE 1=1
	`
	var arguments []interface{}
	numeroParametre := 1

	if c.CritereVille != nil {
		requete += fmt.Sprintf(" AND c.ville = $%d", numeroParametre)
		arguments = append(arguments, *c.CritereVille)
		numeroParametre++
	}
	if c.CriterePays != nil {
		requete += fmt.Sprintf(" AND c.pays = $%d", numeroParametre)
		arguments = append(arguments, *c.CriterePays)
		numeroParametre++
	}
	if c.CritereStatutAdhesion != nil {
		requete += fmt.Sprintf(" AND a.statut = $%d", numeroParametre)
		arguments = append(arguments, *c.CritereStatutAdhesion)
		numeroParametre++
	}
	if c.CritereAdhesionExpireeDepuisJours != nil {
		requete += fmt.Sprintf(" AND (CURRENT_DATE - a.date_fin) >= $%d", numeroParametre)
		arguments = append(arguments, *c.CritereAdhesionExpireeDepuisJours)
		numeroParametre++
	}

	rows, err := Conn.Query(requete, arguments...)
	if err != nil {
		return nil, fmt.Errorf("ResoudreDestinatairesCampagne : %w", err)
	}
	defer rows.Close()

	var resultats []models.DestinataireCampagne
	for rows.Next() {
		var d models.DestinataireCampagne
		err := rows.Scan(&d.CommercantId, &d.RaisonSociale, &d.Email)
		if err != nil {
			return nil, fmt.Errorf("ResoudreDestinatairesCampagne (scan) : %w", err)
		}
		resultats = append(resultats, d)
	}
	return resultats, nil
}

func EnregistrerCampagneEnvoi(campagneId int, commercantId int) error {
	_, err := Conn.Exec(
		"INSERT INTO campagne_envois (campagne_id, commercant_id) VALUES ($1, $2)",
		campagneId, commercantId,
	)
	if err != nil {
		return fmt.Errorf("EnregistrerCampagneEnvoi : %w", err)
	}
	return nil
}
