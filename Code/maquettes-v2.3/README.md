# Maquettes V2.3 — remplacée par la V2.4

> ⏱️ **Lecture : ~5 min** · 637 mots

> ⚠️ **VERSION REMPLACÉE.** Le parti pris visuel de la V2.3 a été retenu, mais un audit a
> montré que **7 domaines de l'API n'avaient aucun écran** (adhésions, bénéficiaires,
> utilisateurs, emplacements, liste des tournées, catalogue, campagnes).
>
> C'est la **[V2.4](../maquettes-v2.4/)** qui sera mise en production : même design,
> 26 écrans au lieu de 14, couverture fonctionnelle complète.
>
> Ce dossier est conservé comme trace de l'itération.

**Ouvre `sommaire.html`** (double-clic, ni Docker ni PHP). `index.html` est l'accueil public du site.

14 écrans, comme la V2.2. Les autres versions restent dans [`../maquettes/`](../maquettes/), [`../maquettes-v2/`](../maquettes-v2/) et [`../maquettes-v2.2/`](../maquettes-v2.2/).

## Le parti pris : deux densités opposées

La V2.2 traitait les deux espaces de la même façon. La V2.3 part du constat qu'ils ne servent pas du tout au même usage :

**Le back-office est dense.** Barre latérale **sombre**, tableaux compacts (`table-sm`), onglets de sous-navigation. C'est un outil utilisé toute la journée par le personnel : on veut voir un maximum de lignes sans faire défiler, et distinguer nettement la navigation du contenu.

**Le front-office est aéré.** Beaucoup d'espace, typographie large, listes séparées par de simples filets plutôt que par des cartes. C'est une vitrine consultée quelques minutes : on veut du confort de lecture, pas de la densité.

C'est un argument qui se défend bien à l'oral : **la densité d'une interface doit suivre le temps qu'on y passe**, pas une préférence esthétique.

## Ce qui change concrètement par rapport à la V2.2

| | V2.2 | V2.3 |
|---|---|---|
| Barre latérale | claire, bordée | **sombre** (`data-bs-theme="dark"`) |
| Tableaux | normaux | **compacts** (`table-sm`) |
| Filtres par statut | menus déroulants | **onglets** sous le titre |
| Fond du contenu | gris | gris, mais blocs sans ombre |
| Boutons d'action | bleus parmi d'autres bleus | bleus **seuls** de leur couleur |
| Front-office | cartes bordées | filets et espace |

### Le bleu rendu aux actions

En V2.2, la navigation était bleue **et** les boutons d'action aussi : les actions se noyaient dans l'interface. Ici la navigation est sombre, donc **le bleu ne sert plus qu'aux boutons** — ils ressortent immédiatement.

C'est le prolongement du principe de la V2.2 (une couleur = un sens), poussé un cran plus loin.

### Les onglets à la place des menus déroulants

Filtrer les bénévoles par statut demandait d'ouvrir un menu, choisir, valider. Maintenant les statuts sont des onglets visibles en permanence : **un clic**, et on voit d'emblée combien il y a d'éléments dans chaque catégorie grâce aux compteurs.

## Toujours zéro CSS écrit à la main

La barre latérale sombre ne coûte **aucune ligne de CSS** : c'est l'attribut `data-bs-theme="dark"` de Bootstrap 5.3, qui bascule toutes les classes à l'intérieur en version sombre.

Les seuls `style="..."` inline portent sur des valeurs que Bootstrap ne propose pas en classe : largeur de colonne (`225px`), hauteur de barre (`4px`), tailles de police réduites.

## Comparatif des quatre versions

| Version | Pages | Vert | Bleu | Signe distinctif |
|---|---|---|---|---|
| V1 | 12 | 72 | 12 | Bootstrap standard, barre horizontale |
| V2 | 12 | 105 | 2 | plat, bordure à gauche, barre latérale claire |
| V2.2 | 14 | 39 | 199 | couleurs porteuses de sens, tri/pagination/modales |
| **V2.3** | **14** | **35** | **113** | **densités opposées, barre latérale sombre** |

## Le lien avec l'API

Chaque maquette indique en haut les routes qu'elle consomme. **Toutes existent et sont testées (66/66)**, à deux exceptions près : le **tri** des colonnes et la **pagination** des listes, qui demanderaient d'ajouter des paramètres `tri` et `page` aux routes de listing.

⚠️ Ne déplace pas ce dossier hors de `Code/` : le chemin `../front-php/...` vers Bootstrap casserait.
