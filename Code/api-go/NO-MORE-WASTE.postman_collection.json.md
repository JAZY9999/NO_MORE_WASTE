# `NO-MORE-WASTE.postman_collection.json` — la documentation des 63 endpoints

> ⏱️ **Lecture : ~10 min** · 989 mots, 9 lignes de code

> **Phase** : 10 (consolidation de l'API).
> Ce fichier n'est pas du code : c'est **la documentation de l'API**, sous une forme qu'on peut exécuter.

## À quoi ça sert

Le sujet demande une API. Une API sans documentation est inutilisable par quelqu'un d'autre : il faut lire le code source pour deviner quelles routes existent, quels champs envoyer, et qui a le droit de les appeler.

Ce fichier répond à ça. C'est une **collection Postman** : un fichier qui décrit chaque requête (méthode, URL, en-têtes, corps d'exemple) et qu'on peut **rejouer d'un clic**.

Deux usages :
1. **Documentation** : la liste complète et à jour des 63 routes, rangées par thème.
2. **Test** : on clique sur une requête, elle part réellement vers l'API.

## Comment s'en servir

1. Ouvrir Postman → *Import* → sélectionner ce fichier.
2. Vérifier la variable `base_url` (onglet *Variables* de la collection). Par défaut : `http://localhost:8080/api`.
3. Lancer **`00 - Sante & Auth > Connexion`**. Le token est récupéré et stocké **automatiquement** (voir plus bas).
4. Toutes les autres requêtes sont prêtes à partir.

### Les deux variables

Une variable Postman s'écrit `{{nom}}` et se remplace au moment de l'envoi.

| Variable | Rôle |
|---|---|
| `base_url` | L'adresse de l'API. Si tu déploies sur un vrai serveur (Phase 11), tu changes **cette seule ligne** et les 66 requêtes suivent. |
| `token` | Le JWT de la session en cours. |

C'est exactement pour ça qu'on utilise des variables : sans elles, changer d'adresse voudrait dire éditer 66 requêtes à la main.

### Le token qui se remplit tout seul

La requête de connexion porte un petit script, exécuté **après** la réponse :

```javascript
if (pm.response.code === 200) {
    const donnees = pm.response.json();
    pm.collectionVariables.set('token', donnees.token);
}
```

Traduction : « si la connexion a réussi, prends le champ `token` de la réponse et range-le dans la variable `token` de la collection ».

Sans ce script, il faudrait copier-coller le JWT à la main dans chaque requête après chaque connexion.

### ⚠️ Le token n'a PAS de préfixe `Bearer`

Dans la plupart des tutoriels, on envoie `Authorization: Bearer eyJhbGc...`.

Ici c'est `Authorization: eyJhbGc...`, **sans le mot `Bearer`**. C'est la version simplifiée enseignée dans le cours ESGI, et le code de `utils/jwt.go` lit le header tel quel. Si tu ajoutes `Bearer `, l'authentification échoue.

C'est le genre de détail qu'on peut te demander de justifier : ce n'est pas un oubli, c'est un choix assumé de cohérence avec le cours.

## L'organisation en 14 dossiers

Les dossiers suivent **l'ordre des dépendances réelles**, pas l'ordre des phases du projet. On peut donc lancer la collection du haut vers le bas sur une base vide, et tout s'enchaîne.

| # | Dossier | Pourquoi à cette place |
|---|---|---|
| 00 | Santé & Auth | il faut un token avant tout le reste |
| 01 | Commerçants & adhésions | |
| 02 | Rappels automatiques | a besoin d'une adhésion existante |
| 03 | Campagnes ciblées 🟦 | bonus, hors sujet |
| 04 | Stocks : emplacements | un produit a besoin d'un emplacement |
| 05 | Stocks : produits & code-barre | |
| 06 | **Bénévoles** | **avant les collectes** : une collecte référence un bénévole |
| 07 | **Compétences** | **avant les services** : l'affectation exige une compétence |
| 08 | Collectes | |
| 09 | Services & créneaux | |
| 10 | Planning bénévoles | |
| 11 | Bénéficiaires | |
| 12 | Tournées | a besoin d'un bénévole **validé** |
| 13 | Livraisons & PDF | a besoin d'une tournée et de produits |

Les deux inversions en gras sont volontaires. C'est la découverte du test de rejeu : mettre les collectes avant les bénévoles produisait une erreur, parce que le bénévole chauffeur n'existait pas encore.

### L'ordre compte aussi *à l'intérieur* d'un dossier

Le dossier **Bénévoles** est numéroté `1)` à `4)` :

1. Ajouter un document
2. Lister les documents
3. Valider le document
4. **Valider le bénévole**

Si on saute l'étape 3, l'étape 4 échoue avec `« Impossible de valider : tous les documents du benevole doivent d'abord etre valides »`. Ce n'est pas un bug : c'est **la règle métier de la Phase 6**, celle que le sujet demande explicitement. La collection est construite pour la rendre visible.

## Les rôles sont documentés

Chaque requête indique dans sa description qui a le droit de l'appeler :

- **Public** — aucun token (la candidature bénévole, la consultation des services, l'inscription)
- **Connecté** — n'importe quel rôle
- **staff_back ou admin_back** — le back-office
- **admin_back uniquement** — le déclenchement des jobs, l'envoi des campagnes

## Vérifier que la doc n'est pas périmée

Le danger d'une documentation, c'est qu'elle mente : on modifie le code et on oublie de la mettre à jour.

D'où le script [../tests/tester-tous-les-endpoints.py](../tests/tester-tous-les-endpoints.py) : il lit ce fichier et **rejoue les 66 requêtes** contre l'API réelle.

```bash
python tests/tester-tous-les-endpoints.py
```

```
TOTAL : 66 requetes | 66 OK | 0 en echec
```

Si un exemple devient faux, le script le signale. La documentation est donc **vérifiable**, pas seulement déclarative. Voir son [.md](../tests/tester-tous-les-endpoints.py.md).

## Deux points à savoir justifier

**`montant_cotisation` est une chaîne, pas un nombre** (`"150.00"` et non `150.00`). La colonne est de type `NUMERIC` en PostgreSQL, et le pilote la lit comme du texte. On l'a laissée ainsi volontairement : pour de l'argent, le texte évite les erreurs d'arrondi des nombres à virgule flottante (le fameux `0.1 + 0.2 = 0.30000000000000004`).

**`POST /collectes/{id}/produits` crée un produit, il n'en rattache pas un existant.** C'est le scan sur le terrain : le bénévole scanne un article chez le commerçant, le produit est créé *et* rattaché à la collecte en une seule opération. Il faut donc envoyer un produit complet (`code_barre`, `libelle`…), pas un `produit_id`.

## Fichiers liés

- [../tests/tester-tous-les-endpoints.py.md](../tests/tester-tous-les-endpoints.py.md) — le script qui vérifie cette collection
- [app.go.md](app.go.md) — la déclaration des 63 routes côté Go
- [utils/guard.go.md](utils/guard.go.md) — comment les rôles sont contrôlés
