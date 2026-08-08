# db/rappelsRepository.go — les requêtes SQL pour trouver qui relancer

> ⏱️ **Lecture : ~5 min** · 568 mots, 24 lignes de code

## C'est quoi ce fichier ?

Le repository qui répond à des questions comme "quelles adhésions arrivent bientôt à expiration ?", "quels commerçants sont partis depuis longtemps ?", "a-t-on déjà envoyé ce type de rappel pour cette adhésion ?".

## Fonction 1 : ListAdhesionsARenouveler

```go
func ListAdhesionsARenouveler(joursAvant int) ([]models.AdhesionARenouveler, error) {
    rows, err := Conn.Query(`
        SELECT a.id, a.commercant_id, c.raison_sociale, c.email, a.date_fin,
               (a.date_fin - CURRENT_DATE) AS jours_restants
        FROM adhesions a
        JOIN commercants c ON c.id = a.commercant_id
        WHERE a.statut = 'active'
          AND (a.date_fin - CURRENT_DATE) = $1
    `, joursAvant)
    ...
}
```

### La nouveauté : un `JOIN` SQL
Jusqu'ici, toutes nos requêtes lisaient une seule table à la fois. Ici, on a besoin d'informations qui sont réparties sur DEUX tables : la date de fin est dans `adhesions`, mais le nom et l'email du commerçant sont dans `commercants`. Un `JOIN` permet de "fusionner" temporairement les deux tables pour une seule requête, en précisant comment les relier (`ON c.id = a.commercant_id` : "la ligne de `commercants` dont l'id correspond au `commercant_id` de la ligne d'`adhesions`").

### Le calcul de date directement en SQL
```sql
(a.date_fin - CURRENT_DATE) AS jours_restants
```
Postgres sait faire des calculs sur les dates directement dans une requête : soustraire deux dates donne un nombre de jours. `CURRENT_DATE` est une fonction Postgres qui retourne la date du jour. `AS jours_restants` donne un nom à cette colonne calculée, pour pouvoir la lire ensuite avec `Scan(&a.JoursRestants)` côté Go, exactement comme une colonne normale.

### Pourquoi `WHERE ... = $1` (exactement égal) et pas `<=`
On cherche les adhésions dont il reste **exactement** `joursAvant` jours (30 ou 7), pas "30 jours ou moins". C'est voulu : le job (voir `utils/scheduler.go.md`) tourne une fois par jour, donc chaque adhésion ne "matchera" cette condition qu'un seul jour précis dans sa vie — ce qui, combiné à la vérification "déjà envoyé" (voir plus bas), garantit qu'un seul email de ce type est envoyé, pile au bon moment.

## Fonction 2 : ListExAbonnesDepuis

Même structure que la fonction précédente, mais inversée : on cherche les adhésions dont le statut est `'expiree'` ou `'resiliee'`, et dont ça fait exactement X jours qu'elles sont dans cet état (`CURRENT_DATE - a.date_fin`, cette fois dans l'autre sens puisqu'on regarde dans le passé plutôt que vers le futur).

## Fonction 3 : RappelDejaEnvoye

```go
func RappelDejaEnvoye(adhesionId int, typeRappel string) (bool, error) {
    var count int
    err := Conn.QueryRow(
        "SELECT COUNT(*) FROM adhesion_rappels WHERE adhesion_id = $1 AND type_rappel = $2",
        adhesionId, typeRappel,
    ).Scan(&count)
    ...
    return count > 0, nil
}
```

`COUNT(*)` est une fonction SQL qui compte le nombre de lignes qui correspondent aux conditions du `WHERE`. Si ce nombre est supérieur à 0, ça veut dire qu'une ligne existe déjà dans `adhesion_rappels` pour cette adhésion et ce type de rappel précis — donc on a déjà envoyé ce rappel, pas besoin de recommencer.

## Fonction 4 : EnregistrerRappelEnvoye

Un simple `INSERT` dans `adhesion_rappels`, appelé juste après un envoi d'email réussi (voir `utils/scheduler.go.md`). C'est cette ligne qui, le lendemain, fera que `RappelDejaEnvoye` retournera `true` pour éviter un doublon.

## Fonction 5 : ListHistoriqueRappels

Liste tous les rappels déjà envoyés pour une adhésion donnée, triés par date d'envoi (`ORDER BY date_envoi`). Utilisée par la route `GET /adhesions/{id}/historique-rappels` (voir `app/rappels.go.md`) pour que le back-office puisse afficher "quels emails ont déjà été envoyés à ce commerçant".

## Pourquoi une table séparée `adhesion_rappels` plutôt qu'un simple champ `rappel_envoye BOOLEAN`

La première version du projet avait un simple booléen `rappel_envoye` sur la table `adhesions`. Ça suffisait tant qu'il n'existait qu'UN SEUL type de rappel possible. Mais avec 3 types différents (J-30, J-7, relance ex-abonné) qui peuvent chacun être envoyés ou non, indépendamment les uns des autres, un seul booléen ne suffisait plus — impossible de savoir "lequel" a été envoyé. Une table séparée, avec une ligne par envoi réel, permet de garder un historique complet et de vérifier chaque type de rappel indépendamment.
