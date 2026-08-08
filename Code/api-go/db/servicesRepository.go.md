# db/servicesRepository.go — services, créneaux, inscriptions, planning du jour

> ⏱️ **Lecture : ~5 min** · 257 mots, 23 lignes de code

## C'est quoi ce fichier ?

Le repository du module Services. La plupart des fonctions sont classiques (mêmes techniques que les repositories précédents) — deux méritent une explication.

## CompterInscriptionsActives — le contrôle de capacité

```go
func CompterInscriptionsActives(creneauId int) (int, error) {
    var count int
    err := Conn.QueryRow(
        "SELECT COUNT(*) FROM inscriptions_service WHERE creneau_id = $1 AND statut != 'annule'",
        creneauId,
    ).Scan(&count)
    ...
}
```

Compte les inscriptions d'un créneau, **en excluant celles qui ont été annulées** (`statut != 'annule'`). C'est important : si 3 personnes s'inscrivent puis 1 annule, il ne reste que 2 places prises, pas 3. Cette valeur est comparée à `capacite_max` avant chaque nouvelle inscription (voir `app/services.go.md`).

## ListPlanning — la requête la plus complexe du projet (3 tables jointes)

> 🔄 **Cette fonction s'appelait `ListPlanningDuJour(date)`.** Elle a été généralisée quand l'espace bénévole a eu besoin de la même requête, filtrée sur une personne au lieu d'une date. L'ancienne existe toujours et appelle la nouvelle — aucun appelant n'a été modifié.

```go
func ListPlanning(date *string, benevoleId *int) ([]models.LignePlanning, error) {
    requete := `SELECT b.id, b.nom, b.prenom, b.email, s.nom, cs.date_creneau,
                       to_char(cs.heure_debut, 'HH24:MI'),
                       to_char(cs.heure_fin, 'HH24:MI'),
                       cs.lieu
                FROM creneaux_service cs
                JOIN benevoles b ON b.id = cs.benevole_id
                JOIN services s ON s.id = cs.service_id
                WHERE cs.statut != 'annule'`

    // Les deux filtres sont facultatifs et s'ajoutent au besoin.
    ...
    requete += " ORDER BY cs.date_creneau, cs.heure_debut, b.id"
}
```

### Deux filtres facultatifs, une seule requête

|  | Ce qu'on obtient |
|---|---|
| `date == nil` | les créneaux **à venir** (aujourd'hui et après) |
| `date != nil` | ce jour précis — le job quotidien |
| `benevoleId != nil` | uniquement cette personne — l'espace bénévole |

Écrire une seconde requête aurait signifié la corriger **à deux endroits** le jour où le modèle change, et en oublier une.

⚠️ La **structure** de la requête change selon les filtres, mais les **valeurs** passent toujours par `$1`, `$2`… C'est ce qui protège de l'injection SQL. Concaténer une valeur directement dans la chaîne serait la faille classique.

### Deux `JOIN` enchaînés
Jusqu'ici, nos requêtes joignaient au maximum deux tables (voir `db/rappelsRepository.go.md`). Ici il en faut trois :
- On part de `creneaux_service` (ce qu'on veut lister).
- `JOIN benevoles` : pour connaître le nom, prénom et surtout l'**email** du bénévole affecté.
- `JOIN services` : pour connaître le NOM du service (le créneau ne stocke qu'un `service_id`, pas le libellé).

### Pourquoi un `JOIN` normal (et pas `LEFT JOIN`) sur `benevoles`
Un `JOIN` normal exclut automatiquement les créneaux qui n'ont AUCUN bénévole affecté (`benevole_id` vaut `NULL`). C'est exactement ce qu'on veut : on génère des plannings **pour les bénévoles**, donc un créneau sans bénévole affecté n'a aucun planning à produire.

### Le tri
Par date, puis par heure, puis par bénévole. Le code qui regroupe les lignes par bénévole (voir `utils/schedulerPlanning.go.md`) obtient ainsi des créneaux déjà dans l'ordre chronologique dans chaque planning.

**Le tri porte sur la colonne `TIME`, pas sur le texte produit par `to_char`.** Trier des heures comme du texte marcherait par chance en 24 h (`"09:00" < "14:00"`), mais pas avec un format sur 12 h où `"9:00 PM"` passerait avant `"10:00 AM"`.

## ⏱️ Pourquoi `to_char` sur les heures

`heure_debut` est une colonne `TIME`. Lue directement dans une chaîne Go, `database/sql` la reçoit comme une **date complète** et la formate ainsi :

```
"0000-01-01T14:00:00Z"
```

Une heure de créneau affublée d'une année zéro. Le front affichait `0000-` au lieu de `14:00`.

On aurait pu découper la chaîne côté client. `to_char` demande à PostgreSQL de renvoyer directement `"14:00"` — et **aucun client n'a besoin de savoir qu'il faut ignorer onze caractères**. Une API qui renvoie une heure doit renvoyer une heure.

Le même correctif a été appliqué aux étapes de tournée, qui avaient exactement le même défaut.

> Le CSV du planning, lui, n'était **pas** cassé : `utils.formaterHeure` savait déjà lire les deux formes. Mais il devait le savoir — c'est précisément la rustine que `to_char` rend inutile. Sa branche de repli est devenue le chemin normal.
