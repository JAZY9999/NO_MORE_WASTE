# models/commercant.go — la forme des données d'un commerçant

> ⏱️ **Lecture : ~6 min** · 260 mots

## C'est quoi ce fichier ?

Comme `models/utilisateur.go`, ce fichier ne contient AUCUNE action — juste une `struct` (voir `models/utilisateur.go.md` pour l'explication de base des structs) qui décrit à quoi ressemble un commerçant adhérent de l'association.

## Le code

```go
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
    UtilisateurId *int `json:"utilisateur_id"`
}
```

## `UtilisateurId`, ajouté en portant l'espace client

C'est ce lien qui rend l'espace client possible : quand un commerçant se connecte, on retrouve **sa** fiche à partir de son compte, sans jamais lui demander son identifiant.

Un pointeur, comme le reste : le lien est **facultatif**. Le personnel peut enregistrer un commerçant avant que celui-ci ait un compte — c'était même la seule façon de faire, jusqu'à ce que la route `PUT /commercants/{id}` (voir `app/commercants.go.md`) permette de rattacher le compte après coup.

La colonne est `UNIQUE` en base : un même compte ne peut pas être rattaché à deux boutiques.

## Pourquoi seulement `Id` et `RaisonSociale` sont des `string`/`int` normaux, et tout le reste en pointeurs `*string`

C'est exactement le même raisonnement que pour `models/utilisateur.go.md` : dans la table SQL `commercants`, seules les colonnes `id` et `raison_sociale` sont `NOT NULL` (obligatoires) — voir `postgres/init/schema.sql`. Toutes les autres colonnes (siret, adresse, ville, pays, email, telephone, contact_nom) peuvent être vides (NULL) en base, si on ne les renseigne pas à la création. Un pointeur `*string` permet de représenter "pas de valeur du tout" (`nil`), ce qu'un `string` normal ne sait pas faire.

## Ce fichier ne contient pas la struct Adhesion

Volontairement : à ce stade, on code d'abord le module "commerçants" tout seul, un endpoint à la fois, avant de s'attaquer aux adhésions (qui viendront dans une prochaine étape, avec leur propre fichier `models/adhesion.go` et leur propre table `adhesions` déjà présente dans `schema.sql`).
