# `blocs/entete_back.php` — le bandeau d'un écran de back-office

> Inclus par `layout_back.php`. Tout ce qu'il affiche vient de `$options`.

## Ce qu'il dessine

Fil d'Ariane, titre, sous-titre, boutons d'action, onglets de filtrage. Les vingt écrans du back-office ont **tous** cette même forme — c'est ce qui justifie de la factoriser une fois.

## La séparation `$donnees` / `$options`

```php
Vue::afficher($chemin, $donnees, $titre, $options);
```

- **`$donnees`** = ce que la **vue** affiche (`$benevoles`, `$produits`…). Ces variables passent par `extract()`.
- **`$options`** = ce que le **bandeau** affiche (`fil`, `actions`, `onglets`…).

Pourquoi ne pas tout mettre dans `$donnees` : `extract()` transforme chaque clé en variable. Une clé générique comme `titre` ou `actions` entrerait tôt ou tard en collision avec une variable métier, et l'écran afficherait n'importe quoi sans la moindre erreur PHP.

## Pourquoi `actions` et `onglets` sont des tableaux, pas du HTML

C'est le point important du fichier.

Un contrôleur décrit ce qu'il veut :

```php
'actions' => [[
    'libelle' => Langue::t('commun.retour'),
    'url' => '/back/tournees',
    'style' => 'light',
    'icone' => 'bi-arrow-left',
]]
```

Ce fichier décide **comment** le dessiner.

Si un contrôleur pouvait envoyer `"<a class='btn'>…</a>"` directement, deux choses casseraient :

1. **La sécurité.** On rouvrirait une porte aux failles XSS dans le seul projet où toute donnée passe par `Vue::e()`.
2. **La cohérence.** Le Bootstrap se disperserait dans les vingt contrôleurs, et changer l'allure des boutons demanderait de les rouvrir tous.

## Le fil d'Ariane par défaut

```php
$fil = $options['fil'] ?? [['libelle' => $titrePage, 'url' => null]];
```

Par défaut : `Back-office / <titre de la page>`. Un écran de détail fournit un fil plus profond — `Commerçants > Boulangerie Martin` — pour donner le chemin du retour.

Un défaut sensé évite de configurer les quinze écrans de liste, qui n'ont rien de particulier à dire.

## Fichiers liés

- [../../Vue.php.md](../../Vue.php.md) — la construction de `$options`
- [../layout_back.php.md](../layout_back.php.md) — le gabarit qui l'inclut
- [menu_back.php.md](menu_back.php.md) — l'autre moitié de l'habillage
