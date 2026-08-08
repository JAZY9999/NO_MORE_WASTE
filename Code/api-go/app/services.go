package app

import (
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"strconv"
	"time"

	"nomorewaste/db"
	"nomorewaste/models"
	"nomorewaste/utils"
)

// --- Services ---

func CreerService(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	var s models.Service
	err := json.NewDecoder(r.Body).Decode(&s)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if s.Nom == "" || s.Type == "" {
		http.Error(w, "nom et type sont obligatoires", http.StatusBadRequest)
		return
	}

	id, err := db.CreateService(s)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation du service", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

// ListerServices est une route PUBLIQUE : le sujet dit que les services sont
// "accessibles aux adherents", donc le catalogue doit etre consultable depuis
// le front-office sans forcement etre connecte.
func ListerServices(w http.ResponseWriter, r *http.Request) {
	var typeService *string
	if valeur := r.URL.Query().Get("type"); valeur != "" {
		typeService = &valeur
	}

	services, err := db.ListServices(typeService)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des services", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(services)
}

func ObtenirService(w http.ResponseWriter, r *http.Request) {
	id, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	service, err := db.GetServiceById(id)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du service", err)
		return
	}
	if service == nil {
		http.Error(w, "Service introuvable", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(service)
}

// --- Creneaux ---

func CreerCreneau(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	serviceId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	service, err := db.GetServiceById(serviceId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du service", err)
		return
	}
	if service == nil {
		http.Error(w, "Service introuvable", http.StatusNotFound)
		return
	}

	var c models.CreneauService
	err = json.NewDecoder(r.Body).Decode(&c)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	if c.DateCreneau == "" || c.HeureDebut == "" || c.HeureFin == "" {
		http.Error(w, "date_creneau, heure_debut et heure_fin sont obligatoires", http.StatusBadRequest)
		return
	}
	if c.CapaciteMax == 0 {
		c.CapaciteMax = 1
	}
	if c.Statut == "" {
		c.Statut = "ouvert"
	}

	c.ServiceId = serviceId

	id, err := db.CreateCreneau(c)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation du creneau", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerCreneauxService(w http.ResponseWriter, r *http.Request) {
	serviceId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	creneaux, err := db.ListCreneauxParService(serviceId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des creneaux", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(creneaux)
}

type affectationCreneauDto struct {
	BenevoleId *int `json:"benevole_id"`
}

// AffecterBenevoleCreneau realise l'"affectation a un service donne" citee par
// le sujet. Le benevole doit etre au statut "valide" (donc avoir passe toutes
// ses conditions, voir Phase 6) ET posseder la competence requise par le
// service, si celui-ci en exige une.
func AffecterBenevoleCreneau(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	creneauId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	creneau, err := db.GetCreneauById(creneauId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du creneau", err)
		return
	}
	if creneau == nil {
		http.Error(w, "Creneau introuvable", http.StatusNotFound)
		return
	}

	var dto affectationCreneauDto
	err = json.NewDecoder(r.Body).Decode(&dto)
	if err != nil {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}
	if dto.BenevoleId == nil {
		http.Error(w, "benevole_id est obligatoire", http.StatusBadRequest)
		return
	}

	benevole, err := db.GetBenevoleById(*dto.BenevoleId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du benevole", err)
		return
	}
	if benevole == nil {
		http.Error(w, "Benevole introuvable", http.StatusNotFound)
		return
	}
	if benevole.Statut != "valide" {
		http.Error(w, "Impossible d'affecter : ce benevole n'est pas valide (ses conditions ne sont pas toutes remplies)", http.StatusBadRequest)
		return
	}

	service, err := db.GetServiceById(creneau.ServiceId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du service", err)
		return
	}
	if service != nil && service.CompetenceRequiseId != nil {
		aLaCompetence, err := db.BenevoleADejaCompetence(*dto.BenevoleId, *service.CompetenceRequiseId)
		if err != nil {
			utils.ErreurServeur(w, r, "Erreur de verification de la competence", err)
			return
		}
		if !aLaCompetence {
			http.Error(w, "Impossible d'affecter : ce benevole n'a pas la competence requise par ce service", http.StatusBadRequest)
			return
		}
	}

	err = db.AffecterBenevoleCreneau(creneauId, dto.BenevoleId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur d'affectation", err)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

// --- Inscriptions ---

// InscrireACreneau inscrit quelqu'un a un creneau de service.
//
// # DEUX APPELANTS, DEUX REGLES DIFFERENTES
//
// Le personnel inscrit AUTRUI : c'est son travail (quelqu'un appelle au
// telephone, le staff l'inscrit). Les identifiants envoyes font donc foi.
//
// Un adherent ne peut inscrire QUE LUI-MEME. Ses identifiants ne sont jamais
// lus dans le corps de la requete : ils sont deduits de son jeton.
//
// Sans cette distinction, un adherent envoyant {"commercant_id": 4} inscrivait
// la boutique d'un autre a sa place. Verifie : la requete repondait 201.
// C'est la regle deja appliquee par les routes /mon-espace -- ne jamais faire
// confiance a un identifiant fourni par le client pour designer QUI agit.
func InscrireACreneau(w http.ResponseWriter, r *http.Request) {
	email, ok := utils.RequireRole(w, r, "admin_back", "staff_back", "adherent")
	if !ok {
		return
	}

	creneauId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	creneau, err := db.GetCreneauById(creneauId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du creneau", err)
		return
	}
	if creneau == nil {
		http.Error(w, "Creneau introuvable", http.StatusNotFound)
		return
	}
	if creneau.Statut == "annule" {
		http.Error(w, "Ce creneau est annule", http.StatusBadRequest)
		return
	}

	nombreInscrits, err := db.CompterInscriptionsActives(creneauId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de verification de la capacite", err)
		return
	}
	if nombreInscrits >= creneau.CapaciteMax {
		http.Error(w, "Ce creneau est complet", http.StatusConflict)
		return
	}

	// Un corps VIDE est accepte : quand un adherent s'inscrit lui-meme, il
	// n'a rien a envoyer -- tout est deduit de son jeton. Exiger un objet
	// JSON vide "{}" serait une formalite sans utilite. io.EOF signale
	// exactement ce cas ; toute autre erreur reste un vrai JSON invalide.
	var i models.InscriptionService
	err = json.NewDecoder(r.Body).Decode(&i)
	if err != nil && !errors.Is(err, io.EOF) {
		http.Error(w, "JSON invalide", http.StatusBadRequest)
		return
	}

	// --- Qui est reellement inscrit ---
	utilisateur, err := db.GetUtilisateurByEmail(email)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation de l'utilisateur", err)
		return
	}
	if utilisateur == nil {
		http.Error(w, "Utilisateur introuvable", http.StatusNotFound)
		return
	}

	if utilisateur.Role == "adherent" {
		// On ECRASE ce que le client a envoye. Un adherent qui tente
		// {"commercant_id": <celui d'un autre>} s'inscrit lui-meme malgre tout.
		commercant, err := db.GetCommercantByUtilisateurId(utilisateur.Id)
		if err != nil {
			utils.ErreurServeur(w, r, "Erreur de recuperation du commercant", err)
			return
		}

		if commercant != nil {
			// Il a une fiche commercant : on l'inscrit a ce titre.
			i.CommercantId = &commercant.Id
			i.UtilisateurId = nil
		} else {
			// Adherent sans boutique : il s'inscrit en son nom propre. Le
			// sujet dit que les services sont ouverts aux adherents, pas
			// seulement aux commercants.
			i.CommercantId = nil
			i.UtilisateurId = &utilisateur.Id
		}
	} else if i.CommercantId == nil && i.UtilisateurId == nil {
		// Personnel : il inscrit quelqu'un, il doit donc dire qui.
		http.Error(w, "commercant_id ou utilisateur_id est obligatoire", http.StatusBadRequest)
		return
	}

	// Le statut n'est jamais choisi par le client : on ne s'inscrit pas
	// directement au statut "annule" ou "present".
	i.Statut = "inscrit"

	i.CreneauId = creneauId

	id, err := db.CreateInscription(i)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de creation de l'inscription", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]int{"id": id})
}

func ListerInscriptionsCreneau(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	creneauId, err := strconv.Atoi(r.PathValue("id"))
	if err != nil {
		http.Error(w, "Id invalide", http.StatusBadRequest)
		return
	}

	inscriptions, err := db.ListInscriptionsParCreneau(creneauId)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation des inscriptions", err)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(inscriptions)
}

// --- Planning ---

// TelechargerPlanning renvoie directement le fichier CSV du planning d'une date
// donnee (tous benevoles confondus), pour que le back-office puisse le consulter
// sans attendre l'envoi automatique par email.
func TelechargerPlanning(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	date := r.URL.Query().Get("date")
	if date == "" {
		date = time.Now().Format("2006-01-02")
	}

	lignes, err := db.ListPlanningDuJour(date)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de recuperation du planning", err)
		return
	}

	contenuCSV, err := utils.GenererPlanningCSV(lignes)
	if err != nil {
		utils.ErreurServeur(w, r, "Erreur de generation du planning", err)
		return
	}

	w.Header().Set("Content-Type", "text/csv; charset=UTF-8")
	w.Header().Set("Content-Disposition", "attachment; filename=\"planning-"+date+".csv\"")
	w.Write(contenuCSV)
}

func DeclencherJobPlannings(w http.ResponseWriter, r *http.Request) {
	_, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	date := r.URL.Query().Get("date")
	if date == "" {
		date = time.Now().Format("2006-01-02")
	}

	utils.EnvoyerPlanningsPourDate(date)

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "envoi des plannings execute pour le " + date})
}
