package models

type Produit struct {
	Id            int      `json:"id"`
	CodeBarre     string   `json:"code_barre"`
	Libelle       string   `json:"libelle"`
	Categorie     *string  `json:"categorie"`
	Dlc           *string  `json:"dlc"`
	CollecteId    *int     `json:"collecte_id"`
	PoidsKg       *float64 `json:"poids_kg"`
	Quantite      int      `json:"quantite"`
	EmplacementId *int     `json:"emplacement_id"`
	Statut        string   `json:"statut"`
}
