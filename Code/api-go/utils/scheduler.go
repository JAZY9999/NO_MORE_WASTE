package utils

import (
	"fmt"
	"time"

	"nomorewaste/db"
)

func DemarrerSchedulerRappels() {
	go func() {
		for {
			ExecuterJobRappels()
			ExecuterJobPlannings()
			time.Sleep(24 * time.Hour)
		}
	}()
}

func ExecuterJobRappels() {
	envoyerRappelsRenouvellement(30, "j30")
	envoyerRappelsRenouvellement(7, "j7")
	envoyerRelancesExAbonnes(180, "ex_abonne")
}

func envoyerRappelsRenouvellement(joursAvant int, typeRappel string) {
	adhesions, err := db.ListAdhesionsARenouveler(joursAvant)
	if err != nil {
		fmt.Println("Erreur ExecuterJobRappels (renouvellement) :", err.Error())
		return
	}

	for _, a := range adhesions {
		if a.Email == nil {
			continue
		}

		dejaEnvoye, err := db.RappelDejaEnvoye(a.AdhesionId, typeRappel)
		if err != nil {
			fmt.Println("Erreur RappelDejaEnvoye :", err.Error())
			continue
		}
		if dejaEnvoye {
			continue
		}

		sujet := "Votre adhesion NO MORE WASTE arrive a echeance"
		corps := fmt.Sprintf(
			"Bonjour %s,\n\nVotre adhesion se termine le %s (dans %d jours).\nPensez a la renouveler aupres de votre contact NO MORE WASTE.\n\nMerci de votre confiance.",
			a.RaisonSociale, a.DateFin, a.JoursRestants,
		)

		err = EnvoyerEmail(*a.Email, sujet, corps)
		if err != nil {
			fmt.Println("Erreur EnvoyerEmail (rappel renouvellement) :", err.Error())
			continue
		}

		err = db.EnregistrerRappelEnvoye(a.AdhesionId, typeRappel, *a.Email)
		if err != nil {
			fmt.Println("Erreur EnregistrerRappelEnvoye :", err.Error())
		}
	}
}

func envoyerRelancesExAbonnes(joursDepuisExpiration int, typeRappel string) {
	adhesions, err := db.ListExAbonnesDepuis(joursDepuisExpiration)
	if err != nil {
		fmt.Println("Erreur ExecuterJobRappels (ex-abonnes) :", err.Error())
		return
	}

	for _, a := range adhesions {
		if a.Email == nil {
			continue
		}

		dejaEnvoye, err := db.RappelDejaEnvoye(a.AdhesionId, typeRappel)
		if err != nil {
			fmt.Println("Erreur RappelDejaEnvoye :", err.Error())
			continue
		}
		if dejaEnvoye {
			continue
		}

		sujet := "Ca fait longtemps ! NO MORE WASTE vous manque ?"
		corps := fmt.Sprintf(
			"Bonjour %s,\n\nVotre adhesion NO MORE WASTE s'est terminee le %s.\nNous serions ravis de vous compter de nouveau parmi nos adherents.\n\nA bientot !",
			a.RaisonSociale, a.DateFin,
		)

		err = EnvoyerEmail(*a.Email, sujet, corps)
		if err != nil {
			fmt.Println("Erreur EnvoyerEmail (relance ex-abonne) :", err.Error())
			continue
		}

		err = db.EnregistrerRappelEnvoye(a.AdhesionId, typeRappel, *a.Email)
		if err != nil {
			fmt.Println("Erreur EnregistrerRappelEnvoye :", err.Error())
		}
	}
}
