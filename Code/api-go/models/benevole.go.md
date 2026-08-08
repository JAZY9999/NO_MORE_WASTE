# models/benevole.go — un bénévole, ses documents, et les compétences disponibles

> ⏱️ **Lecture : ~5 min** · 193 mots, 21 lignes de code

## C'est quoi ce fichier ?

Trois structs : `Benevole` (une personne qui candidate/s'engage), `Competence` (chauffeur, cuisinier, plombier... déjà insérées dans `schema.sql`), `BenevoleDocument` (une pièce justificative liée à une "condition à valider" avant affectation, citée dans le sujet).

## Benevole

```go
type Benevole struct {
    Id              int     `json:"id"`
    UtilisateurId   *int    `json:"utilisateur_id"`
    Nom             string  `json:"nom"`
    Prenom          string  `json:"prenom"`
    Telephone       *string `json:"telephone"`
    Adresse         *string `json:"adresse"`
    Statut          string  `json:"statut"`
    PermisConduire  bool    `json:"permis_conduire"`
    DateCandidature string  `json:"date_candidature"`
    DateValidation  *string `json:"date_validation"`
}
```

`Nom`/`Prenom` obligatoires, tout le reste optionnel — logique identique aux autres structs du projet. `DateValidation` reste `nil` tant que le bénévole n'est pas encore validé (voir `app/benevoles.go.md`, `ValiderBenevole`, qui la remplit automatiquement).

## Competence

Une struct minimaliste : juste `Id` et `Libelle`. Ces compétences (`chauffeur`, `cuisinier`, `plombier`, `electricien`, `bricoleur`) sont déjà insérées une fois pour toutes dans `schema.sql` — le staff ne peut pas en créer de nouvelles via l'API (pas de route `POST /competences`), seulement les associer/dissocier d'un bénévole.

## BenevoleDocument

```go
type BenevoleDocument struct {
    Id            int     `json:"id"`
    BenevoleId    int     `json:"benevole_id"`
    TypeDocument  string  `json:"type_document"`
    CheminFichier *string `json:"chemin_fichier"`
    Valide        bool    `json:"valide"`
}
```

C'est ici qu'est modélisée l'exigence du sujet : "chacun peut s'inscrire (…) à condition de valider un certain nombre de conditions". Chaque `BenevoleDocument` représente une condition/pièce à fournir (par exemple `"permis_conduire"`, `"casier_judiciaire"`), avec un champ `Valide` que seul le staff peut passer à `true` — voir `app/benevoles.go.md` pour comment cette validation influence le passage du bénévole au statut final "valide".
