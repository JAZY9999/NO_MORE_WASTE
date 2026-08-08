# `app/monEspace.go` — l'espace client du front-office

> ⏱️ **Lecture : ~10 min** · 1011 mots, 27 lignes de code

> **Le fichier le plus sensible du projet côté sécurité.** Si tu ne dois retenir qu'une chose de ce document : lis la section « La règle qui gouverne tout le fichier ».

## Pourquoi ce fichier existe

Le sujet demande *« à la fois un back-office (utilisé par NO MORE WASTE) et un **front office (utilisé par les clients** de NO MORE WASTE) »*.

Jusqu'ici, toutes les routes métier étaient réservées au personnel (`RequireRole("admin_back", "staff_back")`). Résultat : un commerçant ou un bénévole qui se connectait ne voyait **rien de plus qu'un visiteur anonyme**. Le front-office était une vitrine, pas un espace client.

Ce fichier ajoute cinq routes qui permettent à un client de voir **ses** données.

## La règle qui gouverne tout le fichier

**Aucune de ces routes n'accepte d'identifiant venant du client.**

On part toujours du jeton :

```
jeton  →  email  →  compte  →  sa fiche  →  ses données
```

### Ce qui se passerait si on faisait autrement

Imaginons cette route, qui paraît naturelle :

```
GET /mon-espace/collectes?commercant_id=7
```

N'importe quel adhérent connecté pourrait alors essayer `commercant_id=8`, puis `9`, `10`… et lire les collectes de tous les autres commerçants. Aucun message d'erreur, aucune trace suspecte : la requête est parfaitement valide du point de vue du serveur.

C'est une faille classique, appelée **référence directe non sécurisée** (*Insecure Direct Object Reference*). Elle figure régulièrement dans le top 10 des failles web les plus répandues, précisément parce qu'elle est facile à introduire sans y penser.

**La protection ici est structurelle** : le client n'a aucun moyen de désigner une autre fiche que la sienne, puisqu'il ne désigne rien du tout.

## Les deux fonctions d'aide

```go
func monCommercant(w, r, email) *models.Commercant
func monBenevole(w, r, email)   *models.Benevole
```

Elles font le chemin complet jeton → fiche, et retournent `nil` si une réponse d'erreur a déjà été envoyée. Les handlers commencent donc tous par :

```go
commercant := monCommercant(w, r, email)
if commercant == nil {
    return
}
```

Sans elles, ces six lignes seraient recopiées dans chaque handler — six occasions d'oublier une vérification.

### Pourquoi 404 et non 403 quand la fiche manque

```go
http.Error(w, "Aucune fiche commercant n'est rattachee a votre compte", http.StatusNotFound)
```

Un adhérent inscrit mais pas encore enregistré comme commerçant a un compte **parfaitement légitime**. Ce n'est pas un problème de droits (403 = « tu n'as pas le droit »), c'est simplement qu'il n'y a rien à montrer (404 = « ça n'existe pas »).

La distinction compte pour le front : sur un 403 il affiche « accès refusé », sur un 404 il peut afficher « votre compte n'est pas encore rattaché à un commerçant, contactez l'association ».

## Les cinq routes

| Route | Rôle exigé | Ce qu'elle renvoie |
|---|---|---|
| `GET /mon-espace/commercant` | `adherent` | Sa fiche **et** ses adhésions |
| `GET /mon-espace/collectes` | `adherent` | Ses collectes |
| `POST /mon-espace/collectes` | `adherent` | Crée une demande de collecte |
| `GET /mon-espace/benevole` | `benevole` | Sa fiche, ses documents, ses compétences |
| `GET /mon-espace/planning` | `benevole` | Ses créneaux à venir |

### Pourquoi renvoyer la fiche ET les adhésions ensemble

L'adhésion est l'information qui **conditionne tout le reste** : un adhérent expiré ne peut plus être collecté. Elle sera donc affichée en premier sur l'écran.

