# Le module tournées — contrôleur et vues

> ⏱️ **Lecture : ~12 min** · 1 050 mots

> Couvre `app/controllers/back/TourneesController.php`, `app/views/back/tournees.php` et `app/views/back/tournee_detail.php`.
>
> **C'est le bout de la chaîne.** Un produit ramassé pendant une collecte, rangé dans un emplacement, ressort ici — et sa sortie laisse une trace signée : le PDF.

## Ce que le sujet demande

> *« gérer les tournées de distribution (associations caritatives, particuliers en détresse…) »*
>
> *« Chaque livraison donnera lieu à l'émission d'un **récapitulatif au format PDF** »*

La deuxième phrase est une exigence dure. Un écran de tournées sans PDF ne répondrait qu'à la moitié de la demande.

## La clôture : cinq opérations en un clic

Quand on clôture un arrêt, l'API enchaîne :

1. elle refuse si l'arrêt a **déjà** une livraison (409) ;
2. elle vérifie que tous les produits annoncés existent ;
3. elle crée la livraison ;
4. elle passe les produits remis au statut **`distribue`** ;
5. elle marque l'arrêt `livre` avec l'**heure réelle**.

Le front n'en fait aucune. Il envoie une liste de produits et affiche le résultat.

C'est délibéré : ces cinq étapes doivent réussir **ou échouer ensemble**. Si le front les orchestrait, une coupure de réseau entre l'étape 3 et l'étape 4 laisserait une livraison enregistrée pour des produits toujours affichés « en stock ». Le stock mentirait, et personne ne saurait pourquoi.

## Des tableaux parallèles dans le formulaire

Le formulaire propose trois lignes « produit + quantité ». En HTML, cela s'écrit :

```html
<select name="produit_id[]">…</select>
<input  name="quantite[]">
```

Les crochets disent à PHP : *range ça dans un tableau*. On reçoit donc **deux tableaux séparés**, que l'on recoud par le rang :

```php
foreach ($ids as $rang => $produitId) {
    $quantite = (int) ($quantites[$rang] ?? 0);
    if ($produitId > 0 && $quantite > 0) {
        $produits[] = ['produit_id' => $produitId, 'quantite' => $quantite];
    }
}
```

### Pourquoi ignorer les lignes vides

Les trois lignes sont **toujours** envoyées, même celles laissées à `—` et `0`. Sans le test `> 0`, on transmettrait `produit_id: 0` à l'API, qui répondrait « produit introuvable » pour une ligne que l'utilisateur n'a jamais remplie.

Le `?? 0` protège le cas où un tableau serait plus court que l'autre — ce qui n'arrive pas avec ce formulaire, mais rien ne garantit qu'un client forgé enverra les deux de même longueur. **On ne fait pas confiance à ce qui arrive en POST.**

### Pourquoi trois lignes, et pas dix

Trois suffisent à une livraison courante, et on peut clôturer plusieurs fois si nécessaire. Dix lignes vides sur dix arrêts feraient une page de plusieurs écrans de haut, difficile à parcourir.

## On ne propose que les produits en stock

```php
$produits = $this->extraire($this->api->get('/produits/?statut=en_stock', Auth::jeton()));
```

Proposer un produit déjà distribué n'aurait aucun sens, et l'API le refuserait. Autant ne pas l'afficher.

C'est la même idée que le bouton désactivé chez les bénévoles : **rendre l'erreur impossible plutôt que la signaler après coup.**

## Le PDF : pourquoi il passe par le front

C'est le point qu'il faut savoir expliquer, parce qu'il n'est pas intuitif.

Le lien évident serait :

```html
<a href="/api/livraisons/1/pdf">   <!-- ❌ répond 401 -->
```

Il ne fonctionne pas. Le jeton JWT est rangé dans la **session PHP**, pas dans un cookie que l'API saurait lire. Le navigateur qui suit ce lien n'envoie donc **aucune preuve d'identité**, et l'API répond `401 Jeton invalide`.

La solution est un relais côté front :

