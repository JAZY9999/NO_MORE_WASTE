package db

import (
	"database/sql"
	"fmt"

	"nomorewaste/models"
)

func CreateAdhesion(a models.Adhesion) (int, error) {
	var id int
	err := Conn.QueryRow(
		"INSERT INTO adhesions (commercant_id, date_debut, date_fin, statut, montant_cotisation) VALUES ($1, $2, $3, $4, $5) RETURNING id",
		a.CommercantId, a.DateDebut, a.DateFin, a.Statut, a.MontantCotisation,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateAdhesion : %w", err)
	}
	return id, nil
}

func GetAdhesionById(id int) (*models.Adhesion, error) {
	var a models.Adhesion
	row := Conn.QueryRow(
		"SELECT id, commercant_id, date_debut, date_fin, statut, montant_cotisation FROM adhesions WHERE id = $1",
		id,
	)
	err := row.Scan(&a.Id, &a.CommercantId, &a.DateDebut, &a.DateFin, &a.Statut, &a.MontantCotisation)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetAdhesionById (id=%v) : %w", id, err)
	}
	return &a, nil
}

func UpdateAdhesion(id int, a models.Adhesion) error {
	_, err := Conn.Exec(
		"UPDATE adhesions SET date_debut = $1, date_fin = $2, statut = $3, montant_cotisation = $4 WHERE id = $5",
		a.DateDebut, a.DateFin, a.Statut, a.MontantCotisation, id,
	)
	if err != nil {
		return fmt.Errorf("UpdateAdhesion (id=%v) : %w", id, err)
	}
	return nil
}

// ListAdhesionsByCommercant retourne l'historique des adhesions d'un
// commercant, de la plus recente a la plus ancienne.
//
// Le projet conserve une LIGNE PAR ADHESION plutot que de modifier la meme a
// chaque renouvellement : on garde ainsi la trace des annees precedentes, et
// l'espace client peut afficher "adherent depuis 2024".
//
// La plus recente en premier, car c'est celle qui interesse : c'est elle qui
// dit si le commercant est a jour.
func ListAdhesionsByCommercant(commercantId int) ([]models.Adhesion, error) {
	rows, err := Conn.Query(
		`SELECT id, commercant_id, date_debut, date_fin, statut, montant_cotisation
		 FROM adhesions
		 WHERE commercant_id = $1
		 ORDER BY date_fin DESC`,
		commercantId,
	)
	if err != nil {
		return nil, fmt.Errorf("ListAdhesionsByCommercant (commercant=%v) : %w", commercantId, err)
	}
	defer rows.Close()

	adhesions := make([]models.Adhesion, 0)
	for rows.Next() {
		var a models.Adhesion
		err := rows.Scan(&a.Id, &a.CommercantId, &a.DateDebut, &a.DateFin, &a.Statut, &a.MontantCotisation)
		if err != nil {
			return nil, fmt.Errorf("ListAdhesionsByCommercant (scan) : %w", err)
		}
		adhesions = append(adhesions, a)
	}
	return adhesions, nil
}

// ListAdhesions liste TOUTES les adhesions, avec le nom de leur commercant.
//
// Elle manquait. Consequence : le back-office ne pouvait pas voir les
// adhesions qu'il est cense gerer -- seulement celles qui arrivent a echeance
// a J-30 et J-7 exactement. Impossible de savoir combien sont actives, ni
// lesquelles ont expire. Trouve en portant l'ecran des adhesions.
//
// Le filtre par statut est facultatif : la meme fonction sert a la liste
// complete et aux onglets de l'ecran.
func ListAdhesions(statut *string) ([]models.AdhesionDetaillee, error) {
	requete := `
		SELECT a.id, a.commercant_id, c.raison_sociale, c.email,
		       a.date_debut, a.date_fin, a.statut, a.montant_cotisation,
		       (a.date_fin - CURRENT_DATE) AS jours_restants
		FROM adhesions a
		JOIN commercants c ON c.id = a.commercant_id`

	// La STRUCTURE de la requete change, mais la VALEUR passe toujours par
	// $1 : c'est ce qui protege de l'injection SQL.
	var arguments []interface{}
	if statut != nil {
		arguments = append(arguments, *statut)
		requete += " WHERE a.statut = $1"
	}

	// La plus proche echeance en premier : c'est celle sur laquelle il faut
	// agir. Un tri par identifiant n'aurait aucun sens metier ici.
	requete += " ORDER BY a.date_fin"

	rows, err := Conn.Query(requete, arguments...)
	if err != nil {
		return nil, fmt.Errorf("ListAdhesions : %w", err)
	}
	defer rows.Close()

	// make(...) et non var : Go encoderait une slice nil en "null", et le
	// front devrait le rattraper. Une liste vide est "[]".
	resultats := make([]models.AdhesionDetaillee, 0)
	for rows.Next() {
		var a models.AdhesionDetaillee
		err := rows.Scan(&a.Id, &a.CommercantId, &a.RaisonSociale, &a.Email,
			&a.DateDebut, &a.DateFin, &a.Statut, &a.MontantCotisation, &a.JoursRestants)
		if err != nil {
			return nil, fmt.Errorf("ListAdhesions (scan) : %w", err)
		}
		resultats = append(resultats, a)
	}
	return resultats, nil
}
