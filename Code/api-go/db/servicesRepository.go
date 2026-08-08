package db

import (
	"database/sql"
	"fmt"

	"nomorewaste/models"
)

// --- Services ---

func CreateService(s models.Service) (int, error) {
	var id int
	err := Conn.QueryRow(
		"INSERT INTO services (nom, description, competence_requise_id, type, actif) VALUES ($1, $2, $3, $4, $5) RETURNING id",
		s.Nom, s.Description, s.CompetenceRequiseId, s.Type, s.Actif,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateService : %w", err)
	}
	return id, nil
}

func GetServiceById(id int) (*models.Service, error) {
	var s models.Service
	row := Conn.QueryRow(
		"SELECT id, nom, description, competence_requise_id, type, actif FROM services WHERE id = $1",
		id,
	)
	err := row.Scan(&s.Id, &s.Nom, &s.Description, &s.CompetenceRequiseId, &s.Type, &s.Actif)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetServiceById (id=%v) : %w", id, err)
	}
	return &s, nil
}

func ListServices(typeService *string) ([]models.Service, error) {
	requete := "SELECT id, nom, description, competence_requise_id, type, actif FROM services WHERE 1=1"
	var arguments []interface{}
	if typeService != nil {
		requete += " AND type = $1"
		arguments = append(arguments, *typeService)
	}
	requete += " ORDER BY id"

	rows, err := Conn.Query(requete, arguments...)
	if err != nil {
		return nil, fmt.Errorf("ListServices : %w", err)
	}
	defer rows.Close()

	var resultats []models.Service
	for rows.Next() {
		var s models.Service
		err := rows.Scan(&s.Id, &s.Nom, &s.Description, &s.CompetenceRequiseId, &s.Type, &s.Actif)
		if err != nil {
			return nil, fmt.Errorf("ListServices (scan) : %w", err)
		}
		resultats = append(resultats, s)
	}
	return resultats, nil
}

// --- Creneaux ---

func CreateCreneau(c models.CreneauService) (int, error) {
	var id int
	err := Conn.QueryRow(
		`INSERT INTO creneaux_service (service_id, benevole_id, date_creneau, heure_debut, heure_fin, lieu, capacite_max, statut)
		 VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING id`,
		c.ServiceId, c.BenevoleId, c.DateCreneau, c.HeureDebut, c.HeureFin, c.Lieu, c.CapaciteMax, c.Statut,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateCreneau : %w", err)
	}
	return id, nil
}

func GetCreneauById(id int) (*models.CreneauService, error) {
	var c models.CreneauService
	row := Conn.QueryRow(
		// to_char : voir l'explication dans ListCreneauxParService.
		`SELECT id, service_id, benevole_id, date_creneau,
		        to_char(heure_debut, 'HH24:MI'), to_char(heure_fin, 'HH24:MI'),
		        lieu, capacite_max, statut
		 FROM creneaux_service WHERE id = $1`,
		id,
	)
	err := row.Scan(&c.Id, &c.ServiceId, &c.BenevoleId, &c.DateCreneau, &c.HeureDebut, &c.HeureFin, &c.Lieu, &c.CapaciteMax, &c.Statut)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetCreneauById (id=%v) : %w", id, err)
	}
	return &c, nil
}

func ListCreneauxParService(serviceId int) ([]models.CreneauService, error) {
	rows, err := Conn.Query(
		// POURQUOI to_char SUR LES HEURES
		//
		// heure_debut est une colonne TIME. Lue directement dans une chaine,
		// database/sql la recoit comme une date complete et la formate en
		// "0000-01-01T14:00:00Z" : une heure de creneau affublee d'une annee
		// zero. Tout client devrait alors savoir qu'il faut ignorer les onze
		// premiers caracteres.
		//
		// to_char demande a PostgreSQL de renvoyer directement "14:00".
		// Le meme correctif a ete applique aux etapes de tournee.
		//
		// Le tri, lui, porte toujours sur la colonne TIME et non sur le
		// texte : trier des heures comme du texte marcherait par chance en
		// 24 h, mais pas avec un format sur 12 h.
		`SELECT id, service_id, benevole_id, date_creneau,
		        to_char(heure_debut, 'HH24:MI'), to_char(heure_fin, 'HH24:MI'),
		        lieu, capacite_max, statut
		 FROM creneaux_service WHERE service_id = $1 ORDER BY date_creneau, heure_debut`,
		serviceId,
	)
	if err != nil {
		return nil, fmt.Errorf("ListCreneauxParService : %w", err)
	}
	defer rows.Close()

	var resultats []models.CreneauService
	for rows.Next() {
		var c models.CreneauService
		err := rows.Scan(&c.Id, &c.ServiceId, &c.BenevoleId, &c.DateCreneau, &c.HeureDebut, &c.HeureFin, &c.Lieu, &c.CapaciteMax, &c.Statut)
		if err != nil {
			return nil, fmt.Errorf("ListCreneauxParService (scan) : %w", err)
		}
		resultats = append(resultats, c)
	}
	return resultats, nil
}

func AffecterBenevoleCreneau(creneauId int, benevoleId *int) error {
	_, err := Conn.Exec("UPDATE creneaux_service SET benevole_id = $1 WHERE id = $2", benevoleId, creneauId)
	if err != nil {
		return fmt.Errorf("AffecterBenevoleCreneau (id=%v) : %w", creneauId, err)
	}
	return nil
}

