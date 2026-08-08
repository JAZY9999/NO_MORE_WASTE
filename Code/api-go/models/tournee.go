package models

type Beneficiaire struct {
	Id        int     `json:"id"`
	Type      string  `json:"type"`
	Nom       string  `json:"nom"`
	Adresse   *string `json:"adresse"`
	Ville     *string `json:"ville"`
	Telephone *string `json:"telephone"`
	Contact   *string `json:"contact"`
}

type Tournee struct {
	Id          int    `json:"id"`
	DateTournee string `json:"date_tournee"`
	BenevoleId  *int   `json:"benevole_id"`
	Statut      string `json:"statut"`
}

type TourneeEtape struct {
	Id             int `json:"id"`
	TourneeId      int `json:"tournee_id"`
	BeneficiaireId int `json:"beneficiaire_id"`
	Ordre          int `json:"ordre"`
	// Heures de passage au format "HH:MM" (ex. "10:30"), ou nil.
	// Le formatage est fait par PostgreSQL : voir ListEtapesParTournee.
	HeurePrevue *string `json:"heure_prevue"`
	HeureReelle *string `json:"heure_reelle"`

	Statut string `json:"statut"`

	// LivraisonId : l'identifiant de la livraison rattachee a cet arret, ou
	// nil tant qu'il n'a pas ete cloture.
	//
	// Sans ce champ, un client qui liste les etapes d'une tournee sait qu'un
	// arret est "livre", mais n'a aucun moyen de retrouver SA livraison --
	// donc aucun moyen de construire le lien vers le recapitulatif PDF, alors
	// que ce PDF est exige par le sujet. Le manque a ete decouvert en portant
	// l'ecran des tournees.
	LivraisonId *int `json:"livraison_id"`
}

type Livraison struct {
	Id             int     `json:"id"`
	TourneeEtapeId int     `json:"tournee_etape_id"`
	DateLivraison  string  `json:"date_livraison"`
	PdfGenerePath  *string `json:"pdf_genere_path"`
}

type ProduitLivre struct {
	ProduitId int    `json:"produit_id"`
	CodeBarre string `json:"code_barre"`
	Libelle   string `json:"libelle"`
	Quantite  int    `json:"quantite"`
}

// RecapLivraison rassemble tout ce qu'il faut imprimer sur le recapitulatif
// PDF d'une livraison : qui a recu, quand, dans quelle tournee, et quoi.
type RecapLivraison struct {
	LivraisonId         int            `json:"livraison_id"`
	DateLivraison       string         `json:"date_livraison"`
	TourneeId           int            `json:"tournee_id"`
	DateTournee         string         `json:"date_tournee"`
	BeneficiaireNom     string         `json:"beneficiaire_nom"`
	BeneficiaireType    string         `json:"beneficiaire_type"`
	BeneficiaireAdresse *string        `json:"beneficiaire_adresse"`
	BeneficiaireVille   *string        `json:"beneficiaire_ville"`
	Produits            []ProduitLivre `json:"produits"`
}
