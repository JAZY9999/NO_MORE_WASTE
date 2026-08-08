# Phase 1 — Authentification & rôles

> ⏱️ **Lecture : ~5 min** · 519 mots, 7 lignes de code

> **Légende** : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour un point du sujet · 🟦 = bonus, absent du sujet (assumé, pas du hors-sujet).

## Le besoin (pourquoi cette phase existe)

Le sujet distingue explicitement un "back-office (utilisé par NO MORE WASTE)" et un "front office (utilisé par les clients)" — il faut donc un système qui identifie QUI fait chaque requête, et QUEL DROIT cette personne a.

## Ce qui a été mis en place

### L'inscription et la connexion (`app/auth.go`)
- 🟧 `POST /auth/register` : crée un compte (rôle `adherent` par défaut — personne ne peut s'auto-attribuer un rôle privilégié en s'inscrivant). Le mot de passe est haché avec `bcrypt` avant d'être stocké — jamais en clair en base. — le sujet ne dit pas "fais une inscription", mais parle de "clients" et d'"adhérents" qui doivent bien avoir un compte.
- 🟧 `POST /auth/login` : vérifie les identifiants, retourne un **token JWT** (une longue chaîne signée qui prouve l'identité, valable 8h).
- 🟦 `GET /auth/me` : retourne le profil de la personne connectée, à partir de son token. — pas demandé par le sujet, ajouté par commodité technique (pratique pour tester/déboguer, et utile au futur front).

### Le système de rôles (`utils/guard.go`)
🟥 Une fonction unique, `RequireRole(w, r, "role1", "role2", ...)`, appelée en une ligne au début de chaque route protégée. Elle vérifie le token ET compare le rôle contenu dedans à la liste des rôles autorisés pour cette route précise. Pas de "middleware" automatique qui s'intercale tout seul (choix simple et explicite, conforme au style du cours) — chaque handler protégé affiche clairement, dès sa première ligne, qui a le droit d'y accéder. — répond directement à l'exigence du sujet de séparer "back-office (NO MORE WASTE)" et "front office (clients)".

Quatre rôles existent : `admin_back`, `staff_back` (le personnel NO MORE WASTE), `adherent` (un commerçant), `benevole`.

## La logique clé à savoir réexpliquer

1. Le client envoie ses identifiants à `/auth/login`.
2. Le serveur vérifie le mot de passe (comparaison bcrypt), génère un token JWT signé avec une clé secrète connue seulement du serveur, contenant l'email et le rôle.
3. Le client renvoie ce token dans le header `Authorization` à chaque requête suivante.
4. Le serveur déchiffre le token avec la même clé secrète, en extrait le rôle, et l'autorise ou non selon la route visée.

## Comment le vérifier soi-même

```bash
# Sans token -> 401
curl http://localhost:8080/api/admin/ping/

# Login puis test avec le token
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login/ -H "Content-Type: application/json" -d '{"email":"staff@nomorewaste.fr","mot_de_passe":"..."}' | grep -o '"token":"[^"]*' | cut -d'"' -f4)
curl http://localhost:8080/api/admin/ping/ -H "Authorization: $TOKEN"   # -> 200 si staff, 403 si mauvais role
```

## Pour aller plus loin (fichiers `.md` détaillés)

- [api-go/models/utilisateur.go.md](../../Code/api-go/models/utilisateur.go.md)
- [api-go/db/utilisateursRepository.go.md](../../Code/api-go/db/utilisateursRepository.go.md)
- [api-go/app/auth.go.md](../../Code/api-go/app/auth.go.md)
- [api-go/utils/jwt.go.md](../../Code/api-go/utils/jwt.go.md) — comment un token est fabriqué et vérifié
- [api-go/utils/guard.go.md](../../Code/api-go/utils/guard.go.md) — le système de rôle, piège du double-`return`
- [api-go/app/admin.go.md](../../Code/api-go/app/admin.go.md) — exemple concret de route protégée

## Ce qu'il reste à faire dans cette phase

- **1.1 (partiel)** : il n'existe pas encore de route pour créer proprement un compte staff/admin (le compte de test a été créé directement en base par SQL manuel).
- **1.3** : page de connexion multilingue — dépend du front PHP, pas encore attaqué (choix assumé : toute l'API est terminée avant de commencer le front).
