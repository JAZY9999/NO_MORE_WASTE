# Plan d'architecture — NO MORE WASTE — Mission 1 (Applications)

> ⏱️ **Lecture : ~40 min** · 2967 mots, 133 lignes de code

## Contexte

Projet scolaire ESGI (rattrapage projet annuel 2i, 2025-2026). Le sujet ("NO MORE WASTE", association anti-gaspillage) contient deux missions ; seule la **Mission 1 (développement des applications)** est traitée ici — la Mission 2 (architecture réseau GNS3/pfSense/VPN) est hors scope et ignorée.

Le dossier `Code/` du projet est actuellement **vide** : on part de zéro. L'objectif est de construire une application web complète permettant à l'association de gérer : adhésions des commerçants, collectes, stocks (avec code-barre), tournées de distribution, bénévoles (candidature → validation → affectation), et services (propositions/planning/inscriptions) — avec back-office et front-office séparés, site multilingue, et le tout empaqueté pour un déploiement facile.

Décisions validées avec l'utilisateur :
- **Monorepo** : un seul dossier `Code/` avec sous-dossiers par service.
- **Conteneurisation Docker** obligatoire pour tous les services.
- **PostgreSQL** conteneurisé comme base de données.
- **API en Go**, **front en FlightPHP** (pas de framework JS).
- **Envoi d'email réel via SMTP** (pas de simulation) pour les rappels d'adhésion et l'envoi du planning.
- **Une seule app FlightPHP** avec routes back-office / front-office séparées (pas deux conteneurs distincts).
- **Multilingue limité à l'UI** (labels/menus/textes fixes) — le contenu métier saisi en back-office reste en une seule langue.
- **Code-barre** : saisie manuelle / douchette USB (simule un clavier) — pas de décodage d'image côté serveur.

L'utilisateur a fourni sa propre todo liste pédagogique (phases 0 à 12, tags 🟥[SUJET]/🟧[ADAPTATION]/🟦[AJOUT PERSO], avec sessions de **live coding** après chaque fonctionnalité pour vérifier qu'il maîtrise le code produit avec l'aide de l'IA — objectif : pouvoir ré-expliquer, modifier et déboguer sans IA). Cette todo est la référence de séquencement retenue ci-dessous (remplace les "étapes" génériques de la section 7 par les phases de l'utilisateur). Contraintes supplémentaires à respecter dans tout le projet :
1. **Documentation par fichier** : chaque fichier de code doit être accompagné d'un `.md` expliquant son fonctionnement, rédigé pour être compréhensible même par quelqu'un de faible niveau — condition nécessaire aux sessions de live coding où l'utilisateur doit pouvoir ré-expliquer le code sans IA.
2. **Checkpoints de live coding** : après chaque fonctionnalité codée, une pause est prévue pour une session de live coding avec l'utilisateur (pas de vérification technique attendue de la part de l'IA à ce moment-là — c'est un exercice humain de restitution).
3. **API Go d'abord, module par module** : l'ordre réel de développement est backend-first. On code l'API Go **fonctionnalité par fonctionnalité** (ex: endpoint de création d'un commerçant, puis endpoint de liste, puis endpoint de renouvellement d'adhésion, etc.), et après **chaque** fonctionnalité on fait un test manuel via **Postman** (requête réelle contre l'API qui tourne dans son conteneur Docker) avant de passer à la suivante. Le front FlightPHP n'est attaqué qu'une fois les modules API concernés sont codés et testés — pas de va-et-vient prématuré vers le front.
4. **API modulaire = un seul service, packages internes séparés par domaine** : pas de micro-services séparés. Un seul binaire/conteneur `api-go`, mais organisé en packages internes clairs par domaine métier (voir section 1 et 3 : `handlers/`, `repository/`, `models/` avec un fichier par domaine — commerçants, collectes, stocks, tournées, bénévoles, services). Chaque module est ainsi codable, testable (Postman) et explicable indépendamment, sans la lourdeur de vrais micro-services pour un projet étudiant en solo.

---

## 1. Structure de dossiers du monorepo