// --- Inscriptions ---

func CreateInscription(i models.InscriptionService) (int, error) {
	var id int
	err := Conn.QueryRow(
		"INSERT INTO inscriptions_service (creneau_id, commercant_id, utilisateur_id, statut) VALUES ($1, $2, $3, $4) RETURNING id",
		i.CreneauId, i.CommercantId, i.UtilisateurId, i.Statut,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateInscription : %w", err)
	}
	return id, nil
}

func ListInscriptionsParCreneau(creneauId int) ([]models.InscriptionService, error) {
	rows, err := Conn.Query(
		`SELECT id, creneau_id, commercant_id, utilisateur_id, date_inscription, statut
		 FROM inscriptions_service WHERE creneau_id = $1 ORDER BY id`,
		creneauId,
	)
	if err != nil {
		return nil, fmt.Errorf("ListInscriptionsParCreneau : %w", err)
	}
	defer rows.Close()

	var resultats []models.InscriptionService
	for rows.Next() {
		var i models.InscriptionService
		err := rows.Scan(&i.Id, &i.CreneauId, &i.CommercantId, &i.UtilisateurId, &i.DateInscription, &i.Statut)
		if err != nil {
			return nil, fmt.Errorf("ListInscriptionsParCreneau (scan) : %w", err)
		}
		resultats = append(resultats, i)
	}
	return resultats, nil
}

// CompterInscriptionsActives compte les inscriptions non annulees d'un creneau,
// pour verifier que la capacite maximale n'est pas depassee avant d'en ajouter une.
func CompterInscriptionsActives(creneauId int) (int, error) {
	var count int
	err := Conn.QueryRow(
		"SELECT COUNT(*) FROM inscriptions_service WHERE creneau_id = $1 AND statut != 'annule'",
		creneauId,
	).Scan(&count)
	if err != nil {
		return 0, fmt.Errorf("CompterInscriptionsActives : %w", err)
	}
	return count, nil
}

// --- Planning quotidien des benevoles ---

// ListPlanningDuJour retourne, pour une date donnee, tous les creneaux affectes
// a un benevole, avec les informations necessaires pour construire son planning
// (nom du service, horaires, lieu) et pour le lui envoyer (email).
// ListPlanningDuJour retourne les creneaux d'une journee, tous benevoles
// confondus. C'est ce qu'utilise le job d'envoi des plannings quotidiens.
func ListPlanningDuJour(date string) ([]models.LignePlanning, error) {
	return ListPlanning(&date, nil)
}

// ListPlanning est la version generale : les deux filtres sont facultatifs.
//
//	date == nil       -> les creneaux A VENIR (aujourd'hui et apres)
//	date != nil       -> ce jour precis
//	benevoleId != nil -> uniquement ce benevole
//
// Une seule fonction pour deux usages tres differents (le job quotidien et
// l'espace benevole), parce que la requete a trois tables jointes est la meme.
// La dupliquer signifierait la corriger a deux endroits le jour ou le modele
// change -- et en oublier un.
func ListPlanning(date *string, benevoleId *int) ([]models.LignePlanning, error) {
	// to_char sur les heures, par coherence avec ListCreneauxParService.
	//
	// Le CSV, lui, n'etait PAS casse : utils.formaterHeure sait deja lire les
	// deux formes. Mais il devait le savoir -- c'est exactement le rustine que
	// to_char rend inutile. Sa branche de repli (valeur[:5]) devient desormais
	// le chemin normal.
	requete := `SELECT b.id, b.nom, b.prenom, b.email, s.nom, cs.date_creneau,
	                   to_char(cs.heure_debut, 'HH24:MI'),
	                   to_char(cs.heure_fin, 'HH24:MI'),
	                   cs.lieu
	            FROM creneaux_service cs
	            JOIN benevoles b ON b.id = cs.benevole_id
	            JOIN services s ON s.id = cs.service_id
	            WHERE cs.statut != 'annule'`

	// La STRUCTURE de la requete change selon les filtres, mais les valeurs
	// passent toujours par $1, $2... C'est ce qui protege de l'injection SQL.
	var arguments []interface{}

	if date != nil {
		arguments = append(arguments, *date)
		requete += fmt.Sprintf(" AND cs.date_creneau = $%d", len(arguments))
	} else {
		// Pas de date fournie : on ne montre que ce qui reste a venir.
		// Un benevole n'a pas besoin de revoir les creneaux passes.
		requete += " AND cs.date_creneau >= CURRENT_DATE"
	}

	if benevoleId != nil {
		arguments = append(arguments, *benevoleId)
		requete += fmt.Sprintf(" AND b.id = $%d", len(arguments))
	}

	requete += " ORDER BY cs.date_creneau, cs.heure_debut, b.id"

	rows, err := Conn.Query(requete, arguments...)
	if err != nil {
		return nil, fmt.Errorf("ListPlanning : %w", err)
	}
	defer rows.Close()

	resultats := make([]models.LignePlanning, 0)
	for rows.Next() {
		var l models.LignePlanning
		err := rows.Scan(&l.BenevoleId, &l.Nom, &l.Prenom, &l.Email, &l.ServiceNom,
			&l.DateCreneau, &l.HeureDebut, &l.HeureFin, &l.Lieu)
		if err != nil {
			return nil, fmt.Errorf("ListPlanning (scan) : %w", err)
		}
		resultats = append(resultats, l)
	}
	return resultats, nil
}
