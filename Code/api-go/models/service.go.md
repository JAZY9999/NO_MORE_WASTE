# models/service.go — services, créneaux, inscriptions, planning

> ⏱️ **Lecture : ~5 min** · 283 mots, 21 lignes de code

## C'est quoi ce fichier ?

Quatre structs pour le module Services : `Service` (une offre : cours de cuisine, gardiennage...), `CreneauService` (une date/heure précise où ce service est proposé), `InscriptionService` (quelqu'un qui s'inscrit à un créneau), `LignePlanning` (une ligne du planning d'un bénévole, utilisée pour générer le CSV).

## Service

```go
type Service struct {
    Id                  int     `json:"id"`
    Nom                 string  `json:"nom"`
    Description         *string `json:"description"`
    CompetenceRequiseId *int    `json:"competence_requise_id"`
    Type                string  `json:"type"`
    Actif               bool    `json:"actif"`
}
```

`Type` correspond à la liste fermée du sujet (`conseil_anti_gaspi`, `cours_cuisine`, `partage_vehicule`, `echange_service`, `reparation`, `gardiennage`, `autre`) — cette liste est vérifiée par une contrainte `CHECK` dans `schema.sql`, donc Postgres refuse toute autre valeur.

`CompetenceRequiseId` est optionnel : certains services exigent une compétence précise (un cours de cuisine demande un `cuisinier`), d'autres non. Quand ce champ est rempli, l'affectation d'un bénévole vérifie automatiquement qu'il possède bien cette compétence (voir `app/services.go.md`).

## CreneauService

Une instance datée d'un service : quel jour, de quelle heure à quelle heure, où, avec quelle capacité maximale, et quel bénévole est affecté pour l'animer (`BenevoleId`, optionnel tant que personne n'est affecté).

`DateCreneau`, `HeureDebut`, `HeureFin` sont des `string` — même choix que pour les dates d'adhésion (voir `models/adhesion.go.md`) : Postgres convertit lui-même le texte vers ses types `DATE`/`TIME`.

## InscriptionService

Qui s'est inscrit à un créneau. `CommercantId` et `UtilisateurId` sont tous les deux optionnels (mais il en faut au moins un, vérifié dans le handler) — un service peut être réservé par un commerçant adhérent OU par un utilisateur particulier.

## LignePlanning

```go
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
```

Comme `AdhesionARenouveler` (voir `models/adhesion.go.md`), ce n'est PAS le reflet d'une table SQL : c'est le résultat d'une requête qui combine TROIS tables (`creneaux_service` + `benevoles` + `services`) via des `JOIN`, pour rassembler en une seule ligne tout ce qu'il faut pour écrire le planning d'un bénévole ET le lui envoyer par email.