Les renvoyer dans la même réponse évite au front un second appel pour construire sa page principale.

### `DemanderCollecte` — ce que le client ne décide pas

```go
collecte := models.Collecte{
    CommercantId: &commercant.Id,   // sa fiche, pas celle qu'il demande
    DatePrevue:   &demande.DatePrevue,
    Statut:       "demandee",       // en dur
}
```

On ne lit **que** `date_prevue` du corps de la requête. Tout le reste est ignoré, même si le client l'envoie :

- **le statut** est forcé à `demandee` — un commerçant ne déclare pas lui-même sa collecte « réalisée » ;
- **le bénévole** reste vide — c'est l'association qui affecte ;
- **le commerçant** vient de sa fiche — il ne peut pas demander une collecte au nom d'un autre.

C'est le principe à retenir : **ne jamais lire du corps de la requête ce que le client n'a pas à décider**. Décoder tout l'objet `Collecte` d'un coup aurait laissé passer les trois.

## `MonPlanning` — la réutilisation qui évite une dette

```go
lignes, err := db.ListPlanning(nil, &benevole.Id)
```

Le planning quotidien (envoyé par email) et le planning personnel du bénévole affichent les mêmes informations : une requête à **trois tables jointes** (créneaux + bénévoles + services).

Plutôt que d'en écrire une seconde, `ListPlanningDuJour` a été généralisée en `ListPlanning(date, benevoleId)` avec deux filtres facultatifs — et l'ancienne fonction l'appelle désormais. Aucun appelant existant n'a été modifié.

Si la requête avait été dupliquée, le jour où le modèle change il faudrait corriger deux endroits — et en oublier un.

Le `nil` en premier argument signifie « pas de date précise » : la fonction renvoie alors les créneaux **à venir** (`date_creneau >= CURRENT_DATE`). Un bénévole n'a pas besoin de revoir ses créneaux passés.

## Le prérequis en base : la contrainte `UNIQUE`

Tout ce fichier repose sur `commercants.utilisateur_id` et `benevoles.utilisateur_id`.

Ces colonnes existaient déjà, mais **sans contrainte d'unicité**. Un test rejoué deux fois l'a révélé : deux fiches commerçant se sont retrouvées rattachées au même compte, et `GetCommercantByUtilisateurId` renvoyait l'une ou l'autre **selon l'humeur de la base**.

Le schéma porte maintenant :

```sql
utilisateur_id BIGINT UNIQUE REFERENCES utilisateurs(id),
```

`NULL` reste autorisé plusieurs fois — PostgreSQL ne considère pas deux `NULL` comme égaux — donc une fiche peut exister sans compte associé. C'est le cas d'un commerçant enregistré par le personnel avant que le gérant crée son compte.

## Comment le vérifier soi-même

Un script dédié monte le contexte complet et vérifie l'isolation :

```bash
docker compose up -d
python tests/tester-espace-client.py
```

Il crée un compte adhérent lié à une fiche commerçant, un compte bénévole lié à une fiche bénévole, puis vérifie **17 points** dont les plus importants :

| Vérification | Attendu |
|---|---|
| Un bénévole accède à l'espace commerçant | **403** |
| Un commerçant accède à l'espace bénévole | **403** |
| Le personnel accède à l'espace client | **403** (il a le back-office) |
| Sans jeton | **401** |
| Un adhérent crée un compte administrateur | **403** |

Le dernier est le plus important : sans lui, n'importe quel adhérent pourrait **se promouvoir administrateur**.

## Fichiers liés

- [utilisateurs.go.md](utilisateurs.go.md) — la création de comptes, écrite en même temps
- [../utils/guard.go.md](../utils/guard.go.md) — `RequireRole`, qui fournit l'email du jeton
- [../db/servicesRepository.go.md](../db/servicesRepository.go.md) — `ListPlanning` et sa requête à trois jointures
- [../../tests/tester-espace-client.py](../../tests/tester-espace-client.py) — le script de vérification
