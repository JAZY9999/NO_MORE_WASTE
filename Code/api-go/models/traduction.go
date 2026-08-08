package models

// Langue represente une langue proposee par le site (fr, en, it, pt...).
//
// La colonne "code" est la cle primaire (pas un id numerique) : c'est ce code
// qu'on retrouve partout ailleurs (nom du fichier JSON, parametre ?lang=fr,
// attribut <html lang="fr">). Utiliser directement le code evite d'avoir a
// faire une jointure pour retrouver "fr" a partir d'un numero.
type Langue struct {
	Code    string `json:"code"`
	Libelle string `json:"libelle"`
}

// Traduction represente UN libelle de l'interface, dans UNE langue.
//
//	{"cle": "connexion.titre", "valeur": "Connexion", "code_langue": "fr"}
//	{"cle": "connexion.titre", "valeur": "Sign in",   "code_langue": "en"}
//
// La cle ne s'affiche jamais : c'est un identifiant technique. C'est la valeur
// qui apparait a l'ecran.
type Traduction struct {
	Id         int    `json:"id"`
	Cle        string `json:"cle"`
	Valeur     string `json:"valeur"`
	CodeLangue string `json:"code_langue"`
}
