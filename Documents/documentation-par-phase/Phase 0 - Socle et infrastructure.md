# Phase 0 — Socle & infrastructure

> ⏱️ **Lecture : ~5 min** · 558 mots, 5 lignes de code

> **Légende** (reprise de `Documents/TODO-Mission1-NoMoreWaste.md`) : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour réaliser un point du sujet · 🟦 = bonus, absent du sujet (assumé, pas du hors-sujet), à sacrifier en premier si besoin de temps.

## Le besoin (pourquoi cette phase existe)

Avant de coder la moindre fonctionnalité métier, il fallait un squelette de projet qui tourne : une base de données, une API qui répond, un front qui s'affiche, le tout dans des conteneurs Docker (exigence explicite du sujet : "le produit rendu devra être packagé pour pouvoir être aisément déployé").

## Ce qui a été mis en place

- 🟥 **4 conteneurs Docker** : `postgres` (la base de données), `api-go` (le programme Go), `front-php` (FlightPHP), `nginx` (le point d'entrée unique, sur le port 8080). — le sujet exige explicitement un produit "packagé pour être aisément déployé".
- 🟧 **`postgres/init/schema.sql`** : un seul fichier SQL qui décrit TOUTES les tables du projet (pas d'outil de migration versionnée, conforme au style du cours ESGI). Ce fichier s'exécute automatiquement la toute première fois que le conteneur Postgres démarre. — le sujet ne dit pas "fais un schéma SQL", mais aucune des fonctionnalités demandées n'est réalisable sans un modèle de données.
- 🟧 **La structure Go** : pas de dossiers `cmd/`/`internal/` compliqués — un fichier `app.go` à la racine (le point d'entrée), et des dossiers plats `app/` (les routes), `db/` (les requêtes SQL), `models/` (les structures de données), `config/` (les réglages), `utils/` (les outils transverses comme le JWT). — l'organisation du code n'est pas décrite dans le sujet, mais suit le style enseigné en cours ESGI.
- 🟦 **`.env`** : toutes les valeurs de configuration (mots de passe, ports, clés) au même endroit, jamais écrites en dur dans le code. — bonne pratique non demandée par le sujet, ajoutée pour la qualité du rendu.

## Deux bugs trouvés en testant (bon exemple de "difficulté rencontrée" pour l'oral)

1. **Le dossier `vendor/` de PHP disparaissait au démarrage.** Le conteneur `front-php` monte le code source local par-dessus l'image Docker (pour pouvoir modifier le code sans reconstruire l'image à chaque fois) — mais ce montage écrasait aussi le dossier `vendor/` (les dépendances PHP) généré pendant la construction de l'image. Corrigé avec un second volume dédié, monté uniquement sur `vendor/`, qui garde ce contenu indépendamment du reste.
2. **Les pages d'erreur 404/500 personnalisées ne s'affichaient pas.** nginx affichait par défaut le message d'erreur brut du programme Go ou de PHP, pas notre belle page stylisée — sauf pour les erreurs générées par nginx lui-même. Corrigé en ajoutant `proxy_intercept_errors on;` et `fastcgi_intercept_errors on;` dans la config nginx, qui forcent nginx à toujours utiliser NOS pages d'erreur, peu importe qui a généré l'erreur à l'origine.

## Comment le vérifier soi-même

```bash
docker compose up -d
curl http://localhost:8080/api/          # doit répondre "NO MORE WASTE api - ok"
curl http://localhost:8080/              # doit répondre "NO MORE WASTE - front en ligne"
curl http://localhost:8080/nimportequoi  # doit afficher la page 404 personnalisée
```

## Pour aller plus loin (fichiers `.md` détaillés)

- [api-go/app.go.md](../../Code/api-go/app.go.md) — le point d'entrée du programme
- [api-go/config/config.go.md](../../Code/api-go/config/config.go.md) — la configuration centralisée
- [api-go/db/db.go.md](../../Code/api-go/db/db.go.md) — la connexion à Postgres, avec le mécanisme de réessai
- [docker-compose.yml.md](../../Code/docker-compose.yml.md) — comment les conteneurs s'assemblent, le piège du `vendor/`
- [nginx/conf.d/nmw.conf.md](../../Code/nginx/conf.d/nmw.conf.md) — la réécriture d'URL et les pages d'erreur

## Ce qu'il reste à faire dans cette phase

- **0.2** : rédiger le mini cahier des charges (1-2 pages) — ce n'est pas du code, un document séparé à écrire.