```
Code/
├── docker-compose.yml
├── docker-compose.override.yml.example     # variante dev (hot-reload, ports exposés)
├── .env.example                            # variables d'env (secrets DB, JWT, SMTP, etc.)
├── Makefile                                # cibles: up, down, build, seed, logs, reset-db, package
├── install.sh                              # script de packaging/déploiement (exigence du sujet)
├── README.md                               # doc d'installation + démo
│
├── api-go/
│   ├── Dockerfile
│   ├── go.mod / go.sum
│   ├── cmd/api/main.go                     # point d'entrée, wiring, démarrage HTTP + scheduler cron
│   ├── internal/
│   │   ├── config/                         # lecture env, config app (incl. SMTP)
│   │   ├── db/
│   │   │   └── migrations/                 # fichiers .sql versionnés (golang-migrate)
│   │   ├── models/                         # structs de domaine
│   │   ├── handlers/                       # un fichier par domaine fonctionnel
│   │   │   ├── auth_handler.go
│   │   │   ├── commercants_handler.go
│   │   │   ├── collectes_handler.go
│   │   │   ├── stocks_handler.go
│   │   │   ├── tournees_handler.go
│   │   │   ├── benevoles_handler.go
│   │   │   └── services_handler.go
│   │   ├── repository/                     # accès DB par domaine
│   │   ├── middleware/                     # auth JWT, CORS, logging, role-guard
│   │   ├── pdf/                            # génération PDF récap livraison
│   │   ├── excel/                          # génération planning Excel bénévoles
│   │   ├── barcode/                        # génération code-barre (Code128)
│   │   ├── mailer/                         # client SMTP (envoi rappels + plannings)
│   │   ├── scheduler/                      # cron interne (rappels adhésion, planning quotidien)
│   │   └── router/                         # déclaration des routes
│   └── pkg/                                # utilitaires réutilisables (si besoin réel)
│
├── front-php/
│   ├── Dockerfile
│   ├── composer.json                       # flightphp/core + guzzle
│   ├── public/index.php                    # front controller unique
│   ├── app/
│   │   ├── config/
│   │   ├── routes/
│   │   │   ├── front_routes.php            # front-office (adhérents/public)
│   │   │   └── back_routes.php             # back-office (staff NO MORE WASTE)
│   │   ├── controllers/{front,back}/
│   │   ├── views/{front,back}/
│   │   ├── services/ApiClient.php          # client HTTP vers api-go (Guzzle)
│   │   ├── middleware/                     # auth session, détection langue, role-guard
│   │   └── i18n/fr.php, en.php, it.php, pt.php
│   └── storage/                            # sessions, cache, logs (volume docker)
│
├── nginx/
│   ├── nginx.conf
│   ├── conf.d/nmw.conf                     # réécriture URL, routage /api → go, / → php, erreurs perso
│   └── errors/404.html, 500.html
│
├── postgres/
│   ├── init/00_extensions.sql
│   └── seed/                               # jeu de données de démo
│
└── docs/architecture.md                    # schéma, choix techniques (pour le rapport)
```

---

## 2. Modèle de données relationnel (PostgreSQL)

Principes : clés primaires en `bigserial`, colonnes `created_at`/`updated_at` sur les tables principales, pas de sur-normalisation.

### 2.1 Support / transverse
```sql
utilisateurs (id, email UNIQUE, mot_de_passe_hash, role ENUM('admin_back','staff_back','adherent','benevole'), actif, created_at, updated_at)
langues (code VARCHAR(5) PK, libelle)   -- fr, en, it, pt — utilisé pour préférence utilisateur, pas de table traductions (i18n = fichiers front)
sites (id, ville, pays, adresse, code_langue_defaut REFERENCES langues)  -- Paris, Nantes, Marseille, Limoges, Naples, Porto, Dublin
```

### 2.2 Adhésions commerçants
```sql
commercants (id, raison_sociale, siret, adresse, ville, pays, email, telephone, contact_nom,
  utilisateur_id REFERENCES utilisateurs NULL, site_id REFERENCES sites, created_at, updated_at)

adhesions (id, commercant_id REFERENCES commercants, date_debut, date_fin,
  statut ENUM('active','expiree','resiliee','en_attente'), montant_cotisation NUMERIC,
  rappel_envoye BOOLEAN DEFAULT false, created_at, updated_at)
```
Renouvellement : job cron scanne les adhésions proches de `date_fin` (J-30/J-7) avec `rappel_envoye=false`, envoie un email SMTP réel via `internal/mailer`, marque `rappel_envoye=true`. Historique conservé (nouvelle ligne à chaque renouvellement).

