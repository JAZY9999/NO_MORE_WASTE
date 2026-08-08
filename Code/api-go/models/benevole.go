package models

type Benevole struct {
	Id              int     `json:"id"`
	UtilisateurId   *int    `json:"utilisateur_id"`
	Nom             string  `json:"nom"`
	Prenom          string  `json:"prenom"`
	Email           *string `json:"email"`
	Telephone       *string `json:"telephone"`
	Adresse         *string `json:"adresse"`
	Statut          string  `json:"statut"`
	PermisConduire  bool    `json:"permis_conduire"`
	DateCandidature string  `json:"date_candidature"`
	DateValidation  *string `json:"date_validation"`
}

type Competence struct {
	Id      int    `json:"id"`
	Libelle string `json:"libelle"`
}

type BenevoleDocument struct {
	Id            int     `json:"id"`
	BenevoleId    int     `json:"benevole_id"`
	TypeDocument  string  `json:"type_document"`
	CheminFichier *string `json:"chemin_fichier"`
	Valide        bool    `json:"valide"`
}
