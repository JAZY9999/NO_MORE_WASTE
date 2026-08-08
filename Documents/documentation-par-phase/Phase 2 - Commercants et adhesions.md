# Phase 2 — Adhésions des commerçants (+ rappel automatique, point le plus attendu à l'oral)

> ⏱️ **Lecture : ~5 min** · 763 mots, 5 lignes de code

> **Légende** : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour un point du sujet · 🟦 = bonus, absent du sujet (assumé, pas du hors-sujet).
>
> ⚠️ **Point d'attention pour l'oral** : cette phase contient le plus gros ajout personnel du projet (le système de campagnes segmentées, section dédiée ci-dessous). Le sujet demande UNIQUEMENT "un système de rappel automatique de renouvellement" — tout le reste de cette section est un bonus assumé, à présenter comme une valeur ajoutée si le jury pose la question, jamais comme une exigence du cahier des charges.

## Le besoin (pourquoi cette phase existe)

Le sujet demande de "gérer les adhésions des commerçants" et de "prévoir un système de rappel automatique de renouvellement" — c'est la phrase la plus explicite et la plus probablement questionnée du sujet.

## Ce qui a été mis en place

### CRUD de base (`app/commercants.go`, `app/adhesions.go`)
- 🟥 `POST/GET /commercants`, `GET /commercants/{id}` : gestion des commerçants (raison sociale, adresse, email, etc.). — "gérer les adhésions des commerçants (informations générales, identification, …)".
- 🟥 `POST /commercants/{id}/adhesions` : crée une adhésion (date début/fin, statut, montant) rattachée à un commerçant.
- 🟧 `PUT /adhesions/{id}` : renouvelle/modifie une adhésion (nouvelle date de fin, nouveau montant). — le mot "renouvellement" est dans le sujet, cette route est le moyen concret de le faire.

### Le système de rappel automatique (`utils/scheduler.go`, `utils/mailer.go`, `db/rappelsRepository.go`) — LE point clé, 🟥 exigence explicite
Une **goroutine** (un "robot" qui tourne en tâche de fond, en parallèle du serveur web, sans jamais le bloquer) se réveille toutes les 24h et vérifie trois choses :
1. 🟥 Les adhésions dont il reste exactement 30 jours avant expiration → email de rappel "j30". — c'est littéralement "un système de rappel automatique de renouvellement".
2. 🟥 Les adhésions dont il reste exactement 7 jours → email de rappel "j7".
3. 🟦 Les adhésions expirées depuis 180 jours (environ 6 mois) → email de relance "ça fait longtemps". — pas demandé par le sujet, ajouté à la demande de l'utilisateur pour aller plus loin qu'un simple rappel de renouvellement.

🟧 Chaque envoi réussi est enregistré dans une table `adhesion_rappels`, qui sert à ne JAMAIS envoyer deux fois le même rappel (vérifié avant chaque envoi). — pas décrit dans le sujet, mais indispensable pour qu'un rappel "automatique" ne devienne pas du harcèlement par email.

🟦 Un endpoint `POST /admin/jobs/rappels-adhesions` permet de déclencher ce même job manuellement. — pas demandé, ajouté pour pouvoir démontrer le système à l'oral sans attendre 24h.

### 🟦 Le système de campagnes segmentées — AJOUT PERSO, pas demandé par le sujet
Le staff peut créer une "campagne" (un email avec un sujet et un corps personnalisable) ciblant un segment de commerçants selon des critères optionnels et combinables : ville, pays, statut d'adhésion, ancienneté d'expiration. La requête SQL qui résout ces critères est construite dynamiquement mais de façon sûre (aucune valeur n'est jamais collée directement dans le texte SQL — voir `db/campagnesRepository.go.md` pour le détail anti-injection).

**Ce module entier (tables `campagnes`/`campagne_envois`, 4 routes) est un bonus.** Si le temps manque en fin de projet, c'est le premier bloc à laisser de côté sans risque pour la conformité au sujet — le rappel automatique (ci-dessus) suffit déjà à répondre à l'exigence.

## La logique clé à savoir réexpliquer

- Une **goroutine**, c'est un bout de code qui s'exécute EN PARALLÈLE du reste du programme, sans bloquer. `go func() { for { ...; time.Sleep(24*time.Hour) } }()` lance le "robot" une seule fois au démarrage, et il tourne pour toujours.
- Le système anti-doublon : avant chaque envoi, on vérifie dans `adhesion_rappels` si CE type de rappel a déjà été envoyé pour CETTE adhésion. Sans cette vérification, un commerçant à J-7 recevrait le même email tous les jours jusqu'à expiration.

## Comment le vérifier soi-même

```bash
STAFF_TOKEN=... # se connecter d'abord
curl http://localhost:8080/api/adhesions/a-renouveler/ -H "Authorization: $STAFF_TOKEN"
curl -X POST http://localhost:8080/api/admin/jobs/rappels-adhesions/ -H "Authorization: $STAFF_TOKEN"
curl http://localhost:8080/api/adhesions/1/historique-rappels -H "Authorization: $STAFF_TOKEN"
```

## Pour aller plus loin (fichiers `.md` détaillés)

- [api-go/models/commercant.go.md](../../Code/api-go/models/commercant.go.md), [api-go/db/commercantsRepository.go.md](../../Code/api-go/db/commercantsRepository.go.md), [api-go/app/commercants.go.md](../../Code/api-go/app/commercants.go.md)
- [api-go/models/adhesion.go.md](../../Code/api-go/models/adhesion.go.md), [api-go/db/adhesionsRepository.go.md](../../Code/api-go/db/adhesionsRepository.go.md), [api-go/app/adhesions.go.md](../../Code/api-go/app/adhesions.go.md)
- [api-go/utils/mailer.go.md](../../Code/api-go/utils/mailer.go.md) — l'envoi SMTP réel
- [api-go/db/rappelsRepository.go.md](../../Code/api-go/db/rappelsRepository.go.md) — trouver qui relancer, éviter les doublons
- [api-go/utils/scheduler.go.md](../../Code/api-go/utils/scheduler.go.md) — **à lire en priorité** : qu'est-ce qu'une goroutine
- [api-go/app/rappels.go.md](../../Code/api-go/app/rappels.go.md) — les routes de pilotage
- [api-go/models/campagne.go.md](../../Code/api-go/models/campagne.go.md), [api-go/db/campagnesRepository.go.md](../../Code/api-go/db/campagnesRepository.go.md), [api-go/app/campagnes.go.md](../../Code/api-go/app/campagnes.go.md) — le système de campagnes

## Ce qu'il reste à faire dans cette phase

- **2.4** : vue back-office avec filtre — dépend du front PHP.
- Configurer de vraies clés SMTP Brevo dans `.env` (actuellement des placeholders `change_me`) pour un envoi réellement fonctionnel — la logique est déjà validée par les tests.
