package models

type EmplacementStock struct {
	Id       int     `json:"id"`
	Entrepot string  `json:"entrepot"`
	Zone     *string `json:"zone"`
	Rayon    *string `json:"rayon"`
	Etagere  *string `json:"etagere"`
}
