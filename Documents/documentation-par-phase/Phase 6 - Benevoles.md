# Phase 6 — Bénévoles (le module le plus riche du projet)

> ⏱️ **Lecture : ~10 min** · 879 mots, 20 lignes de code

> **Légende** : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour un point du sujet · 🟦 = bonus, absent du sujet (assumé, pas du hors-sujet).
>
> Phase quasi entièrement conforme au sujet (la logique de validation en 3 étapes est demandée mot pour mot) — seul le référentiel fixe de compétences en base est un choix d'implémentation, pas une exigence littérale.

## Le besoin (pourquoi cette phase existe)

Le sujet dit littéralement : "gérer le suivi des bénévoles, depuis leur candidature jusqu'à leur affectation à un service donné, prenant en compte les différentes capacités qu'ils ont (chauffeurs, cuisiniers, plombiers, …)" et "chacun peut s'inscrire (…) à condition de valider un certain nombre de conditions." C'est un module en 3 étapes obligatoires : candidature → validation de conditions → affectation par compétence.

## Ce qui a été mis en place (10 routes au total)

### Étape 1 : la candidature (`POST /benevoles/candidature`)
🟥 **Route publique, sans authentification** — la toute première du projet à ce jour. N'importe qui, même pas connecté, peut candidater pour devenir bénévole. Le statut est forcé à `"candidat"` côté serveur (impossible pour quelqu'un de s'auto-déclarer "validé"). — "chacun peut s'inscrire auprès de NO MORE WASTE et proposer ses services".

### Étape 2 : la validation de conditions — LE point le plus important à maîtriser, 🟥 exigence explicite

Le sujet parle de "conditions à valider" — modélisées ici par des **documents** (table `benevole_documents`) : chaque pièce à fournir (permis de conduire, casier judiciaire, etc.) est enregistrée par le staff, puis validée une par une.

- 🟧 `POST /benevoles/{id}/documents` : le staff enregistre qu'une pièce est attendue/reçue. — le sujet ne détaille pas "documents", mais il faut bien un moyen concret de représenter une "condition".
- 🟧 `PUT /benevoles/{id}/documents/{docId}/validation` : le staff valide UN document précis.
- 🟥 `PUT /benevoles/{id}/validation` : **la route qui applique la règle du sujet**. Elle refuse de faire passer le bénévole au statut `"valide"` tant que TOUS ses documents ne sont pas eux-mêmes validés. Si un seul document manque ou n'est pas validé, elle répond `400 Bad Request` avec un message clair. — "à condition de valider un certain nombre de conditions", cité mot pour mot.

C'est la fonction `db.TousLesDocumentsSontValides` qui fait cette vérification : elle compte, en une seule requête SQL, le nombre total de documents ET le nombre de documents validés pour ce bénévole, et exige que les deux nombres soient égaux (et qu'il y ait au moins un document — un bénévole sans aucune condition enregistrée ne peut pas être validé).

### Étape 3 : l'affectation par capacité (compétences), 🟥 exigence explicite
- 🟧 `GET /competences` : le référentiel fixe, déjà rempli dans la base (`chauffeur`, `cuisinier`, `plombier`, `electricien`, `bricoleur`). — le sujet cite ces exemples de capacités, mais le choix d'une table dédiée est une décision d'implémentation.
- 🟧 `GET /benevoles/{id}/competences` : les compétences d'un bénévole précis.
- 🟥 `POST /benevoles/{id}/competences/{competenceId}` : associer une compétence (rejette un doublon avec `409 Conflict`). — "prenant en compte les différentes capacités qu'ils ont".
- 🟧 `DELETE /benevoles/{id}/competences/{competenceId}` : dissocier une compétence.

Une fois qu'un bénévole a la compétence `"chauffeur"` et est `"valide"`, il peut être affecté comme chauffeur d'une collecte (Phase 4, champ `benevole_id`) ou d'une tournée (Phase 5, à venir).

## La logique clé à savoir réexpliquer (le scénario complet, à connaître par cœur)

1. Une personne candidate via `POST /benevoles/candidature` → statut `"candidat"`.
2. Le staff essaie de la valider (`PUT /benevoles/{id}/validation` avec `{"statut":"valide"}`) → **refusé** car aucun document n'existe encore.
3. Le staff enregistre un document requis (`POST /benevoles/{id}/documents`).
4. Le staff réessaie de valider le bénévole → **refusé à nouveau**, car ce document existe mais n'est pas encore validé.
5. Le staff valide le document (`PUT /benevoles/{id}/documents/{docId}/validation`).
6. Le staff réessaie de valider le bénévole → **réussi cette fois**, `date_validation` est remplie automatiquement.
7. Le staff associe une compétence au bénévole → il peut désormais être affecté selon cette capacité.

Ce scénario en 7 étapes a été testé intégralement via `curl`, dans cet ordre exact, et chaque étape a produit le résultat attendu (les deux refus, puis les deux succès).

## Deux nouveautés techniques introduites dans ce module

- **Route publique sans rôle** (`PoserCandidature`) : contrairement à tous les autres handlers du projet, elle n'appelle jamais `utils.RequireRole` — choix voulu, pas un oubli.
- **`DELETE`, première utilisation dans le projet** (`RetirerCompetenceBenevole`) : supprime l'association entre un bénévole et une compétence (pas le bénévole ni la compétence elles-mêmes).
- **Deux identifiants dans la même URL** (`/benevoles/{id}/documents/{docId}/validation`, `/benevoles/{id}/competences/{competenceId}`) : chaque `{...}` doit porter un nom différent pour que Go puisse les distinguer avec `r.PathValue("id")` et `r.PathValue("docId")`.

## Comment le vérifier soi-même (reproduit le scénario complet ci-dessus)

```bash
# 1. Candidature publique, sans token
curl -X POST http://localhost:8080/api/benevoles/candidature/ -H "Content-Type: application/json" -d '{"nom":"Martin","prenom":"Julie"}'

STAFF_TOKEN=... # se connecter

# 2. Refuse (pas de document)
curl -X PUT http://localhost:8080/api/benevoles/1/validation -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" -d '{"statut":"valide"}'

# 3. Ajouter un document
curl -X POST http://localhost:8080/api/benevoles/1/documents -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" -d '{"type_document":"permis_conduire"}'

# 4. Refuse encore (document non validé)
curl -X PUT http://localhost:8080/api/benevoles/1/validation -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" -d '{"statut":"valide"}'

# 5. Valider le document
curl -X PUT http://localhost:8080/api/benevoles/1/documents/1/validation -H "Authorization: $STAFF_TOKEN"

# 6. Reussit
curl -X PUT http://localhost:8080/api/benevoles/1/validation -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" -d '{"statut":"valide"}'
```

## Pour aller plus loin (fichiers `.md` détaillés)

- [api-go/models/benevole.go.md](../../Code/api-go/models/benevole.go.md) — les 3 structs (bénévole, compétence, document)
- [api-go/db/benevolesRepository.go.md](../../Code/api-go/db/benevolesRepository.go.md) — **à lire en priorité** : le détail de `TousLesDocumentsSontValides` et `COUNT(*) FILTER`
- [api-go/app/benevoles.go.md](../../Code/api-go/app/benevoles.go.md) — les 10 handlers, la route publique, le `DELETE`, les URLs à deux identifiants

## Ce qu'il reste à faire dans cette phase

Rien — la Phase 6 est entièrement terminée et testée (6.1 à 6.3), avec le scénario complet validé de bout en bout.
