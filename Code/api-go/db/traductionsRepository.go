package db

import (
	"database/sql"
	"fmt"

	"nomorewaste/models"
)

// ---------------------------------------------------------------------------
//  LANGUES
// ---------------------------------------------------------------------------

func ListLangues() ([]models.Langue, error) {
	rows, err := Conn.Query("SELECT code, libelle FROM langues ORDER BY code")
	if err != nil {
		return nil, fmt.Errorf("ListLangues : %w", err)
	}
	defer rows.Close()

	// On initialise la slice a vide plutot que de la laisser a nil : une slice
	// nil est encodee "null" en JSON, ce qui oblige le front a gerer ce cas.
	// Avec make, une liste vide reste "[]".
	langues := make([]models.Langue, 0)

	for rows.Next() {
		var l models.Langue
		if err := rows.Scan(&l.Code, &l.Libelle); err != nil {
			return nil, fmt.Errorf("ListLangues (scan) : %w", err)
		}
		langues = append(langues, l)
	}

	return langues, nil
}

func GetLangueByCode(code string) (*models.Langue, error) {
	var l models.Langue
	err := Conn.QueryRow(
		"SELECT code, libelle FROM langues WHERE code = $1", code,
	).Scan(&l.Code, &l.Libelle)

	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetLangueByCode (code=%v) : %w", code, err)
	}

	return &l, nil
}

func CreateLangue(code string, libelle string) error {
	_, err := Conn.Exec(
		"INSERT INTO langues (code, libelle) VALUES ($1, $2)", code, libelle)
	if err != nil {
		return fmt.Errorf("CreateLangue : %w", err)
	}
	return nil
}

// DeleteLangue supprime une langue ET toutes ses traductions.
//
// La suppression en cascade est declaree dans le schema
// (REFERENCES langues(code) ON DELETE CASCADE) : PostgreSQL s'en charge, on
// n'a pas a supprimer les traductions nous-memes.
func DeleteLangue(code string) error {
	_, err := Conn.Exec("DELETE FROM langues WHERE code = $1", code)
	if err != nil {
		return fmt.Errorf("DeleteLangue (code=%v) : %w", code, err)
	}
	return nil
}

// ---------------------------------------------------------------------------
//  TRADUCTIONS
// ---------------------------------------------------------------------------

// ListTraductions retourne les traductions, filtrees par langue si un code est
// fourni (chaine vide = toutes les langues).
func ListTraductions(codeLangue string) ([]models.Traduction, error) {
	requete := "SELECT id, cle, valeur, code_langue FROM traductions"
	arguments := []interface{}{}

	// Filtre optionnel : la STRUCTURE de la requete change, mais la valeur
	// passe toujours par $1. C'est ce qui protege de l'injection SQL, meme
	// avec une requete construite dynamiquement.
	if codeLangue != "" {
		requete += " WHERE code_langue = $1"
		arguments = append(arguments, codeLangue)
	}
	requete += " ORDER BY cle, code_langue"

	rows, err := Conn.Query(requete, arguments...)
	if err != nil {
		return nil, fmt.Errorf("ListTraductions : %w", err)
	}
	defer rows.Close()

	traductions := make([]models.Traduction, 0)

	for rows.Next() {
		var t models.Traduction
		if err := rows.Scan(&t.Id, &t.Cle, &t.Valeur, &t.CodeLangue); err != nil {
			return nil, fmt.Errorf("ListTraductions (scan) : %w", err)
		}
		traductions = append(traductions, t)
	}

	return traductions, nil
}

func GetTraductionById(id int) (*models.Traduction, error) {
	var t models.Traduction
	err := Conn.QueryRow(
		"SELECT id, cle, valeur, code_langue FROM traductions WHERE id = $1", id,
	).Scan(&t.Id, &t.Cle, &t.Valeur, &t.CodeLangue)

	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetTraductionById (id=%v) : %w", id, err)
	}

	return &t, nil
}

func CreateTraduction(cle string, valeur string, codeLangue string) (int, error) {
	var id int
	err := Conn.QueryRow(
		"INSERT INTO traductions (cle, valeur, code_langue) VALUES ($1, $2, $3) RETURNING id",
		cle, valeur, codeLangue,
	).Scan(&id)

	if err != nil {
		return 0, fmt.Errorf("CreateTraduction : %w", err)
	}

	return id, nil
}

func UpdateTraduction(id int, cle string, valeur string, codeLangue string) error {
	_, err := Conn.Exec(
		"UPDATE traductions SET cle = $1, valeur = $2, code_langue = $3 WHERE id = $4",
		cle, valeur, codeLangue, id)
	if err != nil {
		return fmt.Errorf("UpdateTraduction (id=%v) : %w", id, err)
	}
	return nil
}

func DeleteTraduction(id int) error {
	_, err := Conn.Exec("DELETE FROM traductions WHERE id = $1", id)
	if err != nil {
		return fmt.Errorf("DeleteTraduction (id=%v) : %w", id, err)
	}
	return nil
}

// EnregistrerTraduction cree la traduction, ou met a jour sa valeur si le
// couple (cle, langue) existe deja.
//
// C'est ce qu'on appelle un "upsert" (update + insert). PostgreSQL le fait en
// une seule requete grace a ON CONFLICT, qui s'appuie sur la contrainte
// UNIQUE (cle, code_langue) du schema.
//
// Sans ca, l'import d'un fichier JSON obligerait a faire, pour chaque cle :
// un SELECT pour savoir si elle existe, puis un INSERT ou un UPDATE selon le
// resultat. Soit trois fois plus de requetes, et un risque d'erreur si deux
// imports tournent en meme temps.
func EnregistrerTraduction(cle string, valeur string, codeLangue string) error {
	_, err := Conn.Exec(`
		INSERT INTO traductions (cle, valeur, code_langue)
		VALUES ($1, $2, $3)
		ON CONFLICT (cle, code_langue)
		DO UPDATE SET valeur = EXCLUDED.valeur`,
		cle, valeur, codeLangue)

	if err != nil {
		return fmt.Errorf("EnregistrerTraduction (cle=%v, langue=%v) : %w", cle, codeLangue, err)
	}

	return nil
}

// CompterTraductions sert de garde-fou avant de regenerer les fichiers JSON :
// si la base est vide, on refuse la synchronisation plutot que d'ecraser les
// fichiers existants par du vide.
func CompterTraductions() (int, error) {
	var total int
	err := Conn.QueryRow("SELECT COUNT(*) FROM traductions").Scan(&total)
	if err != nil {
		return 0, fmt.Errorf("CompterTraductions : %w", err)
	}
	return total, nil
}
