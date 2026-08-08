package utils

import (
	"fmt"
	"time"

	"nomorewaste/db"
	"nomorewaste/models"
)

// ExecuterJobPlannings genere et envoie, pour la date du jour, le planning de
// chaque benevole qui a au moins un creneau affecte. Chaque benevole recoit un
// seul email, contenant TOUS ses creneaux de la journee en piece jointe CSV.
func ExecuterJobPlannings() {
	dateDuJour := time.Now().Format("2006-01-02")
	EnvoyerPlanningsPourDate(dateDuJour)
}

func EnvoyerPlanningsPourDate(date string) {
	lignes, err := db.ListPlanningDuJour(date)
	if err != nil {
		fmt.Println("Erreur ExecuterJobPlannings :", err.Error())
		return
	}

	planningParBenevole := make(map[int][]models.LignePlanning)
	for _, l := range lignes {
		planningParBenevole[l.BenevoleId] = append(planningParBenevole[l.BenevoleId], l)
	}

	for _, lignesBenevole := range planningParBenevole {
		premiereLigne := lignesBenevole[0]

		if premiereLigne.Email == nil {
			continue
		}

		contenuCSV, err := GenererPlanningCSV(lignesBenevole)
		if err != nil {
			fmt.Println("Erreur GenererPlanningCSV :", err.Error())
			continue
		}

		sujet := "Votre planning NO MORE WASTE du " + date
		corps := fmt.Sprintf(
			"Bonjour %s %s,\n\nVoici votre planning du %s en piece jointe (%d creneau(x)).\n\nMerci pour votre engagement !",
			premiereLigne.Prenom, premiereLigne.Nom, date, len(lignesBenevole),
		)
		nomFichier := "planning-" + date + ".csv"

		err = EnvoyerEmailAvecPieceJointe(*premiereLigne.Email, sujet, corps, nomFichier, contenuCSV)
		if err != nil {
			fmt.Println("Erreur EnvoyerEmailAvecPieceJointe (planning) :", err.Error())
			continue
		}
	}
}
