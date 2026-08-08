# app/produits.go — gérer les produits en stock, avec code-barre

> ⏱️ **Lecture : ~10 min** · 702 mots, 36 lignes de code

## C'est quoi ce fichier ?

Quatre handlers : créer un produit, lister/rechercher des produits (deux usages différents dans la même route), consulter un produit précis, et le déplacer/changer son statut.

## Fonction 1 : CreerProduit

```go
if p.Quantite == 0 {
    p.Quantite = 1
}
if p.Statut == "" {
    p.Statut = "en_stock"
}
```

Contrairement à `CreerCommercant` (voir `app/commercants.go.md`) qui exige que tous les champs obligatoires soient explicitement fournis, ici on applique des VALEURS PAR DÉFAUT si le client n'a pas précisé `quantite` ou `statut` — pratique, car en réalité, un produit qui vient d'être scanné a presque toujours une quantité de 1 et un statut "en stock", donc on évite au client (le futur front PHP, avec une douchette code-barre) d'avoir à toujours répéter ces deux valeurs.

```go
existant, err := db.GetProduitByCodeBarre(p.CodeBarre)
...
if existant != nil {
    http.Error(w, "Ce code-barre est deja utilise", http.StatusConflict)
    return
}
```

Avant de créer le produit, on vérifie qu'aucun autre produit n'a déjà ce code-barre — même si la colonne SQL est déjà `UNIQUE` (ce qui ferait échouer l'`INSERT` de toute façon), cette vérification permet de répondre clairement `409 Conflict` avec un message compréhensible, plutôt que de laisser remonter une erreur SQL brute peu claire.

## Fonction 2 : ListerProduits — une route, deux usages (le point le plus important à comprendre)

```go
func ListerProduits(w http.ResponseWriter, r *http.Request) {
    ...
    codeBarre := r.URL.Query().Get("code_barre")
    if codeBarre != "" {
        // recherche rapide d'UN SEUL produit precis
        ...
        return
    }

    // sinon : liste filtree de PLUSIEURS produits
    var categorie *string
    if valeur := r.URL.Query().Get("categorie"); valeur != "" {
        categorie = &valeur
    }
    ...
}
```

### `r.URL.Query().Get("code_barre")`
Récupère un "query parameter" — la partie de l'URL après le `?`, du style `/produits?code_barre=CB-0001`. Vu dans le support de cours "fonctions utiles en go.pdf". Contrairement à `r.PathValue(...)` (qui lit une partie FIXE du chemin de l'URL, comme `{id}`), les query parameters servent à des critères optionnels/de recherche, ce qui correspond exactement à notre besoin ici.

### Pourquoi une seule route pour deux usages différents plutôt que deux routes séparées
On aurait pu créer une route dédiée `GET /produits/recherche?code_barre=...` séparée de `GET /produits?categorie=...`. Le choix ici est de garder une seule route `GET /produits`, qui se comporte différemment selon les paramètres reçus : si `code_barre` est fourni, on privilégie la recherche exacte rapide (exigence du sujet) ; sinon, on retourne une liste filtrable pour un usage back-office plus général (parcourir le stock par catégorie/statut). C'est un choix pragmatique pour ce projet, pas une règle absolue — les deux approches sont valables.

### `var categorie *string` puis `if valeur := ...; valeur != "" { categorie = &valeur }`
Ce bout de code convertit un paramètre d'URL (toujours une simple `string`, éventuellement vide) en pointeur `*string` (`nil` si absent, une vraie valeur sinon) — exactement le format attendu par `db.ListProduits` (voir `db/produitsRepository.go.md`), qui décide d'ajouter ou non un filtre SQL selon que le pointeur est `nil` ou pas.

## Fonction 3 : ObtenirProduit

Classique, identique dans l'esprit à `ObtenirCommercant`.

## Fonction 4 : DeplacerProduit (changer l'emplacement et/ou le statut)

```go
type deplacementProduitDto struct {
    EmplacementId *int   `json:"emplacement_id"`
    Statut        string `json:"statut"`
}
```

### Une struct "DTO" dédiée à cette seule action
Plutôt que de réutiliser directement la struct `Produit` complète (qui contient aussi `CodeBarre`, `Libelle`, etc., des champs qu'on ne veut PAS pouvoir modifier via cette route), on définit ici un DTO minimal qui ne contient QUE ce que cette action a le droit de changer : l'emplacement et le statut. Le nom commence par une minuscule (`deplacementProduitDto`) car il n'est utilisé que dans ce fichier (voir `app/auth.go.md` pour le rappel sur les noms privés en Go).

Ce choix protège contre un problème classique : si on utilisait la struct `Produit` complète pour lire le JSON envoyé par le client, un client malveillant (ou juste maladroit) pourrait envoyer un `code_barre` différent dans le body de sa requête `PUT`, et si le code n'y prenait pas garde, ça pourrait écraser le code-barre existant par erreur. En limitant strictement ce que le DTO peut contenir, cette route ne PEUT PAS modifier autre chose que l'emplacement et le statut, quoi que le client envoie dans le body.

## Piège à connaître

Cette route s'appelle `PUT /produits/{id}` alors qu'elle ne remplace que DEUX champs (emplacement et statut), pas la totalité du produit — d'un point de vue strict des conventions REST vues en cours (`http.pdf` : "PUT = envoi d'entité pour remplacement"), on pourrait argumenter qu'un `PATCH` serait plus rigoureux ici (modification partielle). Le choix de `PUT` reste défendable car ces deux champs représentent ENSEMBLE "l'état physique du produit" (où il est + dans quel état), qu'on remplace intégralement à chaque appel — mais c'est un point qu'il faut savoir justifier si le jury pose la question.
