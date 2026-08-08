# Les deux gabarits — `layout_back.php` et `layout_front.php`

> ⏱️ **Lecture : ~10 min** · 866 mots, 17 lignes de code

> **À lire après** [Vue.php.md](../Vue.php.md), qui explique comment le bon gabarit est choisi.
> Remplace l'ancien `layout.php` unique, supprimé lors du portage des maquettes V2.4.

## Pourquoi deux gabarits et non un seul

Le front-office et le back-office ne servent pas au même usage, et leur mise en page le reflète :

| | Front-office | Back-office |
|---|---|---|
| Navigation | barre horizontale claire | **barre latérale sombre**, toujours visible |
| Densité | aérée, typographie large | dense, tableaux compacts |
| Titre | dessiné par chaque page | dessiné par le gabarit |
| Usage réel | quelques minutes, en visiteur | toute la journée, en outil de travail |

**L'argument à savoir défendre** : la densité d'une interface doit suivre le temps qu'on y passe. Quelqu'un qui passe des stocks aux tournées quarante fois par jour n'a pas à remonter en haut de page à chaque fois — d'où le menu latéral permanent.

Un seul fichier avec un `if/else` aurait été tentant, mais le `if` engloberait deux structures HTML entières : un fichier de 350 lignes dont on ne lit jamais la moitié, avec des `<?php endif; ?>` à 200 lignes de leur `<?php if:`. Exactement le fichier qu'on n'arrive plus à expliquer.

## Ce que chaque gabarit reçoit

Les deux reçoivent `$titre`, `$contenu`, `$config`, `$chemin` et `$options`. Le gabarit **back** reçoit en plus `$menu` (la description du menu) et `$menuActif` (l'entrée à surligner) — voir [Vue.php.md](../Vue.php.md).

## Les blocs partagés

Trois fichiers dans `app/views/blocs/` évitent la duplication :

| Bloc | Utilisé par | Rôle |
|---|---|---|
| `messages.php` | les **deux** gabarits | les messages à usage unique |
| `menu_back.php` | back | la barre latérale |
| `entete_back.php` | back | fil d'Ariane, titre, actions, onglets |
| `entete_front.php` | front | la barre horizontale publique |

`messages.php` est partagé pour une raison précise : s'il était recopié dans les deux gabarits, corriger un détail d'affichage demanderait de le faire deux fois — et on en oublierait un.

## `min-width: 0` — le piège de flexbox

Dans `layout_back.php` :

```html
<div class="flex-grow-1 d-flex flex-column bg-body" style="min-width:0">
```

Sans `min-width: 0`, un élément flex refuse de rétrécir en dessous de la largeur de son contenu. Un tableau à huit colonnes ferait donc **déborder toute la page** au lieu de défiler dans son propre cadre : la barre latérale serait poussée hors de l'écran.

C'est le piège flexbox le plus fréquent, et il ne se voit qu'avec un tableau large — donc pas sur le tableau de bord, mais sur l'écran des stocks.

## La barre latérale ne contient aucun texte

`blocs/menu_back.php` se contente de **parcourir** `app/config/menu_back.php`. Les libellés y sont des **clés de traduction** :

```php
<?= Langue::t($entree['libelle']) ?>
```

Écrire `Commerçants` en clair dans la configuration casserait le multilingue sur **les 22 écrans du back-office d'un coup** — alors que le sujet exige un site multilingue. C'est le test à faire en premier après toute modification du menu :

```bash
curl -s -b cookies.txt "http://localhost:8080/back?lang=it" | grep -A1 "letter-spacing:.12em"
```

Vérifié le 2026-08-03 : les cinq sections s'affichent bien en *Panoramica, Rete, Logistica, Attività, Amministrazione*.

## Les pastilles de compteur

```php
$compteur = $compteurs[$entree['cle']] ?? null;
$afficheCompteur = is_int($compteur) && $compteur > 0;
```

La pastille n'apparaît **que** si un compteur est fourni et positif. Afficher « 0 candidature à valider » attirerait l'œil sur une information sans intérêt.

**État actuel : aucun compteur n'est alimenté.** C'est une décision, pas un oubli — les remplir imposerait quatre appels API supplémentaires sur *chaque* page du back-office, et une panne de l'API casserait alors les 22 écrans au lieu d'un seul. La forme est posée ; la donnée arrivera écran par écran.

La couleur (`warning`, `danger`, `info`) est une propriété de l'entrée dans `menu_back.php`, pas une décision du contrôleur : « les adhésions à renouveler » sont toujours un avertissement, quel que soit l'écran affiché.

## L'en-tête du back : décrit, pas dessiné

`entete_back.php` affiche ce que le contrôleur lui **décrit** via `$options` :

```php
Vue::afficher('back/commercants', [...], Langue::t('commercants.titre'), [
    'sous_titre' => count($commercants) . ' ' . Langue::t('commercants.total'),
    'actions'    => [['libelle' => 'Ajouter', 'url' => '/back/commercants/nouveau',
                      'style' => 'primary', 'icone' => 'bi-plus-lg']],
]);
```

⚠️ **`actions` et `onglets` sont des tableaux, jamais du HTML.** Une clé `'actions' => '<a class="btn">…</a>'` serait plus rapide à écrire, mais elle rouvrirait une porte XSS dans le seul projet où toute donnée passe par `Vue::e()`, et disperserait du Bootstrap dans les contrôleurs. Ici le bouton est *décrit* ; le gabarit applique `Vue::e()` sur chaque libellé et reste seul responsable des classes.

## Le lien « Mon espace » du front

```php
$urlEspace = Auth::urlEspace($config);
```

L'adresse dépend du rôle (`/back`, `/mon-espace`, `/mon-espace/benevole`), mais **c'est `Auth` qui le sait**. Sans cette méthode, le gabarit contiendrait un `if/elseif` sur les rôles — de la logique métier dans un fichier dont le seul travail est de mettre en forme.

## Ce qui a été retiré des vues

Les trois vues back existantes dessinaient leur propre `<h1>`. Après la bascule, elles en affichaient **deux** : celui de l'en-tête et le leur. Ces blocs ont été supprimés et l'information déplacée dans `$options`.

Ce nettoyage a servi de **preuve** que le contrat `$options` était utilisable : si un des écrans existants n'y était pas rentré, il valait mieux le savoir avant de porter les 26 suivants.

## Fichiers liés

- [../Vue.php.md](../Vue.php.md) — le choix du gabarit et la résolution de l'entrée de menu
- [../config/menu_back.php](../config/menu_back.php) — la description du menu
- [../middleware/Auth.php.md](../Middleware/Auth.php.md) — `urlEspace` et les gardes de rôle
- [../../../maquettes-v2.4/README.md](../../../maquettes-v2.4/README.md) — les maquettes portées
