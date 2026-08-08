package models

type Campagne struct {
	Id                                int     `json:"id"`
	Nom                               string  `json:"nom"`
	SujetEmail                        string  `json:"sujet_email"`
	CorpsEmail                        string  `json:"corps_email"`
	CritereVille                      *string `json:"critere_ville"`
	CriterePays                       *string `json:"critere_pays"`
	CritereStatutAdhesion             *string `json:"critere_statut_adhesion"`
	CritereAdhesionExpireeDepuisJours *int    `json:"critere_adhesion_expiree_depuis_jours"`
}

type DestinataireCampagne struct {
	CommercantId  int     `json:"commercant_id"`
	RaisonSociale string  `json:"raison_sociale"`
	Email         *string `json:"email"`
}
