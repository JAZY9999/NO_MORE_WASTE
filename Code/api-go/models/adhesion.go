package models

type Adhesion struct {
	Id                int     `json:"id"`
	CommercantId      int     `json:"commercant_id"`
	DateDebut         string  `json:"date_debut"`
	DateFin           string  `json:"date_fin"`
	Statut            string  `json:"statut"`
	MontantCotisation *string `json:"montant_cotisation"`
}

// AdhesionDetaillee est une adhesion accompagnee du nom de son commercant.
//
// # POURQUOI UN TYPE A PART
//
// L'ecran des adhesions affiche une ligne par adhesion, avec le nom de la
// boutique. Le modele Adhesion ne porte qu'un commercant_id : le front devrait
// donc appeler /commercants/{id} pour CHAQUE ligne du tableau -- dix lignes,
// onze requetes.
//
// La jointure est faite une fois cote SQL, la ou elle coute le moins cher.
// C'est le meme choix que pour AdhesionARenouveler, qui porte deja le nom.
type AdhesionDetaillee struct {
	Id                int     `json:"id"`
	CommercantId      int     `json:"commercant_id"`
	RaisonSociale     string  `json:"raison_sociale"`
	Email             *string `json:"email"`
	DateDebut         string  `json:"date_debut"`
	DateFin           string  `json:"date_fin"`
	Statut            string  `json:"statut"`
	MontantCotisation *string `json:"montant_cotisation"`

	// JoursRestants : negatif si l'echeance est passee. Calcule par
	// PostgreSQL, qui connait la date du jour du SERVEUR -- celle du
	// navigateur pourrait etre fausse ou dans un autre fuseau.
	JoursRestants int `json:"jours_restants"`
}

type AdhesionARenouveler struct {
	AdhesionId    int     `json:"adhesion_id"`
	CommercantId  int     `json:"commercant_id"`
	RaisonSociale string  `json:"raison_sociale"`
	Email         *string `json:"email"`
	DateFin       string  `json:"date_fin"`
	JoursRestants int     `json:"jours_restants"`
}

type RappelHistorique struct {
	Id                int    `json:"id"`
	AdhesionId        int    `json:"adhesion_id"`
	TypeRappel        string `json:"type_rappel"`
	DateEnvoi         string `json:"date_envoi"`
	EmailDestinataire string `json:"email_destinataire"`
}
