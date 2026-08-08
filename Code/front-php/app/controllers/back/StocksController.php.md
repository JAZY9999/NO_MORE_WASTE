# Le module stocks et emplacements — contrôleur et vues

> ⏱️ **Lecture : ~9 min** · 780 mots

> Couvre `app/controllers/back/StocksController.php`, `app/views/back/stocks.php` et `app/views/back/emplacements.php`.
>
> **Un seul contrôleur pour deux écrans.** Les emplacements n'existent que pour ranger des produits : les séparer aurait donné une classe de trente lignes qu'on ouvre une fois par an.

## Ce que le sujet demande

> *« chaque produit devra être référencé (code barre) »*, puis *« stocké et retrouvable **TRÈS RAPIDEMENT** »*

Les majuscules sont dans le sujet. Ce n'est pas une liste de produits qu'on demande — c'est une **recherche**. Cette phrase à elle seule dicte la mise en page de l'écran : le champ de recherche est en tête, tout le reste vient après.

## Une route, deux usages

```php
if ($codeBarre !== '') {
    $chemin = '/produits/?code_barre=' . urlencode($codeBarre);
} elseif ($statut !== '') {
    $chemin = '/produits/?statut=' . urlencode($statut);
} else {
    $chemin = '/produits/';
}
```

### Pourquoi le code-barre l'emporte sur le filtre

Imaginons quelqu'un qui consulte les produits « périmés », puis scanne un article. Si les deux critères se combinaient, un produit **en stock** ne serait pas trouvé — l'écran répondrait « aucun résultat » alors que le produit est là, dans la main de celui qui vient de le scanner.

**Quand on scanne, on veut CE produit.** Le filtre en cours n'est plus la question. Le `elseif` dit exactement cela.

## Le piège : la même route renvoie deux formes différentes

C'est le passage le plus délicat du fichier.

- `GET /produits/` renvoie **une liste** : `[{...}, {...}]`
- `GET /produits/?code_barre=X` renvoie **un seul objet** : `{...}`

Un code-barre est unique : renvoyer une liste d'un élément aurait été bizarre côté API. Mais la vue, elle, veut boucler dans tous les cas.

```php
if ($codeBarre !== '' && isset($corps['id'])) {
    $produits = [$corps];          // un objet -> un tableau d'un élément
} elseif ($codeBarre === '') {
    $produits = $corps;            // déjà une liste
}
```

**Le contrôleur normalise, la vue ne connaît qu'un seul cas.** Sans cela, `stocks.php` aurait besoin d'un `if` autour de sa boucle, et la même page aurait deux mises en page à maintenir.

## Un 404 qui n'est pas une erreur

```php
} elseif ($codeBarre !== '' && $reponse['code'] === 404) {
    $introuvable = true;
}
```

Scanner un code inconnu est **normal** : c'est un produit qui n'a jamais été collecté, ou une erreur de scan. Ce n'est pas une panne de l'application.

D'où deux traitements distincts :

| Situation | Ce que voit l'utilisateur |
|---|---|
| Code-barre inconnu | Un encadré neutre : *« Aucun produit avec le code-barre 123 »* |
| API injoignable, 500… | Un bandeau rouge d'erreur |

Confondre les deux ferait paniquer un bénévole pour un scan raté. **Un message d'erreur doit être réservé à ce qui est réellement anormal.**

## Les emplacements : trois niveaux

`entrepot · zone-rayon-étagère` — par exemple `Entrepôt Nord · A-3-2`.

```php
$emplacements[$e['id']] = trim(($e['entrepot'] ?? '') . ' · ' . ($e['zone'] ?? '')
    . '-' . ($e['rayon'] ?? '') . '-' . ($e['etagere'] ?? ''));
```

C'est encore l'index en une requête, comme pour les collectes : la liste des produits ne contient qu'un `emplacement_id`.

Sans les emplacements, « retrouvable très rapidement » ne serait vrai qu'à moitié : on saurait que le produit **existe**, sans savoir **où aller le chercher**. La recherche donne l'un, l'emplacement donne l'autre.

Seul `entrepot` est obligatoire. Une petite association peut n'avoir qu'une réserve sans zonage — l'obliger à inventer des numéros de rayon serait absurde.

## Modifier un produit : n'envoyer que ce qui change

```php
if (empty($donnees)) {
    $_SESSION['message_erreur'] = Langue::t('stocks.rien_a_modifier');
}
```

Le formulaire propose deux modifications : le statut et l'emplacement. On ne construit le corps de la requête qu'avec ce qui a été effectivement rempli et validé.

Envoyer un `PUT` vide serait au mieux inutile, au pire destructeur si l'API interprétait les champs absents comme « mettre à vide ». **Le dire à l'utilisateur vaut mieux qu'appeler l'API pour rien.**

## Aucun matériel spécifique

Une douchette code-barre est vue par l'ordinateur comme un **clavier**. Elle tape le code, puis Entrée — ce qui valide le formulaire. Le champ est un `<input type="text">` ordinaire.

C'est aussi pour cela que l'écran est démontrable pendant la soutenance sans matériel : on tape le code à la main, le comportement est identique.

## Comment le vérifier soi-même

```bash
# recherche exacte
curl -s -b cookies.txt "http://localhost:8080/back/stocks?code_barre=3999888777666"
# -> une ligne, avec son emplacement

# code inconnu : encadré neutre, pas de bandeau rouge
curl -s -b cookies.txt "http://localhost:8080/back/stocks?code_barre=000"
# -> « Aucun produit avec le code-barre 000 »

# la recherche l'emporte sur le filtre
curl -s -b cookies.txt "http://localhost:8080/back/stocks?statut=perime&code_barre=3999888777666"
# -> le produit est trouvé, bien qu'il soit « en stock »

# emplacement sans entrepôt
curl -X POST http://localhost:8080/back/emplacements -b cookies.txt -d "zone=A"
# -> « L'entrepôt est obligatoire. », rien n'est créé
```

Vérifié le 2026-08-06, ainsi que les quatre langues sur les deux écrans.

## Fichiers liés

- [CollectesController.php.md](CollectesController.php.md) — d'où viennent les produits
- [TourneesController.php.md](TourneesController.php.md) — où ils repartent, et pourquoi ils passent à « distribué »
- [BenevolesController.php.md](BenevolesController.php.md) — le même patron, expliqué en détail
- [../../../../api-go/app/produits.go.md](../../../../api-go/app/produits.go.md) — la route à double usage côté API
