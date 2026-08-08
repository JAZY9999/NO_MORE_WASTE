# db/tourneesRepository.go — bénéficiaires, tournées, étapes, livraisons

> ⏱️ **Lecture : ~5 min** · 355 mots, 25 lignes de code

## C'est quoi ce fichier ?

Le repository le plus fourni du projet (15 fonctions), qui couvre les quatre tables du circuit de distribution. La plupart suivent les patterns déjà vus ; trois méritent une explication.

## MarquerEtapeLivree — enregistrer l'heure réelle automatiquement

```go
func MarquerEtapeLivree(etapeId int) error {
    _, err := Conn.Exec(
        "UPDATE tournee_etapes SET statut = 'livre', heure_reelle = CURRENT_TIME WHERE id = $1",
        etapeId,
    )
    ...
}
```

`CURRENT_TIME` est une fonction Postgres qui donne l'heure actuelle du serveur. Même principe que `date_realisee` sur les collectes (voir `db/collectesRepository.go.md`) : on n'attend pas que le client envoie l'heure, la base la remplit elle-même — plus fiable et impossible à falsifier depuis le front.

## ListEtapesParTournee — deux correctifs trouvés en portant l'écran

> 🔄 Cette fonction a été modifiée **deux fois** pendant le portage du back-office. Les deux défauts étaient invisibles pour les tests automatiques et bloquants pour l'écran.

```sql
SELECT te.id, te.tournee_id, te.beneficiaire_id, te.ordre,
       to_char(te.heure_prevue, 'HH24:MI'),
       to_char(te.heure_reelle, 'HH24:MI'),
       te.statut, l.id
FROM tournee_etapes te
LEFT JOIN livraisons l ON l.tournee_etape_id = te.id
WHERE te.tournee_id = $1
ORDER BY te.ordre
```

### 1. `LEFT JOIN` et le PDF exigé par le sujet

La requête ne renvoyait **aucun identifiant de livraison**. Un client savait qu'un arrêt était `livre`, mais n'avait aucun moyen de retrouver **sa** livraison — donc aucun moyen de construire le lien vers le récapitulatif PDF, alors que ce PDF est une exigence 🟥 du sujet.

D'où le `LEFT JOIN` et la colonne `l.id`, exposée sous `livraison_id`.

⚠️ **`LEFT` et non `JOIN` simple.** Avec une jointure ordinaire, seuls les arrêts **déjà livrés** apparaîtraient : l'écran ne montrerait plus que le travail fait, jamais celui qui reste. Exactement l'inverse de ce qu'on attend d'un écran de tournée.

Un `LEFT JOIN` garde toutes les lignes de gauche et met `NULL` à droite quand rien ne correspond — d'où le `*int` côté modèle.

### 2. `to_char` et les heures « 0000- »

`heure_prevue` est une colonne `TIME`. Lue directement dans une chaîne, `database/sql` la reçoit comme une date complète et la formate en `"0000-01-01T10:30:00Z"`.

L'écran affichait `0000-` au lieu de `10:30`. Corrigé à la source plutôt que découpé côté client : **une API qui renvoie une heure doit renvoyer une heure**, sinon chaque consommateur doit savoir qu'il faut ignorer onze caractères.

Le même défaut existait sur les créneaux de service (voir `servicesRepository.go.md`).

## AjouterProduitLivraison — deux effets en une fonction

```go
func AjouterProduitLivraison(livraisonId int, produitId int, quantite int) error {
    _, err := Conn.Exec("INSERT INTO livraison_produits ...")
    ...
    _, err = Conn.Exec("UPDATE produits SET statut = 'distribue' WHERE id = $1", produitId)
    ...
}
```

Cette fonction fait **deux choses** : elle rattache le produit à la livraison, ET elle change son statut en `distribue` dans le stock.

C'est logique métier important : une fois qu'un produit a été donné à un bénéficiaire, il ne fait plus partie du stock disponible. Sans cette seconde requête, le produit apparaîtrait encore comme `en_stock` dans les recherches de la Phase 3, alors qu'il a physiquement quitté l'entrepôt.

C'est le premier endroit du projet où une action sur un module (les tournées) modifie les données d'un autre module (les stocks) — le lien concret entre la Phase 3 et la Phase 5.

## GetRecapLivraison — quatre tables jointes, puis une seconde requête

```go
row := Conn.QueryRow(
    `SELECT l.id, l.date_livraison, t.id, t.date_tournee, b.nom, b.type, b.adresse, b.ville
     FROM livraisons l
     JOIN tournee_etapes te ON te.id = l.tournee_etape_id
     JOIN tournees t ON t.id = te.tournee_id
     JOIN beneficiaires b ON b.id = te.beneficiaire_id
     WHERE l.id = $1`,
    livraisonId,
)
```

### La chaîne de JOIN
C'est la requête la plus "profonde" du projet : pour connaître le bénéficiaire d'une livraison, il faut remonter toute la chaîne — une livraison pointe vers une étape, l'étape pointe vers une tournée ET vers un bénéficiaire. Chaque `JOIN` ajoute un maillon.

### Pourquoi deux requêtes et pas une seule
On pourrait techniquement joindre aussi `livraison_produits` et `produits` dans la même requête — mais on obtiendrait alors **une ligne par produit**, avec les informations du bénéficiaire répétées à l'identique sur chaque ligne. Il faudrait ensuite dédupliquer côté Go.

Faire deux requêtes séparées (une pour les informations générales, une pour la liste des produits) donne directement la bonne structure : un objet `RecapLivraison` contenant une liste `Produits`. C'est plus simple à lire et à maintenir, pour un coût négligeable ici (deux requêtes sur des tables indexées).
