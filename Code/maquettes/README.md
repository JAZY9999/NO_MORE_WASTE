# Maquettes du front NO MORE WASTE

> ⚠️ **VERSION ÉCARTÉE.** C'est la **V2.4** qui a été retenue (voir [`../maquettes-v2.4/`](../maquettes-v2.4/)). Ce dossier est conservé comme trace des itérations de conception.

Les écrans du site en **HTML statique**, pour valider la mise en page et le parcours **avant** d'écrire les vues PHP.

## Comment les ouvrir

Double-clic sur **`index.html`**. C'est tout.

Pas besoin de Docker, ni de PHP, ni de serveur. Ce sont des fichiers HTML ordinaires : ils s'ouvrent dans le navigateur comme n'importe quelle page enregistrée. Pratique pour les consulter hors connexion.

## Pourquoi elles ressemblent exactement au site

Elles chargent **le vrai Bootstrap du projet**, en chemin relatif :

```html
<link rel="stylesheet" href="../front-php/public/assets/bootstrap/bootstrap.min.css">
```

Ce n'est pas une copie : c'est le fichier utilisé par le site. Ce que tu vois dans la maquette est donc exactement ce que donnera la page finale — et si Bootstrap est mis à jour un jour, les maquettes suivent.

⚠️ **Conséquence** : ne déplace pas ce dossier hors de `Code/`, sinon le chemin `../front-php/...` ne pointera plus nulle part et les pages s'afficheront sans aucun style.

## Ce que contiennent les maquettes

| Écran | État du vrai code |
|---|---|
| **Front-office** | |
| `front-services.html` — catalogue des services | à coder |
| `front-service-detail.html` — créneaux + inscription | à coder |
| `front-candidature.html` — candidature bénévole | à coder |
| **Back-office** | |
| `back-tableau-de-bord.html` — les 6 modules actifs | partiel (5 cartes grisées) |
| `back-commercants.html` — liste + filtre | ✅ codé |
| `back-benevoles.html` — liste + filtre par statut | à coder |
| `back-benevole-detail.html` — **validation des documents** | à coder |
| `back-collectes.html` — collectes | à coder |
| `back-stocks.html` — stocks + **recherche code-barre** | à coder |
| `back-tournees.html` — arrêts, clôture, **PDF** | à coder |
| `back-services.html` — créneaux, affectation, planning | à coder |

## Ce qu'elles ne sont pas

**Aucun bouton ne fonctionne.** Les données sont fictives (mais réalistes et cohérentes avec le projet : Boulangerie Martin, Restos du Cœur, Paul Durand…). Il n'y a ni PHP, ni appel d'API, ni base de données.

C'est volontaire : une maquette sert à décider **ce qu'on affiche et comment**, pas à fonctionner.

## Le détail utile pendant le codage

Chaque maquette porte en haut un **bandeau jaune** qui indique la ou les routes d'API qu'elle consomme. Par exemple :

> **Maquette** — `POST /tournee-etapes/{id}/livraison` puis `GET /livraisons/{id}/pdf` — le récapitulatif PDF exigé par le sujet.

C'est le lien direct entre l'écran et le travail déjà fait côté Go. **Toutes ces routes existent et sont testées (66/66)** : il ne reste qu'à écrire le contrôleur et la vue qui les affichent.

## Les deux écrans à regarder en priorité

**`back-benevole-detail.html`** — c'est l'écran le plus important du back-office. Il matérialise la règle métier que le sujet détaille le plus : on ne peut valider un bénévole que si **tous** ses documents sont validés. Le bouton « Valider le bénévole » y est volontairement désactivé, avec l'explication affichée juste au-dessus.

**`back-stocks.html`** — le champ de recherche par code-barre y est mis en avant (grand, en haut, avec le focus automatique), parce que c'est une exigence explicite du sujet et le geste que fera un bénévole des dizaines de fois par jour avec une douchette.

## Et après

Ces maquettes servent de référence pour écrire les vues PHP correspondantes. Le passage de l'une à l'autre est mécanique : on remplace les données en dur par les variables du contrôleur, et on entoure les libellés de `Langue::t()` pour le multilingue.
