package db

import (
	"database/sql"
	"fmt"

	"nomorewaste/models"
)

// --- Beneficiaires ---

func CreateBeneficiaire(b models.Beneficiaire) (int, error) {
	var id int
	err := Conn.QueryRow(
		"INSERT INTO beneficiaires (type, nom, adresse, ville, telephone, contact) VALUES ($1, $2, $3, $4, $5, $6) RETURNING id",
		b.Type, b.Nom, b.Adresse, b.Ville, b.Telephone, b.Contact,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateBeneficiaire : %w", err)
	}
	return id, nil
}

func GetBeneficiaireById(id int) (*models.Beneficiaire, error) {
	var b models.Beneficiaire
	row := Conn.QueryRow(
		"SELECT id, type, nom, adresse, ville, telephone, contact FROM beneficiaires WHERE id = $1",
		id,
	)
	err := row.Scan(&b.Id, &b.Type, &b.Nom, &b.Adresse, &b.Ville, &b.Telephone, &b.Contact)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetBeneficiaireById (id=%v) : %w", id, err)
	}
	return &b, nil
}

func ListBeneficiaires(typeBeneficiaire *string) ([]models.Beneficiaire, error) {
	requete := "SELECT id, type, nom, adresse, ville, telephone, contact FROM beneficiaires WHERE 1=1"
	var arguments []interface{}
	if typeBeneficiaire != nil {
		requete += " AND type = $1"
		arguments = append(arguments, *typeBeneficiaire)
	}
	requete += " ORDER BY id"

	rows, err := Conn.Query(requete, arguments...)
	if err != nil {
		return nil, fmt.Errorf("ListBeneficiaires : %w", err)
	}
	defer rows.Close()

	var resultats []models.Beneficiaire
	for rows.Next() {
		var b models.Beneficiaire
		err := rows.Scan(&b.Id, &b.Type, &b.Nom, &b.Adresse, &b.Ville, &b.Telephone, &b.Contact)
		if err != nil {
			return nil, fmt.Errorf("ListBeneficiaires (scan) : %w", err)
		}
		resultats = append(resultats, b)
	}
	return resultats, nil
}

// --- Tournees ---

func CreateTournee(t models.Tournee) (int, error) {
	var id int
	err := Conn.QueryRow(
		"INSERT INTO tournees (date_tournee, benevole_id, statut) VALUES ($1, $2, $3) RETURNING id",
		t.DateTournee, t.BenevoleId, t.Statut,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateTournee : %w", err)
	}
	return id, nil
}

func GetTourneeById(id int) (*models.Tournee, error) {
	var t models.Tournee
	row := Conn.QueryRow(
		"SELECT id, date_tournee, benevole_id, statut FROM tournees WHERE id = $1",
		id,
	)
	err := row.Scan(&t.Id, &t.DateTournee, &t.BenevoleId, &t.Statut)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetTourneeById (id=%v) : %w", id, err)
	}
	return &t, nil
}

func ListTournees(statut *string) ([]models.Tournee, error) {
	requete := "SELECT id, date_tournee, benevole_id, statut FROM tournees WHERE 1=1"
	var arguments []interface{}
	if statut != nil {
		requete += " AND statut = $1"
		arguments = append(arguments, *statut)
	}
	requete += " ORDER BY date_tournee DESC, id"

	rows, err := Conn.Query(requete, arguments...)
	if err != nil {
		return nil, fmt.Errorf("ListTournees : %w", err)
	}
	defer rows.Close()

	var resultats []models.Tournee
	for rows.Next() {
		var t models.Tournee
		err := rows.Scan(&t.Id, &t.DateTournee, &t.BenevoleId, &t.Statut)
		if err != nil {
			return nil, fmt.Errorf("ListTournees (scan) : %w", err)
		}
		resultats = append(resultats, t)
	}
	return resultats, nil
}

func UpdateStatutTournee(id int, statut string, benevoleId *int) error {
	_, err := Conn.Exec(
		"UPDATE tournees SET statut = $1, benevole_id = $2 WHERE id = $3",
		statut, benevoleId, id,
	)
	if err != nil {
		return fmt.Errorf("UpdateStatutTournee (id=%v) : %w", id, err)
	}
	return nil
}

