# models/campagne.go — les structures du système de campagnes ciblées

> ⏱️ **Lecture : ~5 min** · 209 mots, 17 lignes de code

## C'est quoi ce fichier ?

Deux structs : `Campagne` (une campagne d'email définie par le staff, avec ses critères de ciblage) et `DestinataireCampagne` (un commerçant qui correspond aux critères d'une campagne, une fois qu'on les a appliqués).

## Campagne

```go
type Campagne struct {
    Id                                int     `json:"id"`
    Nom                               string  `json:"nom"`
    SujetEmail                        string  `json:"sujet_email"`
    CorpsEmail                        string  `json:"corps_email"`
    CritereVille                      *string `json:"critere_ville"`
    CriterePays                       *string `json:"critere_pays"`
    CritereStatutAdhesion             *string `json:"critere_statut_adhesion"`
    CritereAdhesionExpireeDepuisJours *int    `json:"critere_adhesion_expiree_depuis_jours"`
}
```

### Pourquoi TOUS les critères sont des pointeurs (`*string`, `*int`)

C'est le point le plus important à comprendre dans ce fichier. Chaque critère est **optionnel** : une campagne peut cibler "tous les commerçants de Paris" (seul `CritereVille` est rempli), ou "tous les commerçants dont l'adhésion est expirée depuis plus de 180 jours, en France" (`CriterePays` + `CritereAdhesionExpireeDepuisJours` remplis, le reste `nil`), ou n'importe quelle combinaison.

Un pointeur qui vaut `nil` veut dire "ce critère n'est PAS utilisé pour cette campagne — ne filtre pas dessus". Un pointeur qui pointe vers une vraie valeur veut dire "filtre en utilisant cette valeur précise". C'est exactement ce que `db.ResoudreDestinatairesCampagne` (voir `db/campagnesRepository.go.md`) vérifie pour chaque critère : "est-ce que ce pointeur est `nil` ou pas ?".

## DestinataireCampagne

```go
type DestinataireCampagne struct {
    CommercantId  int     `json:"commercant_id"`
    RaisonSociale string  `json:"raison_sociale"`
    Email         *string `json:"email"`
}
```

Le résultat de l'application des critères d'une campagne : la liste des commerçants qui correspondent. Utilisé à la fois pour la prévisualisation (`GET /campagnes/{id}/destinataires`, voir `app/campagnes.go.md`) et pour le déclenchement réel de l'envoi (`POST /campagnes/{id}/declencher`).