### 2.3 Collectes / Stocks / Produits (code-barre)
```sql
collectes (id, commercant_id REFERENCES commercants NULL, particulier_nom, particulier_adresse,
  benevole_id REFERENCES benevoles NULL, date_prevue, date_realisee,
  statut ENUM('demandee','planifiee','realisee','annulee'), created_at, updated_at)

produits (id, code_barre VARCHAR UNIQUE, libelle, categorie, dlc DATE NULL,
  collecte_id REFERENCES collectes, poids_kg NUMERIC NULL, quantite INT DEFAULT 1,
  emplacement_id REFERENCES emplacements_stock NULL,
  statut ENUM('en_stock','reserve','distribue','perime'), created_at, updated_at)

emplacements_stock (id, entrepot, zone, rayon, etagere)
```
Index sur `produits.code_barre` (recherche rapide). Code-barre saisi manuellement (douchette = clavier) ou généré côté back-office (format interne `CLT-{collecte_id}-{seq}`), rendu visuel via `boombuler/barcode` si besoin d'impression d'étiquette.

### 2.4 Tournées de distribution
```sql
beneficiaires (id, type ENUM('association_caritative','particulier_detresse'), nom, adresse, ville, telephone, contact)

tournees (id, date_tournee, benevole_id REFERENCES benevoles, statut ENUM('planifiee','en_cours','terminee','annulee'), created_at, updated_at)

tournee_etapes (id, tournee_id REFERENCES tournees, beneficiaire_id REFERENCES beneficiaires,
  ordre INT, heure_prevue, heure_reelle NULL, statut ENUM('a_faire','livre','absent'))

livraisons (id, tournee_etape_id REFERENCES tournee_etapes, date_livraison, pdf_genere_path VARCHAR NULL, created_at)

livraison_produits (livraison_id REFERENCES livraisons, produit_id REFERENCES produits, quantite,
  PRIMARY KEY (livraison_id, produit_id))
```
Chaque livraison déclenche la génération d'un PDF récapitulatif listant les produits via `livraison_produits`.

### 2.5 Bénévoles (candidature → validation → affectation) + compétences
```sql
benevoles (id, utilisateur_id REFERENCES utilisateurs, nom, prenom, telephone, adresse,
  statut ENUM('candidat','en_validation','valide','refuse','inactif'),
  permis_conduire BOOLEAN DEFAULT false, date_candidature, date_validation NULL, created_at, updated_at)

competences (id, libelle UNIQUE)   -- chauffeur, cuisinier, plombier, électricien, ...

benevole_competences (benevole_id REFERENCES benevoles, competence_id REFERENCES competences,
  PRIMARY KEY (benevole_id, competence_id))

benevole_documents (id, benevole_id REFERENCES benevoles, type_document, chemin_fichier, valide BOOLEAN DEFAULT false)
```
Workflow : `candidat` → staff valide documents/conditions → `valide` → affectable à des créneaux de service ou tournées/collectes (rôle chauffeur) selon compétences.

### 2.6 Services (propositions, planning, inscriptions)
```sql
services (id, nom, description, competence_requise_id REFERENCES competences NULL,
  type ENUM('conseil_anti_gaspi','cours_cuisine','partage_vehicule','echange_service','reparation','gardiennage','autre'),
  actif BOOLEAN DEFAULT true)

creneaux_service (id, service_id REFERENCES services, benevole_id REFERENCES benevoles NULL,
  date_creneau, heure_debut, heure_fin, lieu, capacite_max INT DEFAULT 1,
  statut ENUM('ouvert','complet','annule','realise'))

inscriptions_service (id, creneau_id REFERENCES creneaux_service, commercant_id REFERENCES commercants NULL,
  utilisateur_id REFERENCES utilisateurs NULL, date_inscription, statut ENUM('inscrit','annule','present'))
```
Planning quotidien envoyé par email (SMTP réel, fichier Excel en pièce jointe) généré depuis `creneaux_service` + `tournees`/`collectes` du jour filtrés par `benevole_id`.

### 2.7 Relations clé à retenir
- commerçants 1-N adhésions ; 1-N collectes
- collectes 1-N produits ; N-1 bénévoles (chauffeur)
- tournées 1-N tournée_étapes 1-1 livraisons N-N produits (via livraison_produits)
- bénévoles N-N compétences ; 1-N créneaux_service (affectation) ; 1-N tournées/collectes (si chauffeur)
- services 1-N créneaux_service 1-N inscriptions_service

