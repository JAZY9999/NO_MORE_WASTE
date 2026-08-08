# db/collectesRepository.go — les requêtes SQL pour les collectes

> ⏱️ **Lecture : ~5 min** · 281 mots, 24 lignes de code

## C'est quoi ce fichier ?

Le repository des collectes, avec une nouveauté intéressante : `UpdateStatutCollecte` qui remplit AUTOMATIQUEMENT une date en fonction du nouveau statut.

## Les fonctions classiques

`CreateCollecte`, `GetCollecteById` : rien de nouveau par rapport aux repositories précédents. `ListCollectes(statut *string)` : filtre optionnel, même technique que `ListProduits` (voir `db/produitsRepository.go.md`) — un seul critère ici, donc plus simple (pas besoin de `numeroParametre` qui s'incrémente, juste `$1` si le filtre est utilisé).

## UpdateStatutCollecte : remplir une date automatiquement selon le nouveau statut

```go
func UpdateStatutCollecte(id int, statut string, benevoleId *int) error {
    var err error
    if statut == "realisee" {
        _, err = Conn.Exec(
            "UPDATE collectes SET statut = $1, benevole_id = $2, date_realisee = now() WHERE id = $3",
            statut, benevoleId, id,
        )
    } else {
        _, err = Conn.Exec(
            "UPDATE collectes SET statut = $1, benevole_id = $2 WHERE id = $3",
            statut, benevoleId, id,
        )
    }
    ...
}
```

### Pourquoi deux requêtes différentes selon le cas
Quand le staff marque une collecte comme `"realisee"` (effectuée), on veut automatiquement enregistrer À QUEL MOMENT ça s'est passé — sans que le client (front PHP) ait besoin d'envoyer lui-même la date/heure actuelle. `now()` est une fonction Postgres qui retourne la date et l'heure exactes du moment où la requête s'exécute côté serveur — plus fiable que de faire confiance à l'horloge de l'ordinateur du client, qui pourrait être mal réglée.

Pour tous les AUTRES changements de statut (`"planifiee"`, `"annulee"`, etc.), on ne touche PAS à `date_realisee` — elle doit rester vide tant que la collecte n'est pas vraiment terminée. D'où le `if`/`else` : deux requêtes SQL légèrement différentes selon le cas, plutôt qu'une seule requête qui écraserait `date_realisee` avec `now()` à chaque appel, même pour un statut qui n'a rien à voir avec la fin de la collecte.

## ListProduitsParCollecte

```go
func ListProduitsParCollecte(collecteId int) ([]models.Produit, error) {
    rows, err := Conn.Query(
        `SELECT ... FROM produits WHERE collecte_id = $1 ORDER BY id`,
        collecteId,
    )
    ...
}
```

Une simple liste filtrée par `collecte_id` — répond au besoin du sujet "rattache les produits scannés à la collecte" : cette fonction permet de retrouver, à tout moment, tous les produits qui ont été enregistrés lors d'une collecte précise.
