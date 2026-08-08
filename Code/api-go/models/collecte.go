package models

type Collecte struct {
	Id                 int     `json:"id"`
	CommercantId       *int    `json:"commercant_id"`
	ParticulierNom     *string `json:"particulier_nom"`
	ParticulierAdresse *string `json:"particulier_adresse"`
	BenevoleId         *int    `json:"benevole_id"`
	DatePrevue         *string `json:"date_prevue"`
	DateRealisee       *string `json:"date_realisee"`
	Statut             string  `json:"statut"`
}
