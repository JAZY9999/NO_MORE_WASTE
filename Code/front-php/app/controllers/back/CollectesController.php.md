# Le module collectes — contrôleur et vues

> ⏱️ **Lecture : ~9 min** · 750 mots

> Couvre `app/controllers/back/CollectesController.php`, `app/views/back/collectes.php` et `app/views/back/collecte_detail.php`.
>
> **C'est ici que la nourriture entre dans le système.** Tout ce qui sera plus tard rangé, puis livré, est né sur cet écran.

## Ce que le sujet demande

> *« gérer le système des collectes »*

Deux sources possibles, citées par le sujet : les **commerçants partenaires** et les **particuliers**. Le reste du parcours (stock, tournée, livraison) part de là.

## La règle : une source, et une seule

Une collecte concerne **soit** un commerçant, **soit** un particulier. Jamais les deux, jamais aucun des deux.

Cette règle est appliquée par l'API. L'écran ne la re-vérifie pas — il se contente de deviner laquelle des deux colonnes est remplie :

```php
$source = $collecte['particulier_nom'] ?? '';
if (!empty($collecte['commercant_id'])) {
    // c'est un commerçant : on va chercher sa raison sociale
}
```

L'ordre compte : on part du particulier, et le commerçant **écrase** cette valeur s'il existe. Si les deux étaient remplis (ce que la base interdit), on afficherait le commerçant plutôt qu'un mélange incompréhensible.

## Un index plutôt que N requêtes

Dans la liste, chaque ligne doit afficher le nom du commerçant. Or l'API ne renvoie qu'un `commercant_id`.

La tentation est d'appeler `/commercants/{id}` **dans la boucle**. Dix lignes feraient onze requêtes. Cent lignes en feraient cent-une. C'est le problème classique dit « N+1 ».

```php
$commercants = [];
foreach ($this->extraire($this->api->get('/commercants/', Auth::jeton())) as $c) {
    $commercants[$c['id']] = $c['raison_sociale'] ?? '';
}
```

**Une seule requête, puis un tableau indexé par identifiant.** La vue fait ensuite `$commercants[$c['commercant_id']]`, qui est une lecture instantanée en mémoire.

Le même motif sert aux tournées pour les chauffeurs. Quand on le reconnaît une fois, on le voit partout.

## Le scan : le geste du terrain

C'est l'action principale de l'écran de détail. Sur le terrain, quelqu'un tient une douchette et scanne les produits ramassés, un par un.

```php
Flight::route('POST /back/collectes/@id', [$collectes, 'traiter']);
// avec <input type="hidden" name="action" value="scanner">
```

### Une seule requête crée le produit ET le rattache

`POST /collectes/{id}/produits` fait les deux d'un coup. Ce n'est pas un hasard : ça correspond exactement au geste réel. On ne « crée un produit » puis on ne « l'associe à une collecte » — **on ramasse quelque chose pendant une collecte**.

Si l'API avait exigé deux appels, une panne entre les deux aurait laissé un produit orphelin, en stock, rattaché à rien.

### Les champs facultatifs ne sont pas envoyés vides

```php
foreach (['categorie', 'dlc'] as $champ) {
    $valeur = trim($_POST[$champ] ?? '');
    if ($valeur !== '') {
        $produit[$champ] = $valeur;
    }
}
```

Un formulaire HTML envoie **toujours** ses champs, même vides. Sans ce filtrage, `dlc` partirait comme `""` — et PostgreSQL refuserait une chaîne vide dans une colonne `DATE`. L'utilisateur verrait une erreur incompréhensible pour un champ qu'il a délibérément laissé de côté.

Même logique pour `quantite` et `emplacement_id`, testés en `> 0` : `(int) ""` vaut `0`, qui n'est l'identifiant d'aucun emplacement.

### Ce qu'on impose, ce qu'on laisse choisir

```php
'statut' => 'en_stock',
```

Un produit qu'on vient de ramasser est en stock. Ce n'est pas une question à poser : proposer un menu déroulant ici permettrait de créer un produit directement « distribué », ce qui n'a aucun sens.

**Règle générale du projet : ce que le contexte détermine n'est pas un champ de formulaire.**

## Pas de matériel spécifique

Une douchette code-barre se comporte comme un **clavier** : elle « tape » le code puis appuie sur Entrée. Aucun pilote, aucune bibliothèque JavaScript. Le champ est un `<input>` ordinaire.

C'est ce qui rend l'exigence du sujet réalisable sans matériel particulier — et démontrable au clavier pendant la soutenance.

## Comment le vérifier soi-même

```bash
# se connecter, puis scanner un produit
curl -X POST http://localhost:8080/back/collectes/1 -b cookies.txt \
  -d "action=scanner&code_barre=3999888777666&libelle=Conserves tomates"
# -> « Produit ajouté », le produit apparaît dans /back/stocks

# scan incomplet : le libellé manque
curl -X POST http://localhost:8080/back/collectes/1 -b cookies.txt \
  -d "action=scanner&code_barre=123"
# -> « Renseignez au moins le code-barre et le libellé. », rien n'est créé

# statut forgé
curl -X POST http://localhost:8080/back/collectes/1 -b cookies.txt \
  -d "action=changer_statut&statut=pirate"
# -> « Statut invalide. », l'API n'est même pas appelée
```

Vérifié le 2026-08-05, ainsi que les quatre langues sur les deux écrans.

## Fichiers liés

- [StocksController.php.md](StocksController.php.md) — où atterrissent les produits scannés
- [TourneesController.php.md](TourneesController.php.md) — où ils ressortent
- [BenevolesController.php.md](BenevolesController.php.md) — le même patron, expliqué en détail
- [../../../../api-go/app/collectes.go.md](../../../../api-go/app/collectes.go.md) — la règle « une source et une seule » côté API