---

## 3. API Go

### 3.0 Contrainte majeure imposée par le cours ESGI (prioritaire sur toute décision antérieure)

Après analyse des supports de cours de l'utilisateur ("api web introduction.pdf", "librairies golang.pdf", "authentification V2.pdf", "exemple examen final.pdf") et d'un projet antérieur comparable (UpcycleConnect), il est confirmé que l'évaluation attend une architecture Go **stdlib pur**, sans framework externe, à l'exception du driver de base de données et des libs d'auth explicitement enseignées. Ceci **remplace entièrement** la stack initialement envisagée (chi/pgx/gofpdf/excelize/robfig-cron/boombuler-barcode), jugée hors-sujet pédagogiquement.

### 3.1 Stack technique réellement utilisée
- **Router** : `net/http` stdlib pur, nouveau routing Go 1.22+ (`http.HandleFunc("METHODE /chemin/{$}", handler)`, `{id}` pour capturer un segment variable via `r.PathValue("id")`).
- **DB** : `database/sql` stdlib + driver `github.com/lib/pq` (seule dépendance externe tolérée pour l'accès BDD), requêtes SQL explicites (`Query`, `QueryRow`, `Exec`, `Scan`), pas d'ORM.
- **Schéma DB** : un seul fichier `postgres/init/schema.sql` exécuté automatiquement par l'image officielle Postgres au premier démarrage (`docker-entrypoint-initdb.d`) — pas d'outil de migration versionnée (golang-migrate abandonné, non vu en cours).
- **Auth** : JWT (`github.com/golang-jwt/jwt/v5`) + hachage de mot de passe (`golang.org/x/crypto/bcrypt`) — les deux seules libs externes hors driver DB, explicitement enseignées dans le support "authentification V2.pdf". Token stocké brut (sans préfixe `"Bearer "`) dans le header `Authorization`, vérifié manuellement dans chaque handler protégé (pas de middleware générique, conforme au support de cours qui présente cette version comme "plus simple").
- **PDF / Excel / code-barre / cron** : **aucune librairie externe** — ces besoins seront couverts en stdlib pur au moment de coder les phases correspondantes (ex: génération de CSV via `encoding/csv` pour le planning au lieu d'un vrai `.xlsx`, texte/HTML simple pour le récapitulatif de livraison au lieu d'un vrai PDF binaire, `time.Ticker`/goroutine pour la tâche planifiée au lieu de `robfig/cron`, simple champ texte unique en DB pour le code-barre sans génération d'image). Détails à préciser phase par phase.
- **Email (SMTP)** : décision utilisateur validée en amont (envoi réel, pas de simulation) — à implémenter en Phase 2/7 avec `net/smtp` (stdlib) uniquement, pas de lib tierce type gomail.

### 3.2 Structure réelle des packages Go (arborescence plate, pas de `cmd/`/`internal/`)

```
api-go/
├── go.mod / go.sum
├── app.go                      # package main : point d'entrée, routing, ListenAndServe
├── config/
│   └── config.go               # lecture centralisée des variables d'environnement
├── db/
│   ├── db.go                   # connexion *sql.DB (avec retry au démarrage Docker)
│   └── xxxRepository.go        # un fichier de requêtes SQL par domaine (ex: utilisateursRepository.go)
├── models/
│   └── xxx.go                  # structs de domaine + DTOs (ex: utilisateur.go)
├── app/
│   └── xxx.go                  # handlers HTTP par domaine (ex: auth.go), package "app"
└── utils/
    └── jwt.go                  # génération/vérification JWT
```

Chaque nouveau domaine métier (commerçants, collectes, stocks, tournées, bénévoles, services) ajoute : un fichier dans `models/`, un fichier repository dans `db/`, un fichier handler dans `app/`, et ses routes déclarées dans `app.go`.

### 3.3 Endpoints codés à ce jour (Phase 1 — Auth)
- `POST /auth/register/{$}` — inscription (rôle `adherent` par défaut), mot de passe haché bcrypt
- `POST /auth/login/{$}` — vérifie les identifiants, retourne un JWT `{"token": "..."}`
- `GET /auth/me/{$}` — lit le token dans le header `Authorization`, retourne l'utilisateur courant

Chacun testé individuellement via `curl`/Postman avant de passer au suivant, conformément à la méthode de travail validée (API d'abord, fonctionnalité par fonctionnalité, testée immédiatement).

### 3.4 Endpoints restants à coder (phases suivantes, mêmes conventions)
Reprendre la liste par domaine (commerçants/adhésions, collectes, stocks, tournées, bénévoles, services) précédemment envisagée en section 3.2 d'une version antérieure de ce document reste valable **dans son découpage fonctionnel et ses noms de routes** (ex: `GET/POST /commercants`, `POST /tournee-etapes/{id}/livraison`, etc.) — seule la stack technique sous-jacente change (stdlib au lieu de chi, pas de génération PDF/Excel binaire réelle mais des équivalents simples, voir 3.1).

---

## 4. Front FlightPHP

### 4.1 Back-office / front-office : une seule app, routes séparées
Une seule codebase FlightPHP (un seul conteneur), deux arborescences de routes (`app/routes/back_routes.php`, `app/routes/front_routes.php`) montées sous des préfixes différents (`/back/...` vs `/`). Séparation logique via sous-dossiers `controllers/back/`, `controllers/front/`, `views/back/`, `views/front/`, et middleware de garde de rôle sur le préfixe `/back`.

### 4.2 Multilingue (UI uniquement)
- Fichiers `app/i18n/fr.php`, `en.php`, `it.php`, `pt.php` (tableaux associatifs clé → libellé)
- `LangMiddleware` : détecte la langue (paramètre URL > cookie/session > `Accept-Language` > défaut fr), stocke en session
- Helper global `t('cle')` avec fallback vers `fr`
- Le contenu métier (descriptions de services, etc.) reste dans une seule langue, saisi tel quel en back-office

### 4.3 Consommation de l'API Go
`app/services/ApiClient.php` : wrapper Guzzle, `API_BASE_URL=http://api-go:8080` (nom du service Docker), injection du JWT (stocké en session PHP) dans `Authorization: Bearer ...`.

### 4.4 Sessions / Auth
Session PHP native stockant `user_id`, `role`, `jwt`, `lang`. Login : le controller appelle `POST /auth/login` sur l'API Go, stocke le JWT en session. Middleware vérifie le rôle (`admin_back`/`staff_back` pour `/back`, `adherent`/`benevole` pour le reste).

---

## 5. Docker

### 5.1 Conteneurs (5 services)
1. **postgres** — `postgres:16-alpine`, volume nommé, scripts d'init SQL montés
2. **api-go** — build multi-stage (`golang:1.22-alpine` → `alpine:latest`), port interne 8080
3. **front-php** — `php:8.3-fpm-alpine` + composer, port interne 9000 (PHP-FPM derrière nginx)
4. **nginx** — reverse proxy unique, route `/api/*` → api-go, reste → front-php (fastcgi)
5. **mailpit** (profil dev uniquement, optionnel) — pour tester les emails SMTP en local sans envoyer de vrais mails pendant le développement

### 5.2 docker-compose.yml (structure)
- `postgres` : image officielle, `env_file .env`, volumes `pgdata` + `postgres/init`, healthcheck `pg_isready`
- `api-go` : build `./api-go`, `env_file .env` (incl. variables SMTP), `depends_on postgres` (condition `service_healthy`), volumes `pdf_data`, `planning_data`
- `front-php` : build `./front-php`, `env_file .env`, `depends_on api-go`
- `nginx` : build `./nginx`, seul service exposant un port sur l'hôte (`8080:80`), `depends_on front-php`, `api-go`

Seul nginx publie un port sur l'hôte — cohérent avec l'exigence "serveur web personnel" à point d'entrée unique.

### 5.3 Variables d'environnement
`.env.example` documente : `POSTGRES_USER/PASSWORD/DB`, `DATABASE_URL`, `JWT_SECRET`, `API_BASE_URL`, `SMTP_HOST/PORT/USER/PASSWORD/FROM`.

### 5.4 Script de packaging/déploiement (exigence explicite du sujet)
- **install.sh** : vérifie prérequis (docker, docker compose), copie `.env.example` → `.env` si absent, `docker compose build`, `docker compose up -d`, attend postgres healthy, exécute les migrations, charge le seed de démo, affiche l'URL finale.
- **Makefile** : `make up|down|build|seed|logs|reset-db|package` (génère une archive prête à déployer sur un autre serveur).

---

## 6. Nginx — réécriture d'URL et erreurs personnalisées

- `location /api/` → `proxy_pass http://api-go:8080/` (headers Host, X-Real-IP)
- `location /` → sert `front-php/public`, `try_files $uri $uri/ /index.php?$query_string` (réécriture d'URL pour routes propres type `/commercants/12`)
- `location ~ \.php$` → `fastcgi_pass front-php:9000`
- `error_page 404` → `/errors/404.html`, `error_page 500 502 503 504` → `/errors/500.html`

---

## 7. Séquencement retenu — API Go d'abord, fonctionnalité par fonctionnalité, testée Postman

La todo fournie par l'utilisateur (phases 0-12) reste le découpage pédagogique de référence (tags 🟥/🟧/🟦, sessions de live coding). Mais l'ordre réel d'exécution à l'intérieur de chaque phase est **backend-first et atomique** : dans chaque phase métier (2 à 7), on code l'API Go endpoint par endpoint, et après **chaque** endpoint fonctionnel on fait un test manuel Postman contre le conteneur `api-go` réel (pas de mock) avant d'écrire l'endpoint suivant. Le front FlightPHP correspondant à une phase n'est écrit qu'une fois tous les endpoints de cette phase sont codés et validés Postman — jamais avant.

Concrètement, pour une phase métier donnée (ex. Phase 2 — Adhésions), le déroulé est :
1. Migration SQL de la/les tables concernées (si pas déjà fait en Phase 0)
2. Endpoint API #1 (ex: `POST /commercants`) → test Postman → `.md` explicatif du handler
3. Endpoint API #2 (ex: `GET /commercants`) → test Postman → `.md`
4. ... ainsi de suite pour chaque endpoint du module (voir liste complète section 3.2)
5. Une fois tous les endpoints du module validés : vues/controllers FlightPHP qui consomment cette partie de l'API
6. Live coding de la phase

**Phase 0 — Cadrage & fondations** : entités/relations = section 2 (modèle de données) ; structure repo = section 1 ; migrations SQL = section 2 ; Docker dès le départ (dépendances installées au build, pas au run) = section 5, avec attention particulière car explicitement noté comme retour d'expérience négatif d'un projet précédent (Phase 11.1 de la todo) — **à respecter strictement dans tous les Dockerfiles dès l'étape 0**, pas seulement en fin de projet. Inclut la mise en place de la collection Postman du projet (un dossier par domaine métier, réutilisé et enrichi à chaque phase suivante).
→ Live coding #1 : setup Docker + schéma BDD.

**Phase 1 — Auth & rôles (API d'abord)** : endpoints `POST /auth/register`, `POST /auth/login`, `GET /auth/me` (section 3.2) codés et testés Postman un par un, avant le middleware JWT/role-guard, avant enfin les pages de connexion FlightPHP (section 4.4). Rôles : `staff_nmw`/`admin_back` (back-office), `commercant`/`adherent`, `benevole` — à harmoniser avec les ENUM de la section 2.1 (`utilisateurs.role`). Page de connexion multilingue = anticipation légère de la Phase 8 (structure i18n de base seulement).
→ Live coding #2 : middleware de vérification de rôle.

**Phase 2 — Adhésions commerçants** : endpoints CRUD `commercants`/`adhesions` (section 3.2) un par un + test Postman à chaque fois, puis job cron rappel + envoi SMTP réel (section 3.3/3.5) testé isolément (déclenchement manuel avant de compter sur le scheduler), puis pages back-office/front-office FlightPHP.
→ Live coding #3 : job planifié de rappel automatique (point 🟥 critique).

**Phase 3 — Stocks (code-barre)** : endpoints `produits`/`emplacements_stock` (section 2.3) un par un + Postman, saisie manuelle/douchette (décision validée, pas de décodage d'image), puis front.
→ Live coding #4 : génération/saisie du code-barre et recherche rapide.

**Phase 4 — Collectes** : endpoints `collectes` (section 2.3) un par un + Postman, lien vers produits, puis front.
→ Live coding #5 : parcours complet d'une collecte.

**Phase 5 — Tournées** : endpoints `tournees`/`tournee_etapes`/`livraisons` (section 2.4) un par un + Postman, génération PDF à la clôture (section 3.3) testée via Postman (télécharger le PDF généré), puis front.
→ Live coding #6 : génération du PDF récapitulatif (lib utilisée, flux des données).

**Phase 6 — Bénévoles** : endpoints candidature → validation → affectation, compétences (section 2.5) un par un + Postman, puis front (formulaire public + back-office validation).
→ Live coding #7 : logique de validation avant affectation.

**Phase 7 — Services** : endpoints `services`/`creneaux_service`/`inscriptions_service` (section 2.6) un par un + Postman, planning Excel + envoi SMTP quotidien (section 3.4/3.5) testé isolément, puis front.
→ Live coding #8 : génération du planning Excel quotidien.

**Phase 8 — Multilingue** : section 4.2 (i18n UI uniquement, décision validée), à intégrer rétroactivement dans les vues FlightPHP des phases 2-7.
→ Live coding #9 : ajout d'une langue en direct.

**Phase 9 — Back-office centralisé (finalisation)** : section 4.1 (séparation routes back/front dans la même app FlightPHP).
→ Live coding #10 : parcours complet du dashboard.

**Phase 10 — API Go consolidation** : relecture de tous les endpoints codés en phases 1-7 (gestion d'erreurs HTTP propre, pas de 500 silencieux), documentation des endpoints (export de la collection Postman comme documentation, Swagger en bonus).
→ Live coding #11 : explication de 2-3 endpoints Go, justification du choix Go.

**Phase 11 — Déploiement final** : section 5 (Docker), section 6 (nginx, réécriture URL, erreurs personnalisées) — **sur un vrai serveur, pas en localhost** (contrainte explicite de l'utilisateur reprise du sujet).
→ Live coding #12 : déploiement complet refait en direct.

**Phase 12 — Packaging & documentation** : section 5.4 (install.sh, Makefile) + README + vérification qu'aucun secret/`.env`/`.git`/`vendor` n'est exposé publiquement.

**Notes de séquencement technique** : les phases 2 à 7 (modules métier) restent largement indépendantes une fois les phases 0 (schéma) et 1 (auth) faites — seule dépendance réelle : la Phase 6 (bénévoles) doit précéder l'affectation "chauffeur" utilisée dans les Phases 3/4/5 (mais les endpoints CRUD de base de ces phases peuvent être codés et testés Postman avant, seule l'affectation réelle attend la Phase 6).

## 8. Documentation par fichier (contrainte transverse, toutes phases)

Chaque fichier de code livré est accompagné d'un fichier `.md` de même nom (ex: `commercants_handler.go` → `commercants_handler.md`, placé à côté ou dans un sous-dossier `docs/` miroir de l'arborescence) expliquant :
- Ce que fait le fichier et son rôle dans le flux global (front → API → BDD → retour)
- Les fonctions/routes principales et pourquoi elles sont écrites ainsi
- Les pièges/subtilités à connaître pour pouvoir modifier le code sans IA

Rédaction accessible à quelqu'un de faible niveau (vocabulaire simple, pas de jargon non expliqué) — condition nécessaire pour que l'utilisateur puisse réussir les sessions de live coding sans IA. Cette documentation est produite **au fur et à mesure de chaque phase**, pas en bloc à la fin.

---

## Point restant à trancher à l'implémentation
Fournisseur SMTP à utiliser pour la démo/soutenance (SMTP réel type Gmail/SendGrid, ou Mailpit en dev + vrai SMTP en prod) — à décider avec l'utilisateur au moment de coder le module `mailer`.

---

## Vérification / test de bout en bout
1. `./install.sh` (ou `make up`) depuis une machine propre → tous les conteneurs démarrent, healthchecks OK.
2. Accès à `http://<serveur>:8080/` → front-office s'affiche ; `/back` → back-office (après login).
3. Créer un commerçant + adhésion → vérifier en base ; forcer une `date_fin` proche → vérifier réception de l'email de rappel.
4. Créer une collecte, ajouter un produit avec code-barre → recherche par code-barre fonctionne.
5. Créer une tournée, une étape, clôturer une livraison → PDF généré et téléchargeable.
6. Candidater comme bénévole → valider en back-office → affecter à une collecte.
7. Créer un service + créneau → s'inscrire en front-office → vérifier le planning Excel généré et envoyé par email du jour.
8. Changer la langue (fr/en/it/pt) → vérifier que les libellés UI changent.
9. Tester une URL invalide → page 404 personnalisée ; simuler une erreur serveur → page 500 personnalisée.
10. Redémarrer avec volumes vides (`make reset-db` puis réinstallation) → tout refonctionne.