```php
public function pdf(string $id): void
{
    if (!Auth::exigerStaff($this->config)) { return; }

    $reponse = $this->api->get('/livraisons/' . (int) $id . '/pdf', Auth::jeton());

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="recapitulatif-' . (int) $id . '.pdf"');
    echo $reponse['brut'];
    exit;
}
```

Trois choses à remarquer :

- **`$reponse['brut']`, pas `['corps']`.** `corps` est le résultat de `json_decode`, qui vaut `null` sur un PDF. `ApiClient` conserve toujours la réponse telle qu'elle est arrivée — c'est exactement à cela que sert `brut`.
- **`inline`** ouvre le PDF dans le navigateur au lieu de le télécharger. On veut le relire avant de l'imprimer pour signature.
- **La garde s'applique aussi au PDF.** Déconnecté, `/back/livraisons/1/pdf` redirige vers `/connexion` — le récapitulatif n'est pas un document public.

## Deux corrections trouvées en testant cet écran

Ce module a révélé deux défauts **dans l'API**, pas dans le front. C'est le bénéfice attendu du portage : un écran réel pose des questions qu'une suite de tests ne pose pas.

### 1. Le PDF était inaccessible faute d'identifiant

`GET /tournees/{id}/etapes` disait qu'un arrêt était `livre`, mais ne donnait aucun moyen de retrouver **sa** livraison. Impossible, donc, de construire le lien vers le PDF exigé par le sujet.

Correction : un champ `livraison_id`, alimenté par un `LEFT JOIN` — **`LEFT` et non `JOIN` simple**, sinon les arrêts pas encore clôturés disparaîtraient de la liste, et l'écran ne montrerait plus le travail restant à faire.

### 2. Les heures s'affichaient « 0000- »

`heure_prevue` est une colonne `TIME`. Lue directement dans une chaîne Go, `database/sql` la reçoit comme une date complète et la formate en `"0000-01-01T10:30:00Z"` : une heure de passage affublée d'une année zéro.

Correction dans la requête SQL :

```sql
to_char(te.heure_prevue, 'HH24:MI')
```

On aurait pu découper la chaîne côté PHP. Ç'aurait été moins bien : chaque client de l'API aurait dû savoir qu'il faut ignorer onze caractères. **Une API qui renvoie une heure doit renvoyer une heure.**

## Comment le vérifier soi-même

```bash
# clôturer un arrêt
curl -X POST http://localhost:8080/back/tournees/1 -b cookies.txt \
  -d "action=cloturer&etape_id=2&produit_id[]=3&quantite[]=2"
# -> « Livraison clôturée, le PDF est disponible. »

# le produit est sorti du stock
curl -s http://localhost:8080/api/produits/3 -H "Authorization: $TOKEN"
# -> "statut": "distribue"

# le PDF
curl -s -o recap.pdf -b cookies.txt http://localhost:8080/back/livraisons/2/pdf
head -c 8 recap.pdf     # %PDF-1.4

# clôturer deux fois le même arrêt
# -> « Cette etape a deja fait l'objet d'une livraison » (409)

# clôturer sans rien sélectionner
# -> « Sélectionnez au moins un article avec une quantité. », l'API n'est pas appelée

# le PDF est protégé
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/back/livraisons/1/pdf
# -> 302 vers /connexion
```

Vérifié le 2026-08-07, cas d'erreur compris, ainsi que les quatre langues sur les deux écrans.

## Fichiers liés

- [CollectesController.php.md](CollectesController.php.md) — le début de la chaîne
- [StocksController.php.md](StocksController.php.md) — ce qui bascule en « distribué » ici
- [BenevolesController.php.md](BenevolesController.php.md) — seul un bénévole validé peut conduire
- [../../../../api-go/app/tournees.go.md](../../../../api-go/app/tournees.go.md) — les cinq opérations de la clôture
- [../../../../api-go/utils/pdf.go.md](../../../../api-go/utils/pdf.go.md) — le PDF écrit à la main, sans bibliothèque
