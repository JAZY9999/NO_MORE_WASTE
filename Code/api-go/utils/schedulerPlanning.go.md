# utils/schedulerPlanning.go — l'envoi quotidien des plannings

> ⏱️ **Lecture : ~5 min** · 437 mots, 14 lignes de code

## C'est quoi ce fichier ?

Le deuxième job automatique du projet (après celui des rappels d'adhésion, voir `utils/scheduler.go.md`) : chaque jour, générer le planning de chaque bénévole ayant au moins un créneau, et le lui envoyer par email en pièce jointe.

Il répond à l'exigence du sujet : *"tous les jours, des plannings sont créés, édités et envoyés aux différents bénévoles"*.

## Le regroupement par bénévole — le point technique intéressant

```go
planningParBenevole := make(map[int][]models.LignePlanning)
for _, l := range lignes {
    planningParBenevole[l.BenevoleId] = append(planningParBenevole[l.BenevoleId], l)
}
```

`db.ListPlanningDuJour` (voir `db/servicesRepository.go.md`) retourne une liste **plate** : une ligne par créneau, tous bénévoles mélangés. Or on ne veut pas envoyer un email par créneau (un bénévole avec 3 créneaux recevrait 3 emails !) — on veut **un seul email par bénévole**, contenant tous ses créneaux.

### C'est quoi une `map` en Go ?
Une `map[int][]models.LignePlanning` est un dictionnaire : à chaque clé (ici un identifiant de bénévole, un `int`) correspond une valeur (ici une liste de lignes de planning). C'est l'équivalent d'un tableau associatif en PHP.

`planningParBenevole[l.BenevoleId] = append(planningParBenevole[l.BenevoleId], l)` se lit : "prends la liste déjà associée à ce bénévole (une liste vide s'il n'y en a pas encore), ajoute-lui cette ligne, et remets le résultat dans le dictionnaire". Après la boucle, chaque bénévole a sa propre liste de créneaux.

## L'envoi

```go
for _, lignesBenevole := range planningParBenevole {
    premiereLigne := lignesBenevole[0]

    if premiereLigne.Email == nil {
        continue
    }
    ...
}
```

On parcourt ensuite le dictionnaire (une itération = un bénévole). `lignesBenevole[0]` récupère la première ligne, uniquement pour lire les informations qui sont les mêmes sur toutes les lignes de ce bénévole (son nom, prénom, email) — pas besoin de les chercher ailleurs.

`if premiereLigne.Email == nil { continue }` : on saute les bénévoles sans email enregistré (impossible de leur envoyer quoi que ce soit), exactement comme dans le job des rappels d'adhésion.

Le CSV est généré (voir `utils/planning.go.md`) puis envoyé avec `EnvoyerEmailAvecPieceJointe` (voir `utils/mailer.go.md`).

## Deux fonctions, deux usages

- `ExecuterJobPlannings()` : utilise la date du jour, appelée automatiquement par la goroutine toutes les 24h (voir `utils/scheduler.go.md`).
- `EnvoyerPlanningsPourDate(date)` : accepte n'importe quelle date, appelée par la route `POST /admin/jobs/plannings?date=...` — indispensable pour une démonstration (on peut tester avec une date où on sait qu'il y a des créneaux, sans attendre le lendemain).

## Piège à connaître

Contrairement au job des rappels d'adhésion, il n'y a **pas de mécanisme anti-doublon** ici : si on déclenche deux fois le job pour la même date, les bénévoles reçoivent deux fois le même planning. C'est un choix assumé — un planning est une information du jour qu'il n'est pas grave de renvoyer (contrairement à un rappel commercial insistant), et cela permet au staff de renvoyer volontairement un planning si un bénévole dit ne pas l'avoir reçu.
