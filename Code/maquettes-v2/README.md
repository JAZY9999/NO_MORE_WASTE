# Maquettes V2 — autre parti pris visuel

> ⏱️ **Lecture : ~5 min** · 599 mots

> ⚠️ **VERSION ÉCARTÉE.** C'est la **V2.4** qui a été retenue (voir [`../maquettes-v2.4/`](../maquettes-v2.4/)). Ce dossier est conservé comme trace des itérations de conception.

Deuxième proposition de design pour les mêmes 11 écrans. **Ouvre `index.html`** (double-clic, ni Docker ni PHP).

La V1 reste dans [`../maquettes/`](../maquettes/) — les deux coexistent pour pouvoir comparer avant de trancher.

## Ce qui change par rapport à la V1

| | V1 | V2 |
|---|---|---|
| Menu du back-office | barre horizontale en haut | **barre latérale** fixe à gauche |
| Relief | ombres portées (`shadow-sm`) | **plat** : bordures marquées, aucune ombre |
| Coins | arrondis | **droits** |
| Accent de couleur | fond coloré des blocs | **bordure épaisse à gauche** |
| Badges | rectangulaires | **pastilles** (`rounded-pill`) |
| Repère de navigation | — | **fil d'Ariane** dans le back-office |
| Tableau de bord | 6 cartes de modules | **4 indicateurs chiffrés** + activité récente + « à traiter » |

### Pourquoi la barre latérale

C'est la disposition des vrais outils de gestion, et pour une raison précise : le menu **reste visible en permanence**. Un membre du personnel qui passe des stocks aux tournées quarante fois par jour n'a pas à remonter en haut de page à chaque fois.

C'est aussi ce qui distingue le plus nettement les deux espaces : le front-office public garde une barre horizontale classique, le back-office a sa colonne. Impossible de confondre pendant une démonstration — c'est la séparation demandée par le sujet, rendue visible.

### Pourquoi la bordure à gauche plutôt qu'un fond coloré

Elle **porte de l'information** au lieu de décorer : vert = terminé, orange = à traiter, gris = en attente. On repère l'état d'une ligne sans lire son badge.

Sur la page de détail d'un bénévole, c'est ce qui fait ressortir immédiatement le document qui bloque la validation.

## Toujours du Bootstrap, rien d'écrit à la main

Malgré un rendu très différent, il n'y a **aucune feuille de style personnelle**. Tout passe par les classes Bootstrap : `border-start border-4`, `rounded-0`, `rounded-pill`, `data-bs-theme="dark"` sur la barre latérale, `bg-body-tertiary`.

Les seuls `style="..."` inline concernent des valeurs que Bootstrap ne propose pas en classe : une largeur de colonne (`230px`), une hauteur de barre de progression (`4px`), une taille de police réduite pour les intitulés en majuscules.

C'est un point à savoir défendre : **on change complètement l'apparence sans sortir du framework**. Bootstrap n'impose pas un look, il fournit des briques.

## Les écrans

**Front-office** : `front-services.html`, `front-service-detail.html`, `front-candidature.html`

**Back-office** : `back-tableau-de-bord.html`, `back-commercants.html`, `back-benevoles.html`, `back-benevole-detail.html`, `back-collectes.html`, `back-stocks.html`, `back-tournees.html`, `back-services.html`

Comme en V1, chaque page porte en haut un bandeau indiquant les **routes d'API qu'elle consomme** — toutes existent déjà et sont testées.

## Trois écrans qui ont vraiment changé

**`back-tableau-de-bord.html`** — ce n'est plus un simple menu en cartes. Quatre indicateurs chiffrés en haut, l'activité récente à gauche, une liste « à traiter » à droite avec des compteurs cliquables. C'est ce qu'on attend d'un tableau de bord : savoir en un coup d'œil ce qui demande une action.

**`back-benevole-detail.html`** — le bandeau de blocage passe tout en haut, avec une barre de progression. La règle du sujet (validation impossible tant qu'un document manque) devient la première chose qu'on lit.

**`front-candidature.html`** — le formulaire est accompagné à droite des trois étapes numérotées qui suivent l'envoi. Le candidat sait ce qui l'attend, au lieu de découvrir après coup qu'il devra fournir des justificatifs.

⚠️ Ne déplace pas ce dossier hors de `Code/` : le chemin `../front-php/...` vers Bootstrap casserait.
