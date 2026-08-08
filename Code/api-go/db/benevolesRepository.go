package db

import (
	"database/sql"
	"fmt"

	"nomorewaste/models"
)

func CreateBenevole(b models.Benevole) (int, error) {
	var id int
	err := Conn.QueryRow(
		// utilisateur_id : le compte qui pourra consulter l'espace benevole.
		//
		// ATTENTION : la valeur ne doit JAMAIS venir du corps de la requete.
		// PoserCandidature est une route publique ; accepter un identifiant
		// envoye par le client permettrait a n'importe qui de rattacher une
		// fiche benevole au compte d'autrui. Elle est deduite du jeton, ou
		// laissee nulle pour une candidature anonyme. Voir PoserCandidature.
		`INSERT INTO benevoles (nom, prenom, email, telephone, adresse, statut, permis_conduire, utilisateur_id)
		 VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING id`,
		b.Nom, b.Prenom, b.Email, b.Telephone, b.Adresse, b.Statut, b.PermisConduire,
		b.UtilisateurId,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateBenevole : %w", err)
	}
	return id, nil
}

func GetBenevoleById(id int) (*models.Benevole, error) {
	var b models.Benevole
	row := Conn.QueryRow(
		`SELECT id, utilisateur_id, nom, prenom, email, telephone, adresse, statut, permis_conduire, date_candidature, date_validation
		 FROM benevoles WHERE id = $1`,
		id,
	)
	err := row.Scan(&b.Id, &b.UtilisateurId, &b.Nom, &b.Prenom, &b.Email, &b.Telephone, &b.Adresse, &b.Statut, &b.PermisConduire, &b.DateCandidature, &b.DateValidation)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetBenevoleById (id=%v) : %w", id, err)
	}
	return &b, nil
}

func ListBenevoles(statut *string) ([]models.Benevole, error) {
	requete := `SELECT id, utilisateur_id, nom, prenom, email, telephone, adresse, statut, permis_conduire, date_candidature, date_validation
	            FROM benevoles WHERE 1=1`
	var arguments []interface{}
	if statut != nil {
		requete += " AND statut = $1"
		arguments = append(arguments, *statut)
	}
	requete += " ORDER BY id"

	rows, err := Conn.Query(requete, arguments...)
	if err != nil {
		return nil, fmt.Errorf("ListBenevoles : %w", err)
	}
	defer rows.Close()

	var resultats []models.Benevole
	for rows.Next() {
		var b models.Benevole
		err := rows.Scan(&b.Id, &b.UtilisateurId, &b.Nom, &b.Prenom, &b.Email, &b.Telephone, &b.Adresse, &b.Statut, &b.PermisConduire, &b.DateCandidature, &b.DateValidation)
		if err != nil {
			return nil, fmt.Errorf("ListBenevoles (scan) : %w", err)
		}
		resultats = append(resultats, b)
	}
	return resultats, nil
}

func UpdateStatutBenevole(id int, statut string) error {
	var err error
	if statut == "valide" {
		_, err = Conn.Exec(
			"UPDATE benevoles SET statut = $1, date_validation = CURRENT_DATE WHERE id = $2",
			statut, id,
		)
	} else {
		_, err = Conn.Exec(
			"UPDATE benevoles SET statut = $1 WHERE id = $2",
			statut, id,
		)
	}
	if err != nil {
		return fmt.Errorf("UpdateStatutBenevole (id=%v) : %w", id, err)
	}
	return nil
}

// --- Documents (les "conditions a valider" citees par le sujet) ---

func CreateBenevoleDocument(d models.BenevoleDocument) (int, error) {
	var id int
	err := Conn.QueryRow(
		"INSERT INTO benevole_documents (benevole_id, type_document, chemin_fichier, valide) VALUES ($1, $2, $3, $4) RETURNING id",
		d.BenevoleId, d.TypeDocument, d.CheminFichier, d.Valide,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateBenevoleDocument : %w", err)
	}
	return id, nil
}

func ListDocumentsBenevole(benevoleId int) ([]models.BenevoleDocument, error) {
	rows, err := Conn.Query(
		"SELECT id, benevole_id, type_document, chemin_fichier, valide FROM benevole_documents WHERE benevole_id = $1 ORDER BY id",
		benevoleId,
	)
	if err != nil {
		return nil, fmt.Errorf("ListDocumentsBenevole : %w", err)
	}
	defer rows.Close()

	var resultats []models.BenevoleDocument
	for rows.Next() {
		var d models.BenevoleDocument
		err := rows.Scan(&d.Id, &d.BenevoleId, &d.TypeDocument, &d.CheminFichier, &d.Valide)
		if err != nil {
			return nil, fmt.Errorf("ListDocumentsBenevole (scan) : %w", err)
		}
		resultats = append(resultats, d)
	}
	return resultats, nil
}

func ValiderDocument(documentId int) error {
	_, err := Conn.Exec("UPDATE benevole_documents SET valide = true WHERE id = $1", documentId)
	if err != nil {
		return fmt.Errorf("ValiderDocument (id=%v) : %w", documentId, err)
	}
	return nil
}