// --- Etapes de tournee ---

func CreateTourneeEtape(e models.TourneeEtape) (int, error) {
	var id int
	err := Conn.QueryRow(
		`INSERT INTO tournee_etapes (tournee_id, beneficiaire_id, ordre, heure_prevue, statut)
		 VALUES ($1, $2, $3, $4, $5) RETURNING id`,
		e.TourneeId, e.BeneficiaireId, e.Ordre, e.HeurePrevue, e.Statut,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateTourneeEtape : %w", err)
	}
	return id, nil
}

func GetTourneeEtapeById(id int) (*models.TourneeEtape, error) {
	var e models.TourneeEtape
	row := Conn.QueryRow(
		// to_char : voir l'explication dans ListEtapesParTournee.
		`SELECT id, tournee_id, beneficiaire_id, ordre,
		        to_char(heure_prevue, 'HH24:MI'), to_char(heure_reelle, 'HH24:MI'), statut
		 FROM tournee_etapes WHERE id = $1`,
		id,
	)
	err := row.Scan(&e.Id, &e.TourneeId, &e.BeneficiaireId, &e.Ordre, &e.HeurePrevue, &e.HeureReelle, &e.Statut)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetTourneeEtapeById (id=%v) : %w", id, err)
	}
	return &e, nil
}

func ListEtapesParTournee(tourneeId int) ([]models.TourneeEtape, error) {
	rows, err := Conn.Query(
		// LEFT JOIN et non JOIN : un arret pas encore cloture n'a aucune
		// livraison, et doit quand meme apparaitre dans la liste. Avec un
		// JOIN simple, seuls les arrets deja livres seraient retournes.
		//
		// POURQUOI to_char SUR LES HEURES
		//
		// heure_prevue est une colonne TIME. En la lisant directement dans un
		// *string, database/sql la recoit comme une date complete et la
		// formate en "0000-01-01T10:30:00Z" : une heure de passage affublee
		// d'une annee zero. Le front affichait "0000-" au lieu de "10:30".
		//
		// to_char demande a PostgreSQL de renvoyer directement le texte
		// voulu. L'API rend alors une heure sous la forme d'une heure, et
		// aucun client n'a besoin de savoir qu'il faut ignorer 11 caracteres.
		`SELECT te.id, te.tournee_id, te.beneficiaire_id, te.ordre,
		        to_char(te.heure_prevue, 'HH24:MI'),
		        to_char(te.heure_reelle, 'HH24:MI'),
		        te.statut, l.id
		 FROM tournee_etapes te
		 LEFT JOIN livraisons l ON l.tournee_etape_id = te.id
		 WHERE te.tournee_id = $1
		 ORDER BY te.ordre`,
		tourneeId,
	)
	if err != nil {
		return nil, fmt.Errorf("ListEtapesParTournee : %w", err)
	}
	defer rows.Close()

	var resultats []models.TourneeEtape
	for rows.Next() {
		var e models.TourneeEtape
		err := rows.Scan(&e.Id, &e.TourneeId, &e.BeneficiaireId, &e.Ordre,
			&e.HeurePrevue, &e.HeureReelle, &e.Statut, &e.LivraisonId)
		if err != nil {
			return nil, fmt.Errorf("ListEtapesParTournee (scan) : %w", err)
		}
		resultats = append(resultats, e)
	}
	return resultats, nil
}

func MarquerEtapeLivree(etapeId int) error {
	_, err := Conn.Exec(
		"UPDATE tournee_etapes SET statut = 'livre', heure_reelle = CURRENT_TIME WHERE id = $1",
		etapeId,
	)
	if err != nil {
		return fmt.Errorf("MarquerEtapeLivree (id=%v) : %w", etapeId, err)
	}
	return nil
}

// --- Livraisons ---

func CreateLivraison(etapeId int) (int, error) {
	var id int
	err := Conn.QueryRow(
		"INSERT INTO livraisons (tournee_etape_id) VALUES ($1) RETURNING id",
		etapeId,
	).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("CreateLivraison : %w", err)
	}
	return id, nil
}

