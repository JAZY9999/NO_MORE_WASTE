# `app/utilisateurs.go` — la gestion des comptes

> ⏱️ **Lecture : ~10 min** · 635 mots, 28 lignes de code

> Ce fichier comble **le dernier trou connu de l'API**, traîné depuis la Phase 1.

## Le problème qu'il résout

`POST /auth/register/` crée toujours un compte `adherent` — le rôle est écrit en dur :

```go
err = db.CreateUtilisateur(identifiants.Email, string(hashed), "adherent")
```

C'est volontaire pour l'inscription publique : personne ne doit pouvoir choisir son propre rôle en s'inscrivant. Mais il n'existait **aucun autre moyen** de créer un compte. Résultat, fabriquer un compte pour un membre du personnel imposait une requête SQL à la main :

```sql
UPDATE utilisateurs SET role='admin_back' WHERE email='...';
```

Ça veut dire qu'installer l'application sur un serveur neuf demandait d'ouvrir un client PostgreSQL. Inacceptable pour un produit *« packagé pour pouvoir être aisément déployé »*, comme le demande le sujet — et c'était même codé en dur dans le script de test.

## Les deux routes

| Route | Rôle | Effet |
|---|---|---|
| `GET /utilisateurs/` | `admin_back` | Liste des comptes et de leurs rôles |
| `POST /utilisateurs/` | `admin_back` | Crée un compte **avec choix du rôle** |

### Pourquoi `admin_back` seul, et pas `staff_back`

C'est le seul fichier du projet où `staff_back` est exclu.

**Pouvoir créer des comptes, c'est pouvoir se fabriquer un accès.** Un membre du personnel pourrait se créer un second compte `admin_back` et contourner toutes les limites de son propre rôle. Cette capacité ne se délègue pas.

C'est un raisonnement à savoir tenir : les permissions ne se répartissent pas par confiance envers les personnes, mais par **conséquence de ce que l'action permet**.

## La liste blanche des rôles

```go
var rolesAutorises = []string{"admin_back", "staff_back", "adherent", "benevole"}
```

On ne fait **jamais confiance** à la chaîne envoyée par le client. Sans cette liste :

- créer un compte avec le rôle `"super_admin"` réussirait ;
- ce compte serait refusé par **toutes** les gardes (aucune ne connaît ce rôle) ;
- l'utilisateur se retrouverait bloqué partout sans comprendre pourquoi, et l'administrateur non plus.

Le message d'erreur énumère les valeurs acceptées, plutôt que de dire « rôle invalide » sans plus :

```
Role invalide (attendu : admin_back, staff_back, adherent ou benevole)
```

Un message d'erreur utile dit **ce qui est attendu**, pas seulement ce qui est faux.

## Les mêmes règles qu'à l'inscription

Format d'email, longueur du mot de passe, refus des doublons (409), hachage bcrypt : rien n'est allégé sous prétexte que c'est un administrateur qui crée le compte.

**Pourquoi bcrypt et pas un hachage rapide comme SHA-256 ?** Parce que bcrypt est volontairement **lent**. Face à une base volée, un attaquant qui teste des millions de mots de passe à la seconde avec SHA-256 n'en testera que quelques milliers avec bcrypt. La lenteur est ici une fonctionnalité, pas un défaut.

## Le problème du tout premier compte

Une question qui vient naturellement : **si créer un compte administrateur exige d'être administrateur, comment créer le premier ?**

C'est le problème classique de l'*amorçage* (bootstrap), et il n'a pas de solution purement applicative. Le premier compte doit venir d'ailleurs :

- une requête SQL au moment de l'installation (ce que fait aujourd'hui `tests/tester-tous-les-endpoints.py`) ;
- ou une insertion dans `schema.sql` ;
- ou une variable d'environnement lue au premier démarrage.

C'est ce que fait tout logiciel administrable. À traiter avec le **script d'installation** (item 12.1 du sujet) : c'est exactement son rôle.

En attendant, le trou est réduit à **un seul compte** au lieu d'un par membre du personnel.

## Comment le vérifier soi-même

```bash
JETON=...  # se connecter en admin_back

# créer un compte staff
curl -X POST http://localhost:8080/api/utilisateurs/ \
  -H "Authorization: $JETON" -H "Content-Type: application/json" \
  -d '{"email":"nouveau@nomorewaste.fr","mot_de_passe":"motdepasse123","role":"staff_back"}'
# -> 201

# un rôle inventé est refusé
curl -X POST http://localhost:8080/api/utilisateurs/ \
  -H "Authorization: $JETON" -H "Content-Type: application/json" \
  -d '{"email":"x@test.fr","mot_de_passe":"motdepasse123","role":"super_admin"}'
# -> 400
```

Et surtout, le test qui compte — vérifié automatiquement par `tests/tester-espace-client.py` :

```bash
# un adhérent tente de se promouvoir administrateur
curl -X POST http://localhost:8080/api/utilisateurs/ \
  -H "Authorization: $JETON_ADHERENT" -H "Content-Type: application/json" \
  -d '{"email":"pirate@test.fr","mot_de_passe":"motdepasse123","role":"admin_back"}'
# -> 403
```

## Ce qui reste à faire

L'écran `back-utilisateurs.html` des maquettes prévoit aussi de **changer le rôle** d'un compte existant, de **réinitialiser un mot de passe** et de **désactiver** un compte. Ces trois actions ne sont pas encore codées : la colonne `actif` existe en base mais rien ne la modifie.

## Fichiers liés

- [auth.go.md](auth.go.md) — l'inscription publique, qui force le rôle `adherent`
- [monEspace.go.md](monEspace.go.md) — l'espace client, écrit en même temps
- [../utils/guard.go.md](../utils/guard.go.md) — `RequireRole`
