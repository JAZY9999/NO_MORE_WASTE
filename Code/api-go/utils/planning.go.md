# utils/planning.go — générer le fichier "Excel" du planning

> ⏱️ **Lecture : ~5 min** · 513 mots, 10 lignes de code

## Le point à savoir justifier à l'oral

Le sujet demande des plannings "sous la forme de fichiers Excel". Mais la consigne du cours ESGI est stricte : **aucune librairie externe autorisée** (hors driver de base de données). Or Go ne sait pas produire de fichier `.xlsx` natif sans librairie externe (comme `excelize`).

La solution retenue : générer un **CSV** avec le package standard `encoding/csv` — un format qu'Excel ouvre nativement en double-cliquant. C'est le meilleur compromis entre la demande du sujet et la contrainte technique du cours. À noter : l'énoncé d'examen du cours cite lui-même explicitement `encoding/csv` comme le moyen d'exporter des données, ce qui confirme que c'est l'approche attendue.

## Le code, en détail

```go
func GenererPlanningCSV(lignes []models.LignePlanning) ([]byte, error) {
    var tampon bytes.Buffer

    tampon.WriteString("\xEF\xBB\xBF")

    ecrivain := csv.NewWriter(&tampon)
    ecrivain.Comma = ';'
    ...
}
```

### `bytes.Buffer` : écrire en mémoire plutôt que dans un fichier
Un `bytes.Buffer` est une zone de mémoire dans laquelle on peut écrire comme dans un fichier. On l'utilise ici parce qu'on ne veut PAS créer de fichier sur le disque : le CSV est soit envoyé directement en pièce jointe d'un email, soit renvoyé dans la réponse HTTP. La fonction retourne `[]byte` (les octets du fichier), pas un chemin de fichier.

### `tampon.WriteString("\xEF\xBB\xBF")` — le BOM UTF-8
Ces trois octets bizarres au tout début du fichier s'appellent un "BOM" (Byte Order Mark). Sans eux, Excel ouvre le fichier en supposant un autre encodage et affiche les accents en charabia (`CrÃ©neau` au lieu de `Créneau`). Ce BOM lui dit explicitement "ce fichier est en UTF-8". C'est un détail invisible mais indispensable pour que le fichier soit lisible par un bénévole français.

### `ecrivain.Comma = ';'` — le point-virgule au lieu de la virgule
Par défaut, un CSV sépare ses colonnes par des virgules (c'est le "C" de "Comma-Separated Values"). Mais Excel en configuration française attend des **points-virgules** — avec des virgules, il mettrait toute la ligne dans une seule colonne. On change donc le séparateur.

### `formaterDate` et `formaterHeure`
Postgres renvoie ses dates au format technique `2026-07-31T00:00:00Z` et ses heures `0000-01-01T14:00:00Z` — illisible dans un planning destiné à un humain. Ces deux petites fonctions les transforment en `31/07/2026` et `14:00` grâce à `time.Parse` (lire la date technique) puis `Format` (la réécrire autrement).

Le format `"02/01/2006"` peut surprendre : en Go, on décrit un format de date en écrivant **une date de référence précise** (le 2 janvier 2006 à 15h04) plutôt qu'avec des symboles comme `JJ/MM/AAAA`. `02` = le jour, `01` = le mois, `2006` = l'année, `15` = l'heure, `04` = les minutes. C'est une particularité de Go.

Les deux fonctions retournent la valeur d'origine si le format n'est pas reconnu (`if err != nil { return valeur }`) — mieux vaut afficher une date brute qu'une chaîne vide si Postgres change un jour son format de sortie.

### `ecrivain.Flush()` et `ecrivain.Error()`
`csv.Writer` garde temporairement les données en mémoire tampon pour être plus efficace. `Flush()` force l'écriture de tout ce qui reste. `Error()` juste après permet de vérifier qu'aucune erreur ne s'est produite pendant ces écritures différées — sans ce contrôle, une erreur d'écriture passerait totalement inaperçue.
