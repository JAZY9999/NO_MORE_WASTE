# Phase 4 — Collectes

> ⏱️ **Lecture : ~5 min** · 424 mots, 5 lignes de code

> **Légende** : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour un point du sujet · 🟦 = bonus, absent du sujet (assumé, pas du hors-sujet).
>
> Phase entièrement conforme au sujet, aucun ajout personnel notable ici.

## Le besoin (pourquoi cette phase existe)

Le sujet demande de "gérer le système des collectes" et de rattacher les produits scannés à la collecte pendant laquelle ils ont été récupérés.

## Ce qui a été mis en place (`app/collectes.go`, `db/collectesRepository.go`, `models/collecte.go`)

- 🟥 `POST /collectes` : crée une collecte, SOIT pour un commerçant SOIT pour un particulier (vérifié : il en faut au moins un des deux). — "gérer le système des collectes", collectes chez les commerçants ou chez les particuliers, décrit dans la présentation du sujet.
- 🟧 `GET /collectes`, `GET /collectes/{id}` : consultation, filtrable par statut.
- 🟥 `PUT /collectes/{id}` : change le statut (`demandee` → `planifiee` → `realisee`/`annulee`) et affecte un bénévole chauffeur. — le sujet demande explicitement de gérer des statuts de collecte.
- 🟥 `POST /collectes/{id}/produits` : enregistre un produit DIRECTEMENT rattaché à cette collecte — réutilise le code de la Phase 3 (`CreateProduit`, `GetProduitByCodeBarre`), sans dupliquer la logique. — c'est le lien "produits rapportés" demandé par le sujet.
- 🟧 `GET /collectes/{id}/produits` : liste tous les produits récupérés lors d'une collecte précise.

## La logique clé à savoir réexpliquer

**Pourquoi `POST /collectes/{id}/produits` crée un produit plutôt que de rattacher un produit existant** : c'est le flux réel sur le terrain — un bénévole scanne un produit AU MOMENT de la collecte, ce produit n'existait pas avant. C'est plus fidèle à la réalité qu'un système où on créerait le produit d'abord, puis on le rattacherait après coup.

**Le remplissage automatique de `date_realisee`** : quand le statut passe à `"realisee"`, la base de données remplit elle-même la date/heure actuelle (`now()` en SQL), sans que le client (le futur front) ait besoin de l'envoyer. Plus fiable que de faire confiance à l'horloge de l'ordinateur du client.

## Comment le vérifier soi-même

```bash
STAFF_TOKEN=... # se connecter d'abord
curl -X POST http://localhost:8080/api/collectes/ -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" -d '{"commercant_id":1}'
curl -X POST http://localhost:8080/api/collectes/1/produits -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" -d '{"code_barre":"CB-2","libelle":"Yaourts"}'
curl -X PUT http://localhost:8080/api/collectes/1 -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" -d '{"statut":"realisee"}'
```

## Pour aller plus loin (fichiers `.md` détaillés)

- [api-go/models/collecte.go.md](../../Code/api-go/models/collecte.go.md) — pourquoi commerçant ET particulier sont tous les deux optionnels
- [api-go/db/collectesRepository.go.md](../../Code/api-go/db/collectesRepository.go.md) — le remplissage automatique de date
- [api-go/app/collectes.go.md](../../Code/api-go/app/collectes.go.md) — la réutilisation du code de la Phase 3

## Ce qu'il reste à faire dans cette phase

Rien — la Phase 4 est entièrement terminée et testée (4.1 à 4.3). Le champ `BenevoleId` est utilisable dès maintenant grâce à la Phase 6 (bénévoles) déjà codée.