// TousLesDocumentsSontValides verifie que le benevole a AU MOINS un document
// enregistre, ET qu'aucun d'entre eux n'est en attente de validation.
// C'est la condition qui doit etre vraie avant de pouvoir passer un benevole
// au statut "valide" (voir app/benevoles.go, ValiderBenevole).
func TousLesDocumentsSontValides(benevoleId int) (bool, error) {
	var nombreTotal, nombreValides int
	err := Conn.QueryRow(
		"SELECT COUNT(*), COUNT(*) FILTER (WHERE valide = true) FROM benevole_documents WHERE benevole_id = $1",
		benevoleId,
	).Scan(&nombreTotal, &nombreValides)
	if err != nil {
		return false, fmt.Errorf("TousLesDocumentsSontValides : %w", err)
	}
	return nombreTotal > 0 && nombreTotal == nombreValides, nil
}

// --- Competences ---

func ListCompetences() ([]models.Competence, error) {
	rows, err := Conn.Query("SELECT id, libelle FROM competences ORDER BY libelle")
	if err != nil {
		return nil, fmt.Errorf("ListCompetences : %w", err)
	}
	defer rows.Close()

	var resultats []models.Competence
	for rows.Next() {
		var c models.Competence
		err := rows.Scan(&c.Id, &c.Libelle)
		if err != nil {
			return nil, fmt.Errorf("ListCompetences (scan) : %w", err)
		}
		resultats = append(resultats, c)
	}
	return resultats, nil
}

func GetCompetenceById(id int) (*models.Competence, error) {
	var c models.Competence
	row := Conn.QueryRow("SELECT id, libelle FROM competences WHERE id = $1", id)
	err := row.Scan(&c.Id, &c.Libelle)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetCompetenceById (id=%v) : %w", id, err)
	}
	return &c, nil
}

func ListCompetencesBenevole(benevoleId int) ([]models.Competence, error) {
	rows, err := Conn.Query(
		`SELECT c.id, c.libelle
		 FROM competences c
		 JOIN benevole_competences bc ON bc.competence_id = c.id
		 WHERE bc.benevole_id = $1
		 ORDER BY c.libelle`,
		benevoleId,
	)
	if err != nil {
		return nil, fmt.Errorf("ListCompetencesBenevole : %w", err)
	}
	defer rows.Close()

	var resultats []models.Competence
	for rows.Next() {
		var c models.Competence
		err := rows.Scan(&c.Id, &c.Libelle)
		if err != nil {
			return nil, fmt.Errorf("ListCompetencesBenevole (scan) : %w", err)
		}
		resultats = append(resultats, c)
	}
	return resultats, nil
}

func AjouterCompetenceBenevole(benevoleId int, competenceId int) error {
	_, err := Conn.Exec(
		"INSERT INTO benevole_competences (benevole_id, competence_id) VALUES ($1, $2)",
		benevoleId, competenceId,
	)
	if err != nil {
		return fmt.Errorf("AjouterCompetenceBenevole : %w", err)
	}
	return nil
}

func RetirerCompetenceBenevole(benevoleId int, competenceId int) error {
	_, err := Conn.Exec(
		"DELETE FROM benevole_competences WHERE benevole_id = $1 AND competence_id = $2",
		benevoleId, competenceId,
	)
	if err != nil {
		return fmt.Errorf("RetirerCompetenceBenevole : %w", err)
	}
	return nil
}

func BenevoleADejaCompetence(benevoleId int, competenceId int) (bool, error) {
	var count int
	err := Conn.QueryRow(
		"SELECT COUNT(*) FROM benevole_competences WHERE benevole_id = $1 AND competence_id = $2",
		benevoleId, competenceId,
	).Scan(&count)
	if err != nil {
		return false, fmt.Errorf("BenevoleADejaCompetence : %w", err)
	}
	return count > 0, nil
}

// GetBenevoleByUtilisateurId retrouve le benevole rattache a un compte.
//
// Meme principe que pour les commercants : on part du compte connecte pour
// retrouver SA fiche, sans jamais faire confiance a un identifiant envoye par
// le client.
//
// Retourne (nil, nil) si le compte n'est rattache a aucun benevole.
func GetBenevoleByUtilisateurId(utilisateurId int) (*models.Benevole, error) {
	var b models.Benevole
	err := Conn.QueryRow(
		`SELECT id, utilisateur_id, nom, prenom, email, telephone, adresse, statut,
		        permis_conduire, date_candidature, date_validation
		 FROM benevoles WHERE utilisateur_id = $1`,
		utilisateurId,
	).Scan(&b.Id, &b.UtilisateurId, &b.Nom, &b.Prenom, &b.Email, &b.Telephone,
		&b.Adresse, &b.Statut, &b.PermisConduire, &b.DateCandidature, &b.DateValidation)

	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetBenevoleByUtilisateurId (utilisateur=%v) : %w", utilisateurId, err)
	}
	return &b, nil
}
