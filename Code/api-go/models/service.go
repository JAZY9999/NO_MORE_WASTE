package models

type Service struct {
	Id                  int     `json:"id"`
	Nom                 string  `json:"nom"`
	Description         *string `json:"description"`
	CompetenceRequiseId *int    `json:"competence_requise_id"`
	Type                string  `json:"type"`
	Actif               bool    `json:"actif"`
}

type CreneauService struct {
	Id          int     `json:"id"`
	ServiceId   int     `json:"service_id"`
	BenevoleId  *int    `json:"benevole_id"`
	DateCreneau string  `json:"date_creneau"`
	HeureDebut  string  `json:"heure_debut"`
	HeureFin    string  `json:"heure_fin"`
	Lieu        *string `json:"lieu"`
	CapaciteMax int     `json:"capacite_max"`
	Statut      string  `json:"statut"`
}

type InscriptionService struct {
	Id              int    `json:"id"`
	CreneauId       int    `json:"creneau_id"`
	CommercantId    *int   `json:"commercant_id"`
	UtilisateurId   *int   `json:"utilisateur_id"`
	DateInscription string `json:"date_inscription"`
	Statut          string `json:"statut"`
}

type LignePlanning struct {
	BenevoleId  int     `json:"benevole_id"`
	Nom         string  `json:"nom"`
	Prenom      string  `json:"prenom"`
	Email       *string `json:"email"`
	ServiceNom  string  `json:"service_nom"`
	DateCreneau string  `json:"date_creneau"`
	HeureDebut  string  `json:"heure_debut"`
	HeureFin    string  `json:"heure_fin"`
	Lieu        *string `json:"lieu"`
}
