package models

type Commercant struct {
	Id            int     `json:"id"`
	RaisonSociale string  `json:"raison_sociale"`
	Siret         *string `json:"siret"`
	Adresse       *string `json:"adresse"`
	Ville         *string `json:"ville"`
	Pays          *string `json:"pays"`
	Email         *string `json:"email"`
	Telephone     *string `json:"telephone"`
	ContactNom    *string `json:"contact_nom"`

	// UtilisateurId relie le commercant a un compte de connexion.
	//
	// C'est ce lien qui permet l'espace client : quand un commercant se
	// connecte, on retrouve SA fiche a partir de son compte, sans jamais lui
	// demander son identifiant. Un pointeur car le lien est facultatif : le
	// personnel peut enregistrer un commercant avant que celui-ci ait un compte.
	UtilisateurId *int `json:"utilisateur_id"`
}
