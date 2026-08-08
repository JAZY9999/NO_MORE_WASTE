# `app/controllers/back/TraductionsController.php` — l'écran de gestion du multilingue

> ⏱️ **Lecture : ~10 min** · 790 mots, 26 lignes de code

> **Phase 8** — c'est cet écran qui rend le multilingue **gérable**, et pas seulement présent.
> À lire après [`../../locales/LISEZ-MOI.md`](../../locales/LISEZ-MOI.md).

## Pourquoi cet écran existe

Le sujet demande un site multilingue **et** un back-office. Avec des libellés écrits en dur dans le code, corriger une faute de frappe en italien imposerait : modifier un fichier, reconstruire l'image Docker, redéployer. Pour un mot.

Ici, le staff ouvre `/back/traductions`, corrige, clique. Aucun déploiement.

C'est l'argument à donner si on te demande pourquoi tu n'as pas gardé de simples tableaux PHP : **un tableau figé dans le code n'est pas administrable**.

## Deux routes, une adresse

```php
Flight::route('GET  /back/traductions', [$traductions, 'index']);
Flight::route('POST /back/traductions', [$traductions, 'traiter']);
```

`index()` affiche, `traiter()` exécute. Le même découpage que la page de connexion.

## `index()` — préparer l'affichage

### Le regroupement par clé, le vrai apport de l'écran

L'API renvoie une liste à plat : une ligne par (clé, langue). Avec 63 clés et 4 langues, ça fait **252 lignes**, illisibles telles quelles.

```php
$parCle[$cle][$codeLangue] = ['id' => ..., 'valeur' => ...];
```

On obtient un tableau à deux niveaux, affiché comme ceci :

| Clé | Français | English | Italiano | Português |
|---|---|---|---|---|
| `nav.accueil` | Accueil | Home | Home | Início |
| `nav.services` | Nos services | *(vide)* | I nostri servizi | Os nossos serviços |

**63 lignes au lieu de 252**, et surtout : une traduction manquante **saute aux yeux** (cellule vide bordée d'orange). C'est impossible à repérer dans une liste à plat.

C'est ce qui transforme un CRUD basique en outil réellement utilisable.

### La recherche côté PHP

```php
stripos($t['cle'], $recherche) !== false || stripos($t['valeur'], $recherche) !== false
```

`stripos` cherche sans tenir compte de la casse. On cherche **dans la clé et dans la valeur** : on peut donc retrouver un libellé soit par son identifiant technique (`nav.`), soit par le texte qu'on a vu à l'écran (« Connexion »).

⚠️ `stripos` renvoie la **position** du texte trouvé, donc `0` si c'est au début. Comme `0` est équivalent à `false` en PHP, il faut impérativement comparer avec `!== false` et non écrire `if (stripos(...))`. Sans ça, tout résultat commençant par le terme cherché serait ignoré. C'est un piège PHP classique.

Le filtrage se fait en PHP, pas dans l'API : avec quelques centaines de libellés c'est instantané, et ça évite d'ajouter un paramètre de recherche à l'API.

## `traiter()` — sept actions, un seul point d'entrée

```php
switch ($action) {
    case 'exporter':         // base -> fichiers
    case 'importer':         // fichiers -> base
    case 'creer':            // nouveau libellé
    case 'modifier':         // édition en place
    case 'supprimer':
    case 'creer_langue':
    case 'supprimer_langue':
}
```

Tous les formulaires de la page envoient vers la même adresse, avec un champ caché `action`. On voit ainsi **d'un coup d'œil tout ce que l'écran peut faire**, plutôt que d'éparpiller sept routes.

Le `default` répond « Action inconnue » : un formulaire mal formé ne passe pas en silence.

## La redirection après traitement — pas un détail

```php
Auth::rediriger('/back/traductions');
```

**Pourquoi rediriger au lieu d'afficher directement la page ?**

Sans redirection, la page s'afficherait en réponse au POST. L'utilisateur appuie sur F5 → le navigateur **renvoie le formulaire**, et le libellé est créé une deuxième fois. Le navigateur affiche d'ailleurs un avertissement (« Confirmer le renvoi du formulaire »), que personne ne lit.

Avec la redirection, F5 recharge une simple page GET : rien n'est réenvoyé.

Ce motif s'appelle **POST/Redirect/GET**. C'est la façon standard de traiter un formulaire, et une question de jury fréquente.

## L'édition en place

Dans la vue, chaque cellule du tableau est **son propre formulaire** :

```php
<form method="post" action="/back/traductions" class="d-flex gap-1">
    <input type="hidden" name="action" value="modifier">
    <input type="hidden" name="id" value="...">
    <input type="text" name="valeur" value="...">
    <button type="submit">✓</button>
</form>
```

On corrige un libellé sans quitter la page ni ouvrir un écran d'édition. Pour un travail de traduction — où on corrige dix mots d'affilée — c'est ce qui change tout.

Les cellules **vides** portent un formulaire `creer` au lieu de `modifier`, avec la clé et la langue déjà remplies en champs cachés. Combler un trou = taper le texte et valider.

## Le français protégé

L'API refuse `DELETE /langues/fr`, et la vue n'affiche pas la croix de suppression pour le français.

**Pourquoi :** le français est la langue de référence, celle sur laquelle on retombe quand une clé manque ailleurs. La supprimer laisserait des trous dans **toutes** les autres langues.

Comme toujours : la vue masque le bouton (confort), **l'API refuse** (sécurité). Masquer seul ne protégerait rien — il suffirait d'envoyer la requête à la main.

## Ce qui reste à améliorer

- **Pas de confirmation** avant de supprimer un libellé isolé (il y en a une pour une langue entière, qui est plus destructeur).
- **Pas de pagination** : à quelques centaines de clés ça passe, au-delà il en faudrait une.
- **Pas de détection des clés orphelines** : si une clé n'est plus utilisée dans aucune vue, rien ne le signale.

Autant les connaître avant qu'on te les fasse remarquer.

## Fichiers liés

- [../../locales/LISEZ-MOI.md](../../locales/LISEZ-MOI.md) — pourquoi base **et** fichiers JSON
- [../../services/Traductions.php.md](../../Services/Traductions.php.md) — le code des deux synchronisations
- [../../views/back/traductions.php](../../views/back/traductions.php) — la vue
- [../../../../api-go/app/traductions.go](../../../../api-go/app/traductions.go) — les 8 routes de l'API
