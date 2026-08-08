# models/adhesion.go — les structures liées aux adhésions et aux rappels

> ⏱️ **Lecture : ~7 min** · 380 mots

## C'est quoi ce fichier ?

Quatre structs, sans aucune logique : `Adhesion` (une adhésion telle qu'elle existe en base), `AdhesionDetaillee` (le résultat enrichi utilisé par l'écran des adhésions du back-office), `AdhesionARenouveler` (le résultat enrichi utilisé pour lister les adhésions à relancer), `RappelHistorique` (une ligne de l'historique des rappels envoyés).

## Adhesion

```go
type Adhesion struct {
    Id                int     `json:"id"`
    CommercantId      int     `json:"commercant_id"`
    DateDebut         string  `json:"date_debut"`
    DateFin           string  `json:"date_fin"`
    Statut            string  `json:"statut"`
    MontantCotisation *string `json:"montant_cotisation"`
}
```
Voir `db/adhesionsRepository.go.md` pour l'explication des choix de types (`string` pour les dates, pointeur pour le montant optionnel). Le champ `RappelEnvoye` qui existait au départ a été retiré : un simple booléen ne suffisait plus une fois qu'on a plusieurs TYPES de rappels possibles (voir `db/rappelsRepository.go.md` pour l'explication complète de ce changement).

## AdhesionDetaillee — ajoutée en portant l'écran des adhésions

```go
type AdhesionDetaillee struct {
    Id                int     `json:"id"`
    CommercantId      int     `json:"commercant_id"`
    RaisonSociale     string  `json:"raison_sociale"`
    Email             *string `json:"email"`
    DateDebut         string  `json:"date_debut"`
    DateFin           string  `json:"date_fin"`
    Statut            string  `json:"statut"`
    MontantCotisation *string `json:"montant_cotisation"`
    JoursRestants     int     `json:"jours_restants"`
}
```

Avant cette struct, le back-office n'avait **aucun moyen de voir les adhésions qu'il gère** — seulement celles qui tombent à J-30 ou J-7 exactement (`AdhesionARenouveler`, ci-dessous). Impossible de savoir combien sont actives, ni lesquelles ont expiré sans être encore relancées.

Même logique que `AdhesionARenouveler` : le nom du commerçant est inclus, pour éviter au front un appel par ligne de tableau. `JoursRestants` est calculé par PostgreSQL (`a.date_fin - CURRENT_DATE`) — il connaît la date du **serveur**, celle du navigateur pourrait être fausse ou dans un autre fuseau.

## AdhesionARenouveler

```go
type AdhesionARenouveler struct {
    AdhesionId    int     `json:"adhesion_id"`
    CommercantId  int     `json:"commercant_id"`
    RaisonSociale string  `json:"raison_sociale"`
    Email         *string `json:"email"`
    DateFin       string  `json:"date_fin"`
    JoursRestants int     `json:"jours_restants"`
}
```

Ce n'est PAS le reflet direct d'une table SQL — c'est le résultat d'une requête qui COMBINE des informations de deux tables (`adhesions` ET `commercants`, via un `JOIN`, voir `db/rappelsRepository.go.md`). On a besoin du nom et de l'email du commerçant (pas juste son id) pour pouvoir directement afficher une liste utile côté back-office, et pour pouvoir envoyer l'email de rappel sans avoir à refaire une requête séparée.

`JoursRestants` est un champ calculé (pas stocké en base) : c'est le résultat du calcul de date fait directement dans la requête SQL (`a.date_fin - CURRENT_DATE`).

## RappelHistorique

```go
type RappelHistorique struct {
    Id                int    `json:"id"`
    AdhesionId        int    `json:"adhesion_id"`
    TypeRappel        string `json:"type_rappel"`
    DateEnvoi         string `json:"date_envoi"`
    EmailDestinataire string `json:"email_destinataire"`
}
```

Le reflet direct d'une ligne de la table `adhesion_rappels`. `TypeRappel` contient une des valeurs `"j30"`, `"j7"`, `"ex_abonne"`, ou `"manuel"` (voir `utils/scheduler.go.md` et `app/rappels.go.md` pour ces différents cas). `EmailDestinataire` garde une trace de l'adresse email utilisée AU MOMENT de l'envoi — même si l'email du commerçant change plus tard en base, cet historique reste fidèle à ce qui a réellement été envoyé.
