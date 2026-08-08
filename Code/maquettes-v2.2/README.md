# Maquettes V2.2

> ⏱️ **Lecture : ~5 min** · 601 mots

> ⚠️ **VERSION ÉCARTÉE.** C'est la **V2.4** qui a été retenue (voir [`../maquettes-v2.4/`](../maquettes-v2.4/)). Ce dossier est conservé comme trace des itérations de conception.

**Ouvre `sommaire.html`** (double-clic, ni Docker ni PHP). `index.html` est l'accueil public du site, pas le sommaire.

14 écrans. Les versions précédentes restent dans [`../maquettes/`](../maquettes/) (V1) et [`../maquettes-v2/`](../maquettes-v2/) (V2).

## Le changement principal : chaque couleur a un sens unique

En V2, le vert servait **à la fois** d'accent de marque **et** de signal « validé ». Résultat : il ne voulait plus rien dire — un bouton vert pouvait aussi bien être une action neutre qu'un état positif.

En V2.2, une couleur = une signification :

| Couleur | Sens | Où |
|---|---|---|
| **Bleu** (`primary`) | marque, navigation, action principale | logo, menu actif, boutons d'action |
| **Vert** (`success`) | **uniquement** un état positif | validé, livré, en stock |
| **Orange** (`warning`) | à traiter, en attente | candidatures, DLC proche |
| **Rouge** (`danger`) | problème, blocage | périmé, expiré, refus |
| **Gris** (`secondary`) | neutre, inactif | brouillons, statuts par défaut |

C'est vérifiable dans le code : le vert est passé de **105 à 39 occurrences**, le bleu de 2 à 199.

**À savoir dire à l'oral** : « chaque couleur porte une information ; je ne l'utilise jamais pour décorer ». C'est un principe de conception qui se défend, contrairement à un choix esthétique.

## Ce qui a été poussé plus loin

**La barre latérale** est structurée en sections (Pilotage, Réseau, Logistique, Activités) avec des compteurs. À sept entrées, un menu à plat devient une liste qu'on relit à chaque fois ; groupé, on va droit à la bonne zone.

**Une barre supérieure** apporte le fil d'Ariane, la recherche globale et les notifications — on sait toujours où on est.

**Les tableaux** ont maintenant ce qu'on attend d'une vraie liste : colonnes triables, pagination, menu d'actions en bout de ligne, pastilles d'initiales.

**Deux modales de confirmation** : refuser une candidature (avec motif), et clôturer une livraison (avec le scan des articles remis). Ces actions ne sont pas anodines — elles envoient un email ou modifient le stock.

**Un graphique en barres** sur le tableau de bord, fait avec de simples `div` Bootstrap. Aucune librairie : cohérent avec le reste du projet, et explicable en trois lignes.

**Deux écrans en plus** : l'accueil public et la page de connexion.

## Les écrans

**Front-office** : `index.html` (accueil), `front-services.html`, `front-service-detail.html`, `front-candidature.html`, `front-connexion.html`

**Back-office** : `back-tableau-de-bord.html`, `back-commercants.html`, `back-benevoles.html`, `back-benevole-detail.html`, `back-collectes.html`, `back-stocks.html`, `back-tournees.html`, `back-services.html`

## Toujours zéro CSS écrit à la main

Malgré un rendu très différent des versions précédentes, il n'y a **aucune feuille de style personnelle**. Tout passe par les classes Bootstrap.

Les seuls `style="..."` inline portent sur des valeurs que Bootstrap ne propose pas en classe : une largeur de colonne (`238px`), une hauteur de barre (`4px`), une taille de police réduite pour les intitulés en majuscules.

C'est un point défendable : **on change complètement l'apparence sans sortir du framework**. Bootstrap fournit des briques, pas un look imposé.

## Le lien avec l'API

Chaque maquette indique en haut les routes qu'elle consomme. **Toutes existent déjà et sont testées (66/66)**.

Deux exceptions à connaître, qui demanderaient d'enrichir l'API : le **tri** des colonnes et la **pagination** des listes (paramètres `tri` et `page` à ajouter aux routes de listing). C'est une évolution simple, mais elle n'est pas faite — autant le savoir avant qu'on te pose la question.

⚠️ Ne déplace pas ce dossier hors de `Code/` : le chemin `../front-php/...` vers Bootstrap casserait et les pages s'afficheraient sans style.
