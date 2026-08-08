package utils

import (
	"bytes"
	"encoding/csv"
	"fmt"
	"strings"
	"time"

	"nomorewaste/models"
)

// formaterDate transforme une date renvoyee par Postgres ("2026-07-31T00:00:00Z")
// en date lisible pour un humain ("31/07/2026").
func formaterDate(valeur string) string {
	date, err := time.Parse(time.RFC3339, valeur)
	if err != nil {
		return valeur
	}
	return date.Format("02/01/2006")
}

// formaterDateHeure transforme un horodatage Postgres
// ("2026-07-31T14:30:00Z") en date et heure lisibles ("31/07/2026a 14:30").
func formaterDateHeure(valeur string) string {
	horodatage, err := time.Parse(time.RFC3339, valeur)
	if err != nil {
		return valeur
	}
	return horodatage.Format("02/01/2006 a 15:04")
}

// formaterHeure transforme une heure renvoyee par Postgres
// ("0000-01-01T14:00:00Z") en heure lisible ("14:00").
func formaterHeure(valeur string) string {
	heure, err := time.Parse(time.RFC3339, valeur)
	if err != nil {
		if strings.Contains(valeur, ":") && len(valeur) >= 5 {
			return valeur[:5]
		}
		return valeur
	}
	return heure.Format("15:04")
}

// GenererPlanningCSV construit le contenu d'un fichier CSV a partir des creneaux
// d'un benevole. Le CSV est genere avec le package standard encoding/csv, sans
// aucune librairie externe : Excel ouvre nativement ce format.
func GenererPlanningCSV(lignes []models.LignePlanning) ([]byte, error) {
	var tampon bytes.Buffer

	tampon.WriteString("\xEF\xBB\xBF")

	ecrivain := csv.NewWriter(&tampon)
	ecrivain.Comma = ';'

	err := ecrivain.Write([]string{"Date", "Heure debut", "Heure fin", "Service", "Lieu"})
	if err != nil {
		return nil, fmt.Errorf("GenererPlanningCSV (entete) : %s", err.Error())
	}

	for _, l := range lignes {
		lieu := ""
		if l.Lieu != nil {
			lieu = *l.Lieu
		}
		err := ecrivain.Write([]string{
			formaterDate(l.DateCreneau),
			formaterHeure(l.HeureDebut),
			formaterHeure(l.HeureFin),
			l.ServiceNom,
			lieu,
		})
		if err != nil {
			return nil, fmt.Errorf("GenererPlanningCSV (ligne) : %s", err.Error())
		}
	}

	ecrivain.Flush()
	err = ecrivain.Error()
	if err != nil {
		return nil, fmt.Errorf("GenererPlanningCSV (flush) : %s", err.Error())
	}

	return tampon.Bytes(), nil
}
