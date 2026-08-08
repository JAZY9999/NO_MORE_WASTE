# `tester-espace-client.py` — l'espace client et l'isolation des données

> ⏱️ **Lecture : ~10 min** · 850 mots

> **Tag** : 🟦 **AJOUT PERSO** — comme `tester-tous-les-endpoints.py`, le sujet ne demande pas de tests automatisés.

## Pourquoi un script séparé de l'autre

`tester-tous-les-endpoints.py` rejoue toute la collection Postman avec **un seul compte, administrateur**. Or les routes `/mon-espace/*` exigent d'être `adherent` ou `benevole` — avec un compte admin, elles répondent `403`, ce qui est le comportement **attendu**, mais rend ces routes impossibles à tester dans le parcours principal.

Ce script monte donc un contexte à part : un compte adhérent **rattaché à une fiche commerçant**, un compte bénévole **rattaché à une fiche bénévole**, puis vérifie ce qui compte vraiment ici — pas que les routes répondent, mais qu'**elles ne renvoient jamais les données de quelqu'un d'autre**.

## Comment l'utiliser

```bash
docker compose up -d
python tests/tester-espace-client.py
```

Contrairement à l'autre script, celui-ci **ne vide pas** la base : il réutilise ou complète ce qui existe déjà (voir plus bas), pour rester rejouable sans perturber les données de démonstration.

Si l'API ne tourne pas sur le port `8080` (voir [`tester-tous-les-endpoints.py.md`](tester-tous-les-endpoints.py.md) pour le cas rencontré en production) :

```bash
NMW_BASE_URL=http://localhost:8000 python tests/tester-espace-client.py
```

## Rejouable sans créer de doublons

C'est le point le plus délicat du script, et il revient trois fois sous des formes légèrement différentes.

### Les comptes : `201` ou `409` conviennent tous les deux

```python
if code not in (201, 409):
    print("Creation du compte %s impossible (code %s)" % (email, code))
    sys.exit(1)
```

Au premier lancement, les comptes n'existent pas → `201`. Aux suivants, ils existent déjà → `409`. Les deux sont des **succès** du point de vue du script : ce qui compte, c'est qu'à la fin le compte existe, peu importe qu'il vienne d'être créé ou qu'il soit déjà là.

### La fiche commerçant : chercher avant de créer

```python
existant = sql("SELECT id FROM commercants WHERE raison_sociale='...' LIMIT 1;")
if existant:
    id_commercant = int(existant)
else:
    # ... créer
```

Rien n'empêche en base deux commerçants du même nom. Sans cette vérification, relancer le script dix fois créerait dix fiches identiques, orphelines à neuf reprises.

### La fiche bénévole : encore plus strict

```python
# Meme precaution que pour le commercant : la candidature publique ne verifie
# pas l'unicite de l'email, donc relancer le script creerait une deuxieme
# fiche. On rattacherait alors deux fiches au meme compte -- ce que la
# contrainte UNIQUE de la base refuse, a juste titre.
```

Ici, une deuxième fiche ne serait pas juste inutile : elle ferait **échouer** le script au moment du rattachement (`UPDATE ... utilisateur_id = ...`), puisque `utilisateur_id` est `UNIQUE`. Cette contrainte, ajoutée pendant le projet après un test qui avait justement trouvé deux commerçants rattachés au même compte, protège aussi ce script contre lui-même.

## Le test qui compte vraiment : ce n'est pas *que* ça répond 200

```python
if code == 200:
    donnees = json.loads(contenu)
    verifier("la fiche retournee est la sienne", id_commercant,
             donnees["commercant"]["id"])
```

N'importe quelle route mal câblée pourrait répondre `200` avec les données de **quelqu'un d'autre** — le code HTTP seul ne le détecterait jamais. Ce script vérifie explicitement que l'identifiant renvoyé est bien celui du compte qui a posé la question. C'est la vraie promesse des routes `/mon-espace/*` : jamais d'identifiant fourni par le client, tout part du jeton.

## « Agir en son nom propre, et pas au nom d'un autre »

Cette section (ajoutée en vague 3) couvre une faille réelle, trouvée en portant l'écran d'inscription à un créneau — pas en écrivant ce test après coup :

```python
code, _ = appeler("POST", "/creneaux/%d/inscriptions" % id_creneau,
                  {"commercant_id": id_autre_commercant}, jeton_commercant)
verifier("un adherent peut s'inscrire a un creneau", 201, code)

inscrit = sql("SELECT commercant_id FROM inscriptions_service ...")
verifier("l'inscription est faite en SON nom, pas celui du tiers",
         str(id_commercant), str(inscrit))
```

Avant correction, la requête réussissait (`201`) **et** inscrivait réellement la boutique visée dans le corps — jamais celle du compte connecté. Le test ne se contente donc pas de vérifier le code HTTP : il relit la ligne créée en base pour confirmer **qui** a été inscrit.

La même logique protège la candidature bénévole, route publique celle-là :

```python
code, contenu = appeler("POST", "/benevoles/candidature/",
                        {"nom": "Forge", "prenom": "Corps", "utilisateur_id": 1})
...
verifier("le compte vise dans le corps est ignore", "0", str(rattache))
sql("DELETE FROM benevoles WHERE id=%d;" % id_forge)
```

Une candidature anonyme forgeant un `utilisateur_id` doit être acceptée (la candidature elle-même est légitime), mais ce champ précis doit être ignoré — sans quoi n'importe qui pourrait accrocher une fiche bénévole au compte d'un inconnu. La fiche de test créée pour l'occasion est supprimée à la fin : ce test-là, contrairement aux comptes et fiches principaux, n'a aucune raison de persister d'un lancement à l'autre.

## Pourquoi certaines vérifications sont sautées silencieusement

```python
if id_creneau and id_autre_commercant:
    ...
```

Si aucun créneau n'est `ouvert` en base au moment du test (par exemple sur une base fraîchement réinitialisée par l'autre script), ces trois vérifications ne s'exécutent pas — plutôt que de faire échouer tout le script sur une donnée de contexte manquante qui n'a rien à voir avec l'espace client lui-même. C'est un compromis : ça peut masquer une régression le jour où aucun créneau n'existe, mais évite un faux échec bruyant le reste du temps.

## Comment le vérifier soi-même

```bash
python tests/tester-espace-client.py
# -> TOTAL : 23 verifications | 23 OK | 0 en echec

# rejouable immediatement, sans reinitialiser quoi que ce soit
python tests/tester-espace-client.py
# -> toujours 23/23
```

## Fichiers liés

- [tester-tous-les-endpoints.py.md](tester-tous-les-endpoints.py.md) — l'autre suite, sur un compte unique
- [CHECKLIST-TEST-MANUEL-API.md](CHECKLIST-TEST-MANUEL-API.md) — section 13, pour explorer l'espace client à la main
- [../front-php/app/Controllers/Front/ServicesPublicsController.php.md](../front-php/app/Controllers/Front/ServicesPublicsController.php.md) — l'écran où la faille d'inscription a été trouvée
- [../api-go/app/services.go.md](../api-go/app/services.go.md) — la correction côté API