func GetLivraisonById(id int) (*models.Livraison, error) {
	var l models.Livraison
	row := Conn.QueryRow(
		"SELECT id, tournee_etape_id, date_livraison, pdf_genere_path FROM livraisons WHERE id = $1",
		id,
	)
	err := row.Scan(&l.Id, &l.TourneeEtapeId, &l.DateLivraison, &l.PdfGenerePath)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetLivraisonById (id=%v) : %w", id, err)
	}
	return &l, nil
}

func GetLivraisonParEtape(etapeId int) (*models.Livraison, error) {
	var l models.Livraison
	row := Conn.QueryRow(
		"SELECT id, tournee_etape_id, date_livraison, pdf_genere_path FROM livraisons WHERE tournee_etape_id = $1",
		etapeId,
	)
	err := row.Scan(&l.Id, &l.TourneeEtapeId, &l.DateLivraison, &l.PdfGenerePath)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetLivraisonParEtape (etapeId=%v) : %w", etapeId, err)
	}
	return &l, nil
}

// AjouterProduitLivraison rattache un produit a une livraison ET marque ce
// produit comme "distribue" dans le stock : une fois donne a un beneficiaire,
// il ne fait plus partie du stock disponible.
func AjouterProduitLivraison(livraisonId int, produitId int, quantite int) error {
	_, err := Conn.Exec(
		"INSERT INTO livraison_produits (livraison_id, produit_id, quantite) VALUES ($1, $2, $3)",
		livraisonId, produitId, quantite,
	)
	if err != nil {
		return fmt.Errorf("AjouterProduitLivraison : %w", err)
	}

	_, err = Conn.Exec("UPDATE produits SET statut = 'distribue' WHERE id = $1", produitId)
	if err != nil {
		return fmt.Errorf("AjouterProduitLivraison (maj statut produit) : %w", err)
	}
	return nil
}

func EnregistrerCheminPdf(livraisonId int, chemin string) error {
	_, err := Conn.Exec("UPDATE livraisons SET pdf_genere_path = $1 WHERE id = $2", chemin, livraisonId)
	if err != nil {
		return fmt.Errorf("EnregistrerCheminPdf : %w", err)
	}
	return nil
}

// GetRecapLivraison rassemble, en deux requetes, toutes les informations
// necessaires au recapitulatif PDF : les infos de la livraison (avec le
// beneficiaire et la tournee, via trois JOIN), puis la liste des produits.
func GetRecapLivraison(livraisonId int) (*models.RecapLivraison, error) {
	var r models.RecapLivraison
	row := Conn.QueryRow(
		`SELECT l.id, l.date_livraison, t.id, t.date_tournee, b.nom, b.type, b.adresse, b.ville
		 FROM livraisons l
		 JOIN tournee_etapes te ON te.id = l.tournee_etape_id
		 JOIN tournees t ON t.id = te.tournee_id
		 JOIN beneficiaires b ON b.id = te.beneficiaire_id
		 WHERE l.id = $1`,
		livraisonId,
	)
	err := row.Scan(&r.LivraisonId, &r.DateLivraison, &r.TourneeId, &r.DateTournee,
		&r.BeneficiaireNom, &r.BeneficiaireType, &r.BeneficiaireAdresse, &r.BeneficiaireVille)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetRecapLivraison (id=%v) : %w", livraisonId, err)
	}

	rows, err := Conn.Query(
		`SELECT p.id, p.code_barre, p.libelle, lp.quantite
		 FROM livraison_produits lp
		 JOIN produits p ON p.id = lp.produit_id
		 WHERE lp.livraison_id = $1
		 ORDER BY p.id`,
		livraisonId,
	)
	if err != nil {
		return nil, fmt.Errorf("GetRecapLivraison (produits) : %w", err)
	}
	defer rows.Close()

	for rows.Next() {
		var p models.ProduitLivre
		err := rows.Scan(&p.ProduitId, &p.CodeBarre, &p.Libelle, &p.Quantite)
		if err != nil {
			return nil, fmt.Errorf("GetRecapLivraison (scan produit) : %w", err)
		}
		r.Produits = append(r.Produits, p)
	}

	return &r, nil
}
