# Journal de bord — NO MORE WASTE Mission 1

> ⏱️ **Lecture : ~1 h 50** · 13985 mots, 9 lignes de code

> Ce fichier raconte le fil du projet dans l'ordre chronologique : ce qui a été codé, pourquoi, dans quel ordre, et les raisonnements qui ont mené à chaque décision. Contrairement aux fichiers `.md` à côté de chaque fichier de code (qui expliquent CE QUE fait le code), ce journal explique POURQUOI on a codé les choses ainsi et dans quel contexte — pour pouvoir tout relire et comprendre la logique même sans accès à l'IA.
>
> Chaque entrée suit ce format : **date/heure**, **ce qui a été fait**, **pourquoi/comment on y est arrivé**, **résultat du test**.

---

## 2026-07-28 — Phase 0 : cadrage et socle

**Ce qui a été fait :** analyse du sujet de rattrapage (`Documents/Rattrapages 2i 2025-2026.docx-1.pdf`), conception du plan d'architecture complet (structure du monorepo, modèle de données PostgreSQL, choix Docker), validé avec l'utilisateur via plusieurs questions de cadrage (monorepo vs repos séparés, SMTP réel vs simulé, back/front-office en une ou deux apps, granularité i18n).

**Pourquoi ces choix :** le sujet impose "API en Go et front PHP" — le reste (Docker, PostgreSQL, structure du repo) n'était pas imposé, donc discuté et validé avec l'utilisateur avant de coder quoi que ce soit.

**Résultat :** plan écrit dans `Documents/plan-architecture/plan-architecture-mission1.md`.

---

## 2026-07-28 (suite) — Premier code, puis découverte des contraintes du cours ESGI

**Ce qui a été fait :** création de la structure de dossiers (`api-go/`, `front-php/`, `nginx/`, `postgres/`), premiers `Dockerfile`, `docker-compose.yml`, un `main.go` avec un endpoint `/health` en utilisant une architecture Go "moderne" (`cmd/`, `internal/`, chi, pgx, migrations golang-migrate).

**Ce qui a changé de cap :** l'utilisateur a signalé qu'il existe des supports de cours ESGI précis sur comment coder une API Go (`C:\Users\elyas\Downloads\go\*.pdf`) et un exemple de code fourni en TP. Lecture de ces PDF (`api web introduction.pdf`, `librairies golang.pdf`, `authentification V2.pdf`, `exemple examen final.pdf`) + du `guide_revision.md` d'un projet antérieur comparable (UpcycleConnect, même prof).

**Ce que ça a changé concrètement :** la contrainte du cours est stricte — **aucune librairie externe sauf le driver de base de données**, avec une exception explicite pour `golang-jwt` et `bcrypt` (vus en cours d'authentification). Toute la stack Go a donc été réécrite de zéro :
- Abandon de `chi`, `pgx`, `golang-migrate`, `gofpdf`, `excelize`, `robfig/cron`, `boombuler/barcode`.
- Passage à `net/http` stdlib pur (nouveau routing Go 1.22+, `http.HandleFunc("METHODE /chemin/{$}", handler)`), `database/sql` + `lib/pq`.
- Architecture en package plat, pas de `cmd/`/`internal/` : `app.go` (main) → `app/` (handlers) → `db/` (requêtes SQL) → `models/` (structs) → `config/` (réglages) → `utils/` (JWT, garde de rôle).
- `schema.sql` unique exécuté par Postgres au démarrage, au lieu d'un outil de migration versionnée (pas vu en cours).

**Pourquoi ce changement était important :** le jury de rattrapage va évaluer sur la base de ce qui a été enseigné en cours — une architecture "trop avancée" avec des outils jamais vus en classe aurait été un red flag à l'oral (l'utilisateur doit pouvoir justifier chaque choix technique).

---

## 2026-07-28 (fin de journée) — Premier `docker compose up` fonctionnel + 2 bugs trouvés en testant

**Ce qui a été fait :** premier build complet des 4 conteneurs (postgres, api-go, front-php, nginx), premiers tests curl du endpoint `/health`, puis des 3 endpoints d'authentification (`POST /auth/register`, `POST /auth/login`, `GET /auth/me`).

**Bug #1 trouvé en testant :** en testant une URL 404 sur le front PHP, erreur brute `Failed to open stream: vendor/autoload.php`. Cause : le bind mount `./front-php:/var/www/app` dans `docker-compose.yml` écrase le dossier `vendor/` généré par `composer install` au moment du build de l'image (le dossier local ne contient pas `vendor/`, qui n'est jamais commité). **Fix :** ajout d'un volume nommé séparé (`front_vendor:/var/www/app/vendor`) qui persiste indépendamment du bind mount du code source.

**Bug #2 trouvé en testant :** les pages d'erreur 404/500 personnalisées de nginx ne s'affichaient QUE pour les erreurs générées par nginx lui-même, pas pour celles renvoyées par l'API Go ou PHP-FPM (un vrai message d'erreur brut PHP s'affichait à la place). **Fix :** ajout de `proxy_intercept_errors on;` et `fastcgi_intercept_errors on;` dans la config nginx.

**Pourquoi c'est important à retenir :** ces deux bugs sont des pièges Docker/nginx classiques, pas des erreurs de logique métier — bon exemple à citer si le jury demande "quelles difficultés as-tu rencontrées et comment les as-tu résolues".

---

## 2026-07-29 — Phase 1.2 : middleware/garde de rôle

**Ce qui a été fait :** création de `utils/guard.go` avec la fonction `RequireRole(w, r, ...roles)`, qui vérifie le JWT ET compare le rôle de l'utilisateur à une liste de rôles autorisés. Création d'une route de démonstration `GET /admin/ping` (`app/admin.go`) réservée à `admin_back`/`staff_back`, uniquement pour valider que le système de rôle fonctionne (pas une vraie fonctionnalité métier).

**Comment on y est arrivé :** deux choix discutés avec l'utilisateur avant de coder — (1) vérification manuelle dans chaque handler plutôt qu'un vrai middleware générique qui wrap les routes automatiquement (plus simple à lire/expliquer, conforme au support de cours), (2) la fonction accepte plusieurs rôles à la fois (`rolesAutorises ...string`, un paramètre variadique) plutôt qu'un seul rôle par appel.

**Test réalisé :** création manuelle d'un compte `staff_back` directement en base (pas encore de vrai endpoint de création de staff), puis test des 3 scénarios : sans token → `401`, avec rôle `adherent` → `403`, avec rôle `staff_back` → `200`. Les 3 cas ont fonctionné du premier coup.

---

## 2026-07-29 (suite) — Traduction en français + réécriture des `.md` niveau grand débutant

**Ce qui a été fait :** l'utilisateur a demandé de traduire tous les messages d'erreur/succès du code (qui étaient encore en anglais par habitude, ex: `"Invalid token"`, `"Unauthorized"`) en français, et de réécrire tous les fichiers `.md` en partant du principe qu'il n'a JAMAIS codé en Go — donc en expliquant aussi les concepts de base du langage (pointeurs, structs, gestion d'erreur à deux valeurs de retour, closures, assertions de type...) directement dans les `.md`, pas seulement la logique métier.

**Piège découvert en traduisant :** les clés JWT (`"email"`, `"role"`, `"exp"`, `"iat"`) n'ont PAS été traduites, volontairement — `exp`/`iat` sont des noms standards de la norme JWT, les changer casserait la compatibilité du format.

**Test réalisé :** rebuild complet, revérification de tous les messages d'erreur en français (`"Non autorise"`, `"Jeton invalide"`, `"Acces interdit"`, `"Email deja utilise"`).

---

## 2026-07-30 — Enrichissement du modèle Utilisateur (nom, prénom, date de naissance, téléphone)

**Ce qui a été fait :** l'utilisateur a fait remarquer qu'un utilisateur "réaliste" a besoin de plus d'infos que juste email/mot de passe/rôle. Discussion : est-ce qu'on enrichit la table `utilisateurs` elle-même, ou est-ce qu'on garde `utilisateurs` minimaliste (juste l'auth) et on met les vraies infos dans les tables métier (`commercants`, `benevoles`) qui existent déjà ? Décision : enrichir `utilisateurs` directement (un seul endroit pour "qui est cette personne", valable pour tous les rôles).

**Ajout :** colonnes `nom`, `prenom`, `date_naissance`, `telephone` (toutes nullable) dans `schema.sql`, et dans la struct Go `models.Utilisateur`.

**Piège rencontré et corrigé en testant :** premier essai avec le type `sql.NullString`/`sql.NullTime` (recommandé pour gérer les valeurs NULL en Go). Problème découvert au test : ce type s'encode en JSON comme `{"String":"","Valid":false}` au lieu de `null` — moche et pas pratique pour un front qui consomme l'API. **Fix :** remplacement par des pointeurs Go natifs (`*string`, `*time.Time`), qui s'encodent nativement en `null` quand vides.

**Autre piège rencontré :** la base de données tournait déjà depuis la veille (le volume Docker `pgdata` existait), donc les nouvelles colonnes n'apparaissaient pas automatiquement (`schema.sql` ne s'exécute qu'au tout premier démarrage d'un volume vide). Décision : reset complet du volume `pgdata` plutôt qu'un `ALTER TABLE` manuel (on est encore en tout début de projet, peu de données de test à perdre). Les comptes de test (`test@nomorewaste.fr`, `staff@nomorewaste.fr`) ont été recréés après coup.

**Bug parasite corrigé au passage :** `app.go` contenait une ligne cassée (`http.HandleFunc{"GET /{$}", healthCheck}` avec des accolades au lieu de parenthèses), probablement introduite en éditant le fichier directement dans l'IDE. Corrigé.

**Test réalisé :** rebuild, reset du volume, recréation des comptes de test, revalidation complète du cycle auth (register/login/me) + du middleware de rôle (`/admin/ping`). Tout fonctionne, `null` s'affiche proprement en JSON.

---

## 2026-07-31 — Phase 2 démarrée : module Commerçants (CRUD de base)

**Ce qui a été fait :** création de `models/commercant.go` (struct avec `RaisonSociale` obligatoire, le reste en pointeurs nullable, comme pour `Utilisateur`), `db/commercantsRepository.go` (3 fonctions : `CreateCommercant`, `GetCommercantById`, `ListCommercants`), `app/commercants.go` (3 handlers : `POST /commercants`, `GET /commercants`, `GET /commercants/{id}`), tous protégés par `utils.RequireRole("admin_back", "staff_back")` réutilisé tel quel depuis la Phase 1.

**Nouveauté technique introduite ici :** `CreateCommercant` utilise `RETURNING id` dans la requête SQL (une spécificité Postgres) pour récupérer l'id généré juste après l'insertion, ce qu'on n'avait pas eu besoin de faire pour `CreateUtilisateur`. `ListCommercants` est la première fonction du projet qui lit PLUSIEURS lignes (`Conn.Query` + boucle `for rows.Next()` + `defer rows.Close()`), alors que jusqu'ici on ne lisait qu'une seule ligne à la fois (`QueryRow`).

**Pourquoi ces routes sont protégées par rôle :** le sujet dit "gérer les adhésions des commerçants" du point de vue de l'association (back-office), pas une auto-gestion par le commerçant lui-même — donc ces routes sont réservées au staff, en réutilisant directement le système de garde de rôle codé en Phase 1.2.

**Bug annexe corrigé :** `models/commercant.go` n'était pas formaté au standard Go (colonnes non alignées) — corrigé avec `gofmt -w`.

**État à ce stade :** code compilé (`go build`, `go vet` OK) mais pas encore testé via curl (Docker Desktop s'était arrêté entre-temps). Todo projet mise à jour en conséquence (2.1 marquée partiellement faite).

---

## 2026-07-31 (suite) — Test des endpoints Commerçants + demande de traçabilité accrue

**Ce qui a été fait :** l'utilisateur a relancé Docker Desktop. Rebuild complet (`docker compose up -d --build`), puis tests curl des 5 scénarios : créer un commerçant valide (`201`), créer sans raison sociale (`400`, message "La raison sociale est obligatoire"), lister les commerçants (`200`, tableau JSON avec `null` propre sur les champs vides), obtenir un commerçant existant par id (`200`), obtenir un commerçant inexistant (`404`).

**Fausse alerte investiguée :** le dernier test (commerçant inexistant) renvoyait la page HTML 404 stylisée de nginx au lieu du message JSON "Commercant introuvable" attendu. Après vérification en entrant directement dans le conteneur `api-go` (`docker exec ... curl ...`, en contournant nginx), l'API Go renvoie bien le bon message avec le bon code 404 — c'est nginx qui, via `proxy_intercept_errors on` (voir le fix du 2026-07-28), remplace CE message par la page stylisée, exactement comme prévu et voulu. Pas un bug, juste un comportement à bien comprendre : côté navigateur/nginx, on voit la belle page d'erreur ; côté API brute, le vrai message reste correct.

**Demande de l'utilisateur :** créer ce journal de bord chronologique (ce fichier), à compléter à chaque session de travail, pour garder un maximum de traçabilité sur le pourquoi des décisions — utile car l'utilisateur devra pouvoir relire et comprendre tout le projet même sans accès à une IA le jour de la soutenance ou en cas de coupure.

**Résultat :** module Commerçants (CRUD de base) validé de bout en bout. Todo projet (`Documents/TODO-Mission1-NoMoreWaste.md`) et README (`Code/README.md`) à jour.

---

## 2026-07-31 (suite 2) — Relecture complète des supports de cours restants + module Adhésions

**Ce qui a été fait :** l'utilisateur a rappelé de coder "comme un étudiant de 2e année qui suit ces cours précis" (dossier `C:\Users\elyas\Downloads\go`). Lecture des 4 supports pas encore vus : `bases de langage go.pdf` (syntaxe de base : variables, types, structs, pointeurs, boucles), `http.pdf` (protocole HTTP, méthodes, codes de statut détaillés), `fonctions utiles en go.pdf` (`r.URL.Query().Get`, `r.PathValue`, `strconv.Atoi`), et `audit_swagger.md` (identifié comme appartenant à un AUTRE projet de l'utilisateur, UpcycleConnect — non pertinent ici, Swagger n'est pas mis en place).

**Vérification faite :** relecture du code déjà écrit (`commercants.go`, notamment `strconv.Atoi(r.PathValue("id"))`) pour confirmer qu'il est déjà 100% cohérent avec ces supports de cours — aucune correction nécessaire, juste confirmation.

**Ce qui a été codé ensuite — module Adhésions (Phase 2.2) :** `models/adhesion.go` (struct avec `DateDebut`/`DateFin` en `string` simple, pas `time.Time`, pour rester simple — Postgres convertit lui-même le texte vers sa colonne `DATE`), `db/adhesionsRepository.go` (`CreateAdhesion` avec `RETURNING id`, `GetAdhesionById`, et **premier `UPDATE` du projet** avec `UpdateAdhesion`), deux nouveaux handlers : `CreerAdhesion` (ajouté dans `app/commercants.go`, route imbriquée `POST /commercants/{id}/adhesions`) et `ModifierAdhesion` (nouveau fichier `app/adhesions.go`, route `PUT /adhesions/{id}`).

**Décision technique prise avec l'utilisateur :** `POST` pour créer une adhésion, `PUT` pour la modifier/renouveler (remplace toutes les infos) — conforme aux conventions HTTP vues dans `http.pdf` (POST = "envoi de ressource pour action, souvent insertion", PUT = "envoi d'entité pour remplacement"). Réponse `201 Created` pour le POST, `204 No Content` pour le PUT (convention explicite du cours : "par convention, c'est ce qu'on veut répondre à DELETE/PUT/PATCH").

**Détail de sécurité appliqué :** dans `CreerAdhesion`, après avoir lu le JSON envoyé par le client, le code écrase volontairement `a.CommercantId` avec l'id venant de l'URL (`a.CommercantId = commercantId`) plutôt que de faire confiance à ce que le client aurait pu mettre dans le body — pour empêcher de rattacher une adhésion à un autre commerçant que celui désigné dans l'URL.

**Test réalisé :** rebuild `api-go`, 4 scénarios testés via curl : création d'adhésion pour un commerçant existant (`201`), création pour un commerçant inexistant (`404`, intercepté par nginx comme d'habitude), modification/renouvellement d'une adhésion existante (`204`), modification d'une adhésion inexistante (`404`). Vérification en base via `psql` que le renouvellement (nouvelle `date_fin`, nouveau `montant_cotisation`) a bien été persisté.

**État à ce stade :** Phase 2.1 et 2.2 de la todo marquées faites. Reste pour la Phase 2 : 2.3 (job de rappel automatique — le point le plus attendu à l'oral d'après le sujet) et 2.4 (vue back-office, dépend du front PHP pas encore commencé).

---

## 2026-07-31 (suite 3) — Phase 2.3 : système complet de rappel automatique + campagnes segmentées

**Demande de l'utilisateur :** faire "un truc solide" pour le rappel automatique, afin que le front puisse ensuite "brancher direct" dessus sans réflexion côté API. En creusant la demande, l'utilisateur voulait en réalité un système plus large qu'un simple rappel J-30/J-7 : une relance des "ex-abonnés" (adhésion expirée depuis longtemps), ET un système de campagnes email paramétrable par le staff avec segmentation de population (ville, âge, type d'utilisateur...) depuis le back-office.

**Cadrage fait avant de coder (questions posées à l'utilisateur) :** fournisseur SMTP retenu = Brevo (service tiers), déclenchement = J-30 + J-7 + relance ex-abonnés (vérification quotidienne), routes additionnelles pour que le front puisse consulter/piloter tout ça directement (liste à renouveler, relance manuelle, historique). Puis, pour la partie campagnes : segmentation limitée à commerçants/adhésions (pas aux utilisateurs génériques/bénévoles, hors sujet de cette phase), critères FIXES et prédéfinis combinables (pas de "query builder" totalement libre, pour rester simple et sûr contre l'injection SQL), et un endpoint de déclenchement manuel du job en plus de l'automatisation (indispensable pour une démo).

**Changement de modèle de données important :** l'ancien champ `adhesions.rappel_envoye` (simple booléen) a été retiré et remplacé par une vraie table d'historique `adhesion_rappels` (avec un `type_rappel` : `j30`, `j7`, `ex_abonne`, `manuel`). Raison : un seul booléen ne permettait pas de distinguer QUEL type de rappel a été envoyé — indispensable dès qu'on a plusieurs seuils/types de relance possibles pour la même adhésion. Ajout aussi de `campagnes` (définition d'une campagne avec ses 4 critères optionnels) et `campagne_envois` (historique des envois de campagne).

**Ce qui a été codé :**
- `utils/mailer.go` : `EnvoyerEmail`, construction manuelle d'un message SMTP brut (`net/smtp` stdlib pur, comme exigé par le cours) et connexion à Brevo.
- `config.go` : ajout des fonctions `Smtp*` (host/port/user/password/from), lues depuis `.env`.
- `db/rappelsRepository.go` : `ListAdhesionsARenouveler` (JOIN adhesions+commercants + calcul de date directement en SQL), `ListExAbonnesDepuis`, `RappelDejaEnvoye` (anti-doublon), `EnregistrerRappelEnvoye`, `ListHistoriqueRappels`.
- `utils/scheduler.go` : **première goroutine du projet** — `DemarrerSchedulerRappels` lance une boucle infinie en tâche de fond (`go func() { for { ...; time.Sleep(24 * time.Hour) } }()`), qui tourne EN PARALLÈLE du serveur HTTP sans le bloquer. `ExecuterJobRappels` regroupe les 3 vérifications (J30/J7/ex-abonnés), chacune avec la logique anti-doublon (vérifier `RappelDejaEnvoye` avant d'envoyer, `EnregistrerRappelEnvoye` après un envoi réussi).
- `app/rappels.go` : 4 routes (`GET /adhesions/a-renouveler`, `POST /adhesions/{id}/relancer`, `GET /adhesions/{id}/historique-rappels`, `POST /admin/jobs/rappels-adhesions` pour le déclenchement manuel démo — appelle la MÊME fonction `ExecuterJobRappels` que la goroutine automatique, pas de code dupliqué).
- Système de campagnes : `models/campagne.go`, `db/campagnesRepository.go` (avec `ResoudreDestinatairesCampagne`, la fonction la plus délicate du projet — construit une requête SQL dynamique dont le nombre de conditions `WHERE` dépend des critères réellement définis sur la campagne, chaque critère étant un pointeur Go `nil`/non-`nil`), `app/campagnes.go` (créer, lister, prévisualiser les destinataires AVANT d'envoyer, déclencher réellement avec personnalisation `{{raison_sociale}}` du corps de l'email).

**Point de sécurité expliqué en détail (dans `db/campagnesRepository.go.md`) :** la requête dynamique de `ResoudreDestinatairesCampagne` ne construit QUE la structure SQL (quelles colonnes sont testées) de façon dynamique — jamais les valeurs, qui passent toujours par des paramètres `$N`. Combiné au choix "critères fixes prédéfinis" (pas de colonne choisie librement par l'utilisateur), ça élimine tout risque d'injection SQL sans avoir besoin d'une whitelist complexe.

**Reset de la base nécessaire :** comme pour l'ajout des colonnes utilisateur (voir plus haut), le changement de schéma (retrait de `rappel_envoye`, ajout de 3 nouvelles tables) a nécessité un `docker compose down` + suppression du volume `pgdata` + rebuild complet, puis recréation du compte de test staff.

**Tests réalisés via curl (tous réussis) :**
1. Création d'un commerçant avec email + ville "Paris", et d'une adhésion active à exactement J-30.
2. `GET /adhesions/a-renouveler` : retourne bien cette adhésion avec `jours_restants: 30`.
3. `POST /admin/jobs/rappels-adhesions` : exécute le job sans planter ; les logs montrent une erreur SMTP `535 Authentication failed` (attendu, `.env` a encore des clés Brevo placeholder `change_me`) gérée proprement (pas de crash).
4. `GET /adhesions/1/historique-rappels` : retourne `null` (vide) — confirme qu'aucun rappel n'est enregistré comme envoyé quand l'envoi SMTP a réellement échoué (pas de faux positif dans l'historique).
5. `POST /adhesions/1/relancer` : échoue proprement en `500` (même cause SMTP), passe par la page d'erreur personnalisée nginx comme d'habitude.
6. Création de 2 campagnes avec un critère `critere_ville` différent ("Paris" et "Lyon") : la prévisualisation des destinataires (`GET /campagnes/{id}/destinataires`) trouve bien le commerçant pour "Paris" et retourne une liste vide pour "Lyon" — la segmentation par critère fonctionne correctement.
7. `POST /campagnes/1/declencher` : retourne `{"nombre_envoyes":0}` (échec SMTP comptabilisé correctement, la boucle continue sans planter grâce à `continue` plutôt que `return` sur chaque erreur d'envoi individuelle).

**Décision pour la suite :** l'utilisateur a choisi de ne PAS configurer de vraies clés Brevo tout de suite (logique déjà prouvée solide par les tests ci-dessus) — `.env` reste avec des placeholders `change_me` pour l'instant, à remplir plus tard pour un test d'envoi réellement fonctionnel.

**État à ce stade :** Phase 2.3 marquée faite dans la todo. Il reste 2.4 (vue back-office avec filtre, dépend du front PHP) pour clore complètement la Phase 2. Toutes les routes créées cette session sont prêtes à être consommées telles quelles par le futur front FlightPHP, sans modification côté API nécessaire — c'était l'objectif explicite de l'utilisateur ("après pour le front ça soit du direct").

---

## 2026-07-31 (suite 4) — Phase 3 : gestion des stocks avec code-barre

**Choix fait avant de coder :** proposition de finir la Phase 2.4 (vue back-office PHP) ou d'enchaîner sur la Phase 3 (stocks, encore en API Go pure). L'utilisateur a choisi de continuer sur l'API Go — le front reste pour plus tard.

**Point de cadrage réglé avant de coder :** la table `produits` référence `collectes(id)` via `collecte_id`, mais les collectes (Phase 4) ne sont pas encore codées. Confirmé que `collecte_id` est déjà nullable dans `schema.sql` — décision : on code les produits de façon autonome dès maintenant (sans collecte), le lien sera exploité une fois la Phase 4 codée, sans qu'aucun changement de schéma soit nécessaire.

**Ce qui a été codé :**
- Module emplacements (`models/emplacement.go`, `db/emplacementsRepository.go`, `app/emplacements.go`) : CRUD simple (créer/lister/obtenir), sert de prérequis pour rattacher un produit à un endroit précis.
- Module produits (`models/produit.go`, `db/produitsRepository.go`, `app/produits.go`) : le cœur de la Phase 3.
  - `GetProduitByCodeBarre` : LA fonction qui répond à l'exigence du sujet ("stocké et retrouvable très rapidement") — une simple recherche par égalité, dont la rapidité vient de l'index SQL déjà présent dans `schema.sql` (`idx_produits_code_barre`), pas d'une optimisation côté Go.
  - `ListProduits` : premier filtre combiné optionnel réutilisant la même technique sécurisée que `ResoudreDestinatairesCampagne` (Phase 2.3) — critères ajoutés dynamiquement à la requête SQL uniquement via des paramètres `$N`, jamais de texte collé, donc pas de risque d'injection SQL même avec plusieurs filtres combinables.
  - `CreerProduit` applique des valeurs par défaut (`quantite=1`, `statut="en_stock"`) si le client ne les précise pas, et rejette explicitement un code-barre déjà utilisé (`409 Conflict`) avant même de laisser la contrainte SQL `UNIQUE` réagir.
  - `ListerProduits` (le handler) : une seule route `GET /produits` qui bascule entre DEUX usages différents selon le paramètre reçu — recherche exacte rapide si `code_barre` est fourni (le vrai besoin du sujet), sinon liste filtrable par catégorie/statut (usage back-office plus général).
  - `DeplacerProduit` introduit un DTO dédié (`deplacementProduitDto`) qui ne contient QUE les 2 champs modifiables par cette action (emplacement, statut) — empêche qu'un client puisse accidentellement écraser d'autres champs du produit (comme le code-barre) via cette route.

**Test réalisé :** rebuild `api-go` (pas de changement de schéma cette fois, donc pas de reset du volume Postgres nécessaire). Scénarios testés via curl : création d'un emplacement (201), création d'un produit avec code-barre (201), tentative de doublon de code-barre (409, rejeté avant même d'atteindre la base), recherche rapide par code-barre (200, trouve bien le produit), recherche par code-barre inexistant (404), déplacement/changement de statut (204), vérification que le changement est bien persisté, liste filtrée par statut (trouve le produit avec son nouveau statut, liste vide pour l'ancien statut) — tous réussis du premier coup.

**État à ce stade :** Phase 3 entièrement marquée faite (3.1 à 3.4) dans la todo. Reste en Phase 2 : 2.4 (vue back-office, dépend du front PHP). Prochaines étapes logiques : Phase 4 (collectes, qui viendra enrichir `produits` via `collecte_id`), ou Phase 5 (bénévoles), ou enfin attaquer le front PHP.

---

## 2026-07-31 (suite 5) — Stratégie de séquencement + Phase 4 : collectes

**Décision de l'utilisateur :** terminer TOUTE l'API Go (Phases 4 à 7, puis consolidation 10) avant de basculer sur le front FlightPHP. Les tâches liées au front (1.3, 2.4, Phase 8, Phase 9) restent en attente jusqu'à la fin du travail sur l'API. Documenté explicitement en haut de `Documents/TODO-Mission1-NoMoreWaste.md` pour garder une trace de ce choix.

**Cadrage avant de coder :** le sujet demande de gérer les collectes avec un lien vers les produits rapportés. Question réglée : coder un CRUD complet des collectes ET une vraie route `POST /collectes/{id}/produits` qui crée le produit DIRECTEMENT rattaché à la collecte (plutôt qu'un simple CRUD minimal sans toucher aux produits) — plus fidèle au flux réel (le produit est scanné pendant la collecte, il n'existe pas avant).

**Ce qui a été codé :**
- `models/collecte.go` : struct avec un cas particulier jamais rencontré avant — `CommercantId` ET `ParticulierNom` sont TOUS LES DEUX optionnels, mais la règle métier veut qu'au moins un des deux soit fourni (vérifié dans le handler, pas dans la struct).
- `db/collectesRepository.go` : CRUD classique, plus `UpdateStatutCollecte` qui remplit AUTOMATIQUEMENT `date_realisee` avec `now()` (côté SQL) quand le statut passe à `"realisee"` — sans que le client ait besoin d'envoyer cette date lui-même. Plus `ListProduitsParCollecte` pour retrouver tous les produits d'une collecte donnée.
- `app/collectes.go` : 6 handlers. Le plus important, `AjouterProduitCollecte`, réutilise directement `db.CreateProduit` et `db.GetProduitByCodeBarre` de la Phase 3 (pas de duplication de code) — même logique de valeurs par défaut et de vérification anti-doublon de code-barre que `CreerProduit`, avec en plus l'écrasement volontaire de `CollecteId` avec l'id venant de l'URL (même principe de sécurité que pour `CreerAdhesion` en Phase 2).

**Test réalisé :** rebuild `api-go` (pas de changement de schéma). Scénarios testés via curl : création d'une collecte pour un commerçant (201), validation qu'il faut au moins commerçant OU particulier (400), ajout d'un produit à la collecte (201, bien rattaché), liste des produits de la collecte, changement de statut vers "realisee" (204) avec vérification que `date_realisee` a été rempli automatiquement par Postgres, tentative d'ajout de produit à une collecte inexistante (404), liste filtrée par statut (trouve la collecte "realisee", liste vide pour "demandee") — tous réussis du premier coup.

**État à ce stade :** Phase 4 entièrement marquée faite. Restent côté API : Phase 5 (tournées + PDF), Phase 6 (bénévoles), Phase 7 (services + Excel), puis la consolidation (Phase 10). Le front (1.3, 2.4, Phase 8, Phase 9) reste explicitement en attente jusqu'à la fin de l'API.

---

## 2026-07-31 (suite 6) — Phase 6 : bénévoles (candidature, validation conditionnée, compétences)

**Cadrage avant de coder :** deux points réglés avec l'utilisateur. (1) Pour la validation des "conditions" citées par le sujet : une route dédiée pour valider un document précis (`PUT /benevoles/{id}/documents/{docId}/validation`), puis une route de validation du bénévole qui VÉRIFIE que tous les documents sont déjà validés avant d'autoriser le passage au statut "valide" — plutôt qu'une validation simplifiée sans contrôle automatique. (2) Pour les compétences : une route par compétence (`POST`/`DELETE /benevoles/{id}/competences/{competenceId}`), plutôt qu'un remplacement global de toute la liste en un seul appel — plus proche des conventions REST du cours (une action par appel).

**Ce qui a été codé :**
- `models/benevole.go` : trois structs (`Benevole`, `Competence`, `BenevoleDocument`).
- `db/benevolesRepository.go` : CRUD classique, `UpdateStatutBenevole` (remplit `date_validation` automatiquement, même principe que `date_realisee` sur les collectes), gestion des documents, et surtout **`TousLesDocumentsSontValides`** — utilise `COUNT(*) FILTER (WHERE valide = true)`, une fonctionnalité SQL qui compte en une seule requête à la fois le nombre total de documents ET le nombre de documents validés, pour vérifier que les deux nombres sont égaux (et qu'il y a au moins un document, pour éviter qu'un bénévole sans aucune condition enregistrée soit validé instantanément).
- `app/benevoles.go` : 10 handlers, dont **`PoserCandidature`, la toute première route du projet sans `utils.RequireRole`** — le sujet dit explicitement que n'importe qui doit pouvoir candidater, donc pas d'authentification requise, exactement comme `POST /auth/register`. `ValiderBenevole` est le point central : il appelle `TousLesDocumentsSontValides` AVANT d'autoriser le passage à "valide", et refuse sinon avec un message clair. Première utilisation de la méthode `DELETE` du projet (`RetirerCompetenceBenevole`), et première route avec deux identifiants variables dans la même URL (`{id}` et `{docId}`, ou `{id}` et `{competenceId}`).

**Test réalisé :** rebuild `api-go`. Scénario complet testé via curl, dans l'ordre logique du workflow réel : candidature publique sans token (201) → tentative de validation du bénévole SANS document (400, refusé comme prévu) → ajout d'un document "permis_conduire" (201) → nouvelle tentative de validation AVEC un document non encore validé (400, toujours refusé) → validation du document par le staff (204) → validation du bénévole qui réussit CETTE FOIS (204) → vérification que `date_validation` a bien été remplie automatiquement. Puis gestion des compétences : ajout (204), doublon rejeté (409), liste, retrait (204) — tous les tests ont réussi du premier coup, y compris la séquence de refus/succès qui prouve que la logique de validation conditionnée fonctionne exactement comme prévu.

**État à ce stade :** Phase 6 entièrement marquée faite. Restent côté API : Phase 5 (tournées + PDF), Phase 7 (services + Excel), puis consolidation (Phase 10). Le champ `BenevoleId` sur `Collecte` (Phase 4) peut maintenant être réellement utilisé avec de vrais bénévoles validés et compétents.

---

## 2026-07-31 (suite 7) — Analyse de conformité au sujet, puis Phase 7 : services & plannings

**Pause d'analyse demandée par l'utilisateur :** relecture intégrale du sujet PDF, puis inventaire des 41 routes codées pour vérifier l'adéquation. **Conclusion : aucune dérive hors-sujet** — chaque module correspond à une puce du cahier des charges. Deux points signalés honnêtement : (1) le système de campagnes segmentées (Phase 2) est le seul gros ajout non demandé (l'utilisateur a choisi de le garder comme bonus assumé) ; (2) `GET /admin/ping` est une route purement technique de démonstration, sans valeur métier.

**Suite à cette analyse**, l'utilisateur a demandé que la documentation distingue visuellement le demandé de l'ajouté : les 6 documents de `Documents/documentation-par-phase/` ont été enrichis d'un marquage 🟥/🟧/🟦 sur chaque fonctionnalité, avec justification courte (souvent une citation directe du sujet pour les 🟥), plus un avertissement en tête de la Phase 2 sur son bonus.

### Phase 7 codée dans la foulée

**Cadrage avant de coder :** deux décisions prises avec l'utilisateur. (1) Le sujet demande des plannings "sous forme de fichiers Excel", mais le cours ESGI interdit les librairies externes (donc pas d'`excelize`) → génération d'un **CSV** avec `encoding/csv` (package standard, cité dans l'énoncé d'examen du cours), qu'Excel ouvre nativement. (2) L'envoi quotidien réutilise la goroutine existante des rappels d'adhésion, plus un endpoint de déclenchement manuel pour la démonstration.

**Problème détecté avant de coder :** pour envoyer un planning par email à un bénévole, il faut son adresse — or la table `benevoles` n'en stockait aucune (elle pointait vers `utilisateurs` par un lien optionnel que la route de candidature ne remplissait jamais). Décision : ajouter une colonne `email` directement sur `benevoles`, renseignable dès la candidature, cohérent avec `commercants` qui a déjà son propre champ email. A nécessité un reset du volume Postgres.

**Ce qui a été codé :**
- `models/service.go` : 4 structs (`Service`, `CreneauService`, `InscriptionService`, `LignePlanning`).
- `db/servicesRepository.go` : CRUD des trois entités, le contrôle de capacité (`CompterInscriptionsActives`, qui exclut les inscriptions annulées), et **la requête la plus complexe du projet** — `ListPlanningDuJour` joint TROIS tables (`creneaux_service` + `benevoles` + `services`) pour rassembler tout ce qu'il faut écrire dans un planning.
- `utils/planning.go` : génération du CSV. Deux détails qui font que le fichier s'ouvre correctement dans Excel français : un **BOM UTF-8** en tête (sinon les accents sont illisibles) et un **séparateur point-virgule** (sinon toute la ligne atterrit dans une seule colonne). Plus deux fonctions de formatage qui transforment les dates techniques Postgres (`2026-07-31T00:00:00Z`) en dates lisibles (`31/07/2026`).
- `utils/mailer.go` étendu : `EnvoyerEmailAvecPieceJointe`, qui construit un message **MIME multipart** à la main (le fichier encodé en base64, car un email ne transporte que du texte) — stdlib pur (`mime/multipart`, `encoding/base64`, `net/textproto`).
- `utils/schedulerPlanning.go` : le job quotidien. Point technique intéressant — la requête SQL retourne une liste plate (une ligne par créneau), il faut donc **regrouper par bénévole avec une `map`** pour n'envoyer qu'UN email par personne contenant tous ses créneaux, plutôt qu'un email par créneau.
- `app/services.go` : 11 handlers. Le plus important, `AffecterBenevoleCreneau`, applique **deux règles métier cumulatives** issues du sujet : le bénévole doit être au statut `"valide"` (donc avoir passé toutes ses conditions, lien direct avec la Phase 6) ET posséder la compétence exigée par le service, s'il en exige une. Les routes de catalogue (`GET /services`) sont **publiques**, cohérent avec "services accessibles aux adhérents".

**Défaut de qualité corrigé pendant les tests :** la première version du CSV affichait les dates au format brut Postgres (`2026-07-31T00:00:00Z;0000-01-01T14:00:00Z`) — illisible pour un bénévole. Corrigé avec les fonctions de formatage, résultat final : `31/07/2026;14:00;17:00`.

**Test réalisé :** reset du volume, rebuild complet, recréation des données de test. Scénario complet validé via curl : création service (avec compétence requise) + créneau, catalogue accessible sans token, **affectation refusée car bénévole non validé (400)** → validation du bénévole → **affectation refusée car compétence manquante (400)** → ajout de la compétence → **affectation réussie (204)**. Puis inscriptions avec capacité 2 : 2 réussites puis **409 "Ce creneau est complet"** au troisième. Enfin, génération du CSV avec dates formatées et déclenchement du job d'envoi (trouve bien le bénévole avec son email, échoue proprement sur l'authentification SMTP placeholder).

**État à ce stade :** Phase 7 entièrement marquée faite. Il ne reste qu'une seule phase métier côté API : **Phase 5 (tournées de distribution + PDF récapitulatif)**. Ensuite : consolidation (Phase 10), puis tout le front PHP (1.3, 2.4, Phases 8 et 9), le déploiement (Phase 11) et le packaging (Phase 12).

---

## 2026-07-31 (suite 8) — Phase 5 : tournées de distribution + PDF écrit à la main

**Contexte :** dernière phase métier de l'API. L'utilisateur a demandé de continuer seul en prenant les choix recommandés, sans poser de questions.

**Le défi technique de cette phase :** le sujet exige "un récapitulatif au format PDF" pour chaque livraison, mais le cours ESGI interdit toute librairie externe — donc pas de `gofpdf`. Décision prise : **écrire le fichier PDF nous-mêmes, octet par octet**, plutôt que de se rabattre sur du HTML ou du texte qui aurait été moins fidèle au sujet.

**Comment ça marche :** un PDF n'est pas un format binaire opaque, c'est un fichier texte structuré. `utils/pdf.go` produit : l'en-tête `%PDF-1.4`, cinq objets numérotés (catalogue → liste de pages → page A4 → flux de contenu → police Helvetica), des instructions de dessin (`BT /F1 12 Tf 60 780 Td (texte) Tj ET`), une table `xref` donnant la position en octets de chaque objet (format rigide sur 10 chiffres), un trailer et `%%EOF`. Deux subtilités : dans un PDF l'origine (0,0) est en **bas à gauche** (d'où un `positionY` qui décroît), et les parenthèses du texte doivent être échappées car elles délimitent les chaînes. Limite assumée et documentée : les accents sont convertis en équivalents simples, gérer l'UTF-8 complet demanderait d'embarquer une police entière avec sa table d'encodage.

**Ce qui a été codé :** `models/tournee.go` (6 structs), `db/tourneesRepository.go` (15 fonctions — le plus fourni du projet), `utils/pdf.go`, `app/tournees.go` (12 handlers).

**Le handler central, `CloturerLivraison`** (`POST /tournee-etapes/{id}/livraison`), enchaîne cinq opérations : refus si une livraison existe déjà pour cette étape (409), vérification que **tous** les produits existent AVANT d'en insérer aucun (sinon on créerait une livraison à moitié remplie), création de la livraison, rattachement des produits **qui passent automatiquement au statut "distribue"** dans le stock, et marquage de l'étape comme livrée avec l'heure réelle remplie par Postgres (`CURRENT_TIME`).

**La phase qui connecte le plus de modules :** Phase 3 (les produits livrés sortent du stock), Phase 6 (seul un bénévole "valide" peut conduire une tournée), Phase 4 (le circuit complet du sujet est bouclé : collecte chez les commerçants → stockage → redistribution).

**Incident en cours de route :** Docker Desktop s'est arrêté pendant le développement. J'ai continué à rédiger la documentation en attendant, puis repris les tests une fois le service revenu — les données de test avaient survécu (le volume n'avait pas été supprimé).

**Test réalisé :** création bénéficiaire (association caritative) → tournée avec le bénévole validé de la Phase 7 → étape d'arrêt → création de 2 produits en stock → clôture de la livraison (201, avec l'URL du PDF) → vérification des effets de bord : les 2 produits sont bien passés en `"distribue"`, l'étape est `"livre"` avec une heure réelle automatique → tentative de deuxième clôture refusée (409) → téléchargement du PDF : **1584 octets, signature `%PDF-1.4` en tête, `%%EOF` en fin, et tout le contenu attendu** (en-tête NO MORE WASTE, numéro et date de livraison, bloc bénéficiaire, tableau des 2 produits avec quantités, total "5 article(s) sur 2 reference(s)", ligne de signature).

**Fausse alerte pendant les tests :** ma commande d'extraction du texte du PDF ne montrait pas le nom du bénéficiaire ni la ligne de total. Vérification faite : ces deux lignes contiennent des parenthèses (`Restos du Coeur Paris 11 (association caritative)`), et mon `grep` de test s'arrêtait au premier `)`. Les lignes étaient bien présentes dans le fichier, avec leurs parenthèses correctement échappées — c'était l'outil de test qui était trop naïf, pas le code.

**État à ce stade : toute l'API Go est terminée.** Les 7 phases métier (auth, commerçants/adhésions, stocks, collectes, tournées, bénévoles, services) sont codées, testées et documentées. Restent : la consolidation API (Phase 10), tout le front PHP (1.3, 2.4, Phases 8 et 9), le déploiement sur serveur réel (Phase 11) et le packaging (Phase 12).

---

## 2026-07-31 (suite 9) — Phase 10 : consolidation de l'API

**Pourquoi cette phase avant le front.** Toute l'API était fonctionnelle, mais écrite module par module sur plusieurs sessions. Ce genre de découpage produit forcément des incohérences : une convention prise en Phase 1 et oubliée en Phase 5, un cas d'erreur bien traité ici et bâclé ailleurs. J'ai préféré repasser dessus **avant** d'écrire le front, parce que certaines corrections deviennent coûteuses une fois qu'un client consomme l'API. Aucune route ajoutée dans cette phase : c'est de la relecture.

**J'ai commencé par un audit, pas par du code.** Recherche systématique des mauvais signes : erreurs ignorées, `panic`, messages SQL renvoyés au client, `sql.ErrNoRows` mal géré. Deux bonnes nouvelles d'abord : **zéro** `http.Error(w, err.Error(), ...)` dans tout le projet (aucune fuite de noms de tables vers le client) et les 18 repositories gèrent correctement `sql.ErrNoRows`, donc une ressource inexistante n'a jamais provoqué de 500. Il n'y avait pas besoin de tout refaire.

**Le vrai problème était ailleurs, et il concernait 101 endroits.** Partout, le motif était :

```go
if err != nil {
    http.Error(w, "Erreur de recuperation du produit", http.StatusInternalServerError)
    return
}
```

En le regardant à froid, ça saute aux yeux : la variable `err` — celle qui contient **la vraie explication** — n'est utilisée nulle part. Elle est jetée. Autrement dit, si l'API plante pendant la soutenance, je n'ai strictement aucun moyen de savoir pourquoi. C'est ça, un "500 silencieux" : le serveur crie qu'il a mal, mais ne dit pas où.

J'ai écrit `utils.ErreurServeur(w, r, message, err)` qui sert les deux publics d'un coup : `log.Printf` écrit la cause technique dans les logs du serveur (privé, pour moi), `http.Error` renvoie le message court au client (public, sans détail exploitable par un attaquant). Puis remplacement des 101 occurrences.

**En testant la correction, j'ai trouvé un défaut que je ne cherchais pas.** J'ai provoqué une erreur volontaire (`emplacement_id: 99999`) pour vérifier que le log fonctionnait. Il fonctionnait — mais j'ai réalisé que la réponse était un **500**. Or 500 signifie "le serveur a un bug". Ici le serveur va très bien : c'est la donnée envoyée par le client qui est fausse. Le code correct est **400**, avec un message qui dit quoi corriger.

Comme les 101 handlers passent tous par `ErreurServeur`, il a suffi de modifier **cette fonction seule** : elle regarde si l'erreur vient de PostgreSQL avec le code `23503` (violation de clé étrangère) et répond alors 400. Une modification, 101 handlers corrigés — c'est le bénéfice d'avoir centralisé juste avant.

**Sauf que ça ne marchait pas, et la raison est subtile.** `errors.As` ne trouvait jamais l'erreur PostgreSQL. En regardant les repositories, j'ai compris : ils écrivaient `fmt.Errorf("CreateProduit : %v", err.Error())`. Le verbe `%v` transforme l'erreur en **simple texte** — le message affiché reste identique, mais l'erreur d'origine est **détruite**, il n'en reste qu'une phrase. Il faut `%w` (*wrap*, emballer), qui garde l'erreur d'origine à l'intérieur et permet de la retrouver plus tard.

C'est le genre de bug invisible : les logs étaient identiques dans les deux cas, le problème n'apparaissait qu'au moment de vouloir **inspecter** l'erreur. J'ai converti les **97 lignes** concernées. Règle que je retiens : **quand on enveloppe une erreur, c'est toujours `%w`**.

**Trois autres corrections dans la foulée.** Le health check faisait `panic(err)` si la base tombait — un panic dans un handler HTTP donne au client une connexion coupée, sans message ni code, et c'était sur la route dont le seul rôle est de dire si tout va bien. Remplacé par un **503** ("je vais bien, mais un service dont je dépends est tombé"), qui n'a pas le même sens que 500 ("mon code a un bug"). Ensuite, j'ai corrigé la seule incohérence de nommage du projet : le champ de connexion s'appelait `password` alors que les ~85 autres champs de l'API sont en français. Renommé en `mot_de_passe`. Je l'ai fait **maintenant** précisément parce que changer un nom de champ JSON casse le contrat de l'API : tant qu'aucun front ne la consomme, ça ne coûte rien ; après, il aurait fallu modifier les deux ensemble.

**Le troisième défaut, trouvé aussi par accident.** En testant une erreur d'API avec curl, j'ai reçu… une page HTML complète. Le message calculé par mon code Go avait été jeté et remplacé par la page d'erreur du site. La cause : `proxy_intercept_errors on;` sur `location /api/` — un réglage que j'avais ajouté en Phase 0 pour faire marcher les pages d'erreur personnalisées, et qui est **le bon réglage pour un site et le mauvais pour une API**. Un humain avec un navigateur veut une jolie page ; un programme qui appelle l'API veut un message exploitable. Passé à `off` pour `/api/`, conservé pour le site — l'exigence du sujet reste remplie, elle porte sur le site.

**Piège Docker au passage :** `docker compose restart nginx` ne changeait rien à ma correction. La conf nginx est **copiée dans l'image** (`COPY` dans le Dockerfile), pas montée en volume — un restart relance donc l'ancienne configuration. Il faut `docker compose up -d --build nginx`. J'ai perdu un moment à croire que mon changement était faux alors qu'il n'avait jamais été appliqué.

**Le livrable de documentation : la collection Postman.** J'ai produit `NO-MORE-WASTE.postman_collection.json` — les 63 routes en 14 dossiers, avec pour chacune un corps d'exemple, le rôle requis, les variables `base_url`/`token` et un script qui capture le JWT automatiquement à la connexion. Contrôle croisé automatique route par route : **63 routes déclarées dans `app.go`, 63 couvertes, aucun trou**.

**Puis j'ai voulu vérifier que la doc ne mentait pas.** Une documentation fausse est pire que pas de documentation. J'ai donc écrit un script qui lit la collection et **rejoue les 66 requêtes** contre l'API réelle, sur une base remise à zéro. Premier lancement : **43 OK sur 64**. Le script a été bien plus utile que prévu — il a mis au jour :
- les deux erreurs 500 mentionnées plus haut (que j'ai corrigées en 400) ;
- un enchaînement métier **impossible** dans ma propre documentation : je validais un bénévole avant de valider ses documents, ce que l'API refuse à juste titre — c'est la règle de la Phase 6, mon exemple était faux, pas le code ;
- trois exemples réellement faux : `montant_cotisation` est une chaîne et non un nombre (choix assumé : pour de l'argent, le texte évite les erreurs d'arrondi des flottants), les champs de campagne s'appellent `sujet_email`/`corps_email`, et surtout `POST /collectes/{id}/produits` **crée** un produit rattaché à la collecte (le scan sur le terrain) au lieu d'en rattacher un existant — je m'étais trompé sur le contrat de ma propre route.

**J'ai aussi réordonné la collection selon les dépendances réelles**, pas selon l'ordre des phases : les bénévoles avant les collectes (une collecte référence un bénévole), les compétences avant les services (l'affectation exige la compétence). Résultat : la collection se rejoue de haut en bas sur une base vide et tout s'enchaîne. Score final : **66 requêtes, 66 OK, 0 échec**, avec vérification métier de bout en bout (les produits livrés bien passés en `distribue`, PDF de 1595 octets valide). Le seul échec restant est marqué comme attendu : la relance manuelle envoie un vrai email et échoue tant que les identifiants SMTP du `.env` sont les valeurs par défaut.

**Ce que je n'ai pas fait, volontairement : Swagger.** Il était noté "bonus optionnel" dans ma todo. La collection Postman remplit déjà le rôle de documentation des endpoints ; Swagger imposerait soit une librairie externe (interdite par le cours), soit un fichier OpenAPI de plusieurs centaines de lignes écrit à la main qui doublonnerait la collection. C'est un choix à assumer, pas un oubli.

**État à ce stade : l'API Go est terminée ET consolidée.** Prochaine étape : le front FlightPHP, qui débloque d'un coup les points restants 1.3 (page de connexion multilingue), 2.4 (vue back-office), Phase 8 (multilingue) et Phase 9 (séparation back-office / front-office).

---

## 2026-08-01 — Le front FlightPHP : socle, multilingue, back-office

**Contexte.** Je suis en voyage et je ne peux pas me poser pour faire les sessions de live coding. J'ai donc choisi d'avancer un maximum sur le code, en gardant le même niveau de documentation — c'est justement ce que je pourrai lire hors connexion pendant les trajets.

**Pourquoi le front maintenant.** L'API était finie et consolidée. Le front débloque à lui seul **10 des 15 items restants** de ma todo : 1.3 (connexion multilingue), 2.4 (vue back-office), toute la Phase 8 (multilingue) et toute la Phase 9 (back-office / front-office). C'était de loin le chemin le plus rentable.

**Point de départ :** `public/index.php` faisait 11 lignes et affichait « NO MORE WASTE - front en ligne ». Les dossiers créés en Phase 0 étaient tous vides.

**Premier choix : retirer Guzzle.** Guzzle (la librairie HTTP la plus utilisée en PHP) était déclarée dans `composer.json` depuis le début. Je l'ai retirée au profit de **cURL natif**, pour la même raison que le Go en bibliothèque standard : `ApiClient.php` fait 130 lignes que je peux expliquer entièrement, du premier `curl_init` au dernier `return`. Avec Guzzle, la réponse à « comment ta requête part-elle ? » aurait été « la librairie s'en occupe » — ce qui ne se défend pas à l'oral. Bénéfice secondaire : une dépendance de moins dans `vendor/`, il ne reste plus que FlightPHP.

**Le piège que je n'avais pas anticipé : `localhost` ne veut pas dire la même chose partout.** Dans mon navigateur, l'API est à `http://localhost:8080/api`. Mais le code PHP s'exécute **dans le conteneur** `front-php`, et pour lui `localhost` désigne… lui-même. Il chercherait l'API chez lui. La bonne adresse est `http://api-go:8080` : Docker donne à chaque conteneur un nom utilisable comme adresse réseau, identique au nom du service dans `docker-compose.yml`. Il y a donc deux chemins différents vers la même API — le navigateur passe par nginx, le front PHP parle directement à l'API puisqu'il est déjà à l'intérieur du réseau Docker.

**Le multilingue (Phase 8, exigence 🟥 du sujet).** J'ai écrit `Langue.php` + 4 fichiers de traduction (fr, en, it, pt), **40 clés chacun**. Le principe : aucun texte n'est écrit en dur dans une page, on écrit une clé et le système va chercher le texte de la langue active. La priorité de choix est `?lang=xx` → session → en-tête `Accept-Language` du navigateur → français : **du plus explicite au plus deviné**, parce qu'un clic volontaire doit toujours l'emporter sur une déduction automatique. Si un Italien clique sur « FR », le site doit rester en français même si son navigateur est configuré en italien.

J'ai mis un **double filet de sécurité** dans la fonction de traduction : clé absente de la langue active → on retombe sur le français ; absente partout → on affiche **la clé elle-même** à l'écran. C'est moche exprès : une erreur visible est une erreur qui sera corrigée, alors qu'un trou blanc dans la page italienne pourrait passer inaperçu pendant des semaines.

**Ce que j'ai compris en traduisant :** traduire n'est pas remplacer mot à mot. Le champ « SIRET » n'existe pas hors de France — un commerçant italien a une *Partita IVA*, un portugais un *NIF*. Le multilingue est une **adaptation locale**, pas un dictionnaire. C'est un bon exemple à ressortir si on me demande ce que « site multilingue » implique vraiment.

**La séparation back-office / front-office (Phase 9, exigence 🟥).** Deux fichiers de routes distincts, toutes les adresses internes sous le préfixe `/back`, et `Auth::exigerStaff()` appelé **en première ligne de chaque contrôleur** de back-office. J'aurais pu brancher une protection automatique sur tout `/back` ; je ne l'ai pas fait volontairement, pour la même raison que `utils.RequireRole` côté Go : **la protection se voit en lisant le contrôleur**. Avec un mécanisme invisible, savoir si une page est protégée oblige à aller vérifier ailleurs, et un oubli ne se remarque pas. La contrepartie, c'est que je dois y penser à chaque nouveau contrôleur.

J'ai aussi ajouté deux thèmes de couleur (vert pour le public, ocre pour l'interne). Ce n'est pas décoratif : pendant une démonstration, on voit instantanément dans quel espace on se trouve, ce qui matérialise à l'écran la séparation que le sujet demande.

**Une décision de sécurité que je dois savoir justifier.** Après la connexion, le front fait un **deuxième appel** à `GET /auth/me/` pour récupérer le rôle. Le rôle est pourtant déjà écrit dans le JWT — j'aurais pu le décoder directement, ç'aurait été plus rapide. Mais **un JWT n'est pas chiffré** : c'est du base64 que n'importe qui peut lire *et fabriquer*. Ce qui le rend fiable, c'est sa signature, vérifiable uniquement avec la clé secrète qui vit dans l'API. Décoder sans vérifier la signature reviendrait à croire un badge sans regarder s'il est authentique : il suffirait de fabriquer un jeton disant `"role": "admin_back"`. Nuance à connaître : même en trompant le front de cette façon, on n'obtiendrait rien de plus, parce que l'API revérifie le rôle à chaque requête. **Le front affiche ou masque ; la vraie barrière est côté API.**

Même logique pour le lien « Back-office » masqué dans le menu d'un adhérent : c'est du **confort**, pas de la sécurité. Il reste accessible en tapant l'adresse à la main — ce qui le bloque, c'est la garde côté serveur. Il faut toujours les deux, et ne jamais compter sur le premier seul.

**Deux pièges rencontrés pendant les tests.**

Le premier : ma page 404 renvoyait un **code 200 avec une page vide**. J'utilisais la fonction PHP habituelle `http_response_code(404)`, qui ne fonctionne pas ici : Flight construit sa **propre** réponse et l'envoie à la fin avec son statut à lui (200 par défaut), ce qui écrase le mien. Il faut passer par son objet réponse : `Flight::response()->status(404)->send()`. Une fois le vrai 404 envoyé, nginx l'intercepte et affiche la page personnalisée du sujet.

Le second, que j'ai anticipé en l'écrivant mais qui mérite d'être noté : le `exit` après une redirection est **obligatoire**. `header('Location: ...')` ne fait qu'ajouter une consigne — PHP continue d'exécuter le script et génère la page réservée en entier. Le navigateur ne l'affiche pas, mais `curl` la lit sans difficulté. Sans `exit`, une redirection ne protège rien du tout.

**Fausse alerte pendant les tests :** j'ai cru un moment que la langue ne persistait pas en session (la page suivante s'affichait en français). En refaisant le test proprement, elle persistait très bien — c'était mon enchaînement de commandes `&&` qui avait court-circuité et mélangé les sorties. Comme pour le PDF en Phase 5, c'était l'outil de test qui était fautif, pas le code. Leçon qui commence à se répéter : quand un résultat surprend, vérifier d'abord la commande de test.

**Vérifications faites :** les 4 langues sur `/connexion` (Connexion / Sign in / Accedi / Entrar), la détection via `Accept-Language`, la persistance en session, la cohérence des 4 fichiers de langue (40 clés, aucun manque), `/back` sans connexion → redirigé, connexion staff → `/back`, connexion adhérent → `/`, adhérent tentant `/back` → bloqué avec message traduit et lien masqué, liste des commerçants avec filtre par ville sur un jeu de test réparti sur Paris/Naples/Porto, et route inconnue → 404 personnalisée.

**Un écart à assumer sur l'item 2.4.** Ma todo disait « filtre par statut ». J'ai filtré par **ville**. Le statut appartient à l'**adhésion**, pas au commerçant, et `GET /commercants/` ne le renvoie pas : filtrer dessus demanderait un appel d'API par commerçant, ou une nouvelle route côté API. La ville est directement disponible et tout aussi pertinente pour une association implantée dans 7 villes de 4 pays. Le mécanisme de filtrage est identique, seul le champ change — c'est ce que je dirai si on me pose la question.

**État à ce stade :** le **socle** du front est en place et testé, mais ce n'est qu'un socle. Restent les écrans métier : 2 pages publiques (catalogue des services, candidature bénévole — toutes deux consomment des routes d'API publiques déjà prêtes) et 5 modules de back-office (bénévoles, collectes, stocks, tournées, services). Chaque nouvel écran suivra exactement la structure de `CommercantsController` : garde → appel API → normalisation → vue.

---

## 2026-08-02 — Passage à Bootstrap, et un script de test enfin rejouable

**Le script de test n'était bon qu'une fois.** En le relançant, j'ai obtenu `59 OK | 7 en echec`, tous des `409 Conflict` : code-barre déjà pris, email déjà inscrit, compétence déjà attribuée, livraison déjà clôturée.

L'API avait entièrement raison — refuser un doublon, c'est exactement ce qu'on lui demande. C'était **mon script** le fautif. Mais un outil de test qui affiche « 7 en échec » alors que tout va bien est pire qu'inutile : il fait douter d'un code qui marche. C'est le vrai défaut, et c'est ce que j'ai corrigé.

Le script vide maintenant les données métier avant de commencer. Trois détails que je dois savoir expliquer :

- **`RESTART IDENTITY`** remet les compteurs d'identifiants à 1. Sans lui, après cinq lancements, le premier commerçant créé porterait le numéro 6 — alors que la collection demande `/commercants/1`. Tout échouerait en 404, avec un motif quasi impossible à deviner.
- **`CASCADE`** laisse PostgreSQL gérer l'ordre des suppressions malgré les liens entre tables (livraison → étape → tournée → bénévole). Sans lui, il faudrait maintenir cet ordre à la main à chaque nouvelle table.
- **`langues` et `competences` ne sont pas vidées.** Ce sont des données de **référence**, insérées une seule fois par `schema.sql` à la création de la base — elles ne reviendraient donc pas toutes seules, et les tests des compétences casseraient.

La distinction à retenir : **données métier** (créées par l'usage, jetables) contre **données de référence** (le socle, à préserver). Vérifié en lançant le script trois fois d'affilée : 66/66 à chaque passage. J'ai aussi ajouté `--garder-donnees` pour pouvoir tester sur un jeu de données existant.

**Changement de consigne : le front doit être fait uniquement avec Bootstrap, icônes comprises.** J'avais écrit un `style.css` de 250 lignes à la main, dans la logique du reste du projet. Je l'ai supprimé et refait les 5 vues en classes Bootstrap.

Ça change mon argumentaire à l'oral, et c'est important de ne pas me tromper de discours. Avant : « j'ai tout écrit moi-même, je peux expliquer chaque ligne ». Maintenant : « j'utilise le standard du marché, ce qui me donne un rendu cohérent, responsive et accessible sans réinventer ce qui existe ». Les deux se défendent, mais le second est plus proche de la pratique réelle en entreprise. La phrase à retenir : **le front utilise Bootstrap, l'API reste en Go sans framework** — on ne réinvente pas la mise en forme, mais on maîtrise le métier.

**Décision : Bootstrap stocké en local, pas chargé depuis un CDN.** Deux lignes vers jsdelivr auraient suffi, mais ça crée une dépendance au réseau : sans connexion, le site s'affiche entièrement **sans style**. Le risque est réel dans deux situations qui comptent — travailler dans un train, et faire la démonstration sur le wifi de l'école. Un site nu devant un jury est un incident évitable en une décision. C'est aussi cohérent avec l'exigence de packaging du sujet : les bibliothèques font partie du livrable, elles ne sont pas censées être téléchargées ailleurs à l'exécution. Coût : environ 700 Ko dans le dépôt.

**Le piège des icônes que j'ai failli manquer.** Les icônes Bootstrap ne sont pas des images : c'est une **police de caractères**, où chaque « lettre » est un pictogramme. Le fichier CSS va chercher les polices via un chemin **relatif à lui-même** (`url("fonts/bootstrap-icons.woff2")`). Il faut donc que `bootstrap-icons.css` et son dossier `fonts/` restent côte à côte. Si on déplace le CSS sans les polices, la page s'affiche normalement mais **toutes les icônes deviennent des carrés vides** — un symptôme déroutant, car rien n'indique qu'un fichier manque. J'ai vérifié que la police est bien servie en 200 avec le bon type MIME.

**Les deux thèmes refaits en Bootstrap natif :** barre de navigation verte (`bg-success`) pour le front-office, sombre (`bg-dark`) pour le back-office. Aucune couleur personnalisée — tout vient de Bootstrap, conformément à la consigne. L'intérêt reste le même : voir instantanément dans quel espace on se trouve pendant une démonstration.

**Vérifié après la migration :** les deux thèmes de barre, les 5 cartes grisées et la carte active du tableau de bord, le tableau des commerçants avec son filtre, les 4 langues sur la page de connexion, la 404 personnalisée, les 4 fichiers Bootstrap servis en 200 avec le bon type MIME, et l'API toujours à 66/66.

**État à ce stade :** le socle du front est terminé et habillé. Restent les écrans métier — 2 pages publiques (catalogue des services, candidature bénévole) et 5 modules de back-office (bénévoles, collectes, stocks, tournées, services). Ils suivront tous la même structure que `CommercantsController`, et maintenant aussi les mêmes composants Bootstrap : `card` pour les filtres, `table table-striped` pour les listes, `alert` pour les cas vides.

---

## 2026-08-03 — Maquettes des écrans restants, et choix de la V2.3

**Pourquoi des maquettes.** Il me restait une douzaine d'écrans à écrire (2 pages publiques, 5 modules de back-office). Plutôt que de coder directement les vues PHP et de découvrir la mise en page en cours de route, j'ai fait des **maquettes HTML statiques** : ouvrables d'un double-clic, sans Docker ni PHP, donc consultables hors connexion pendant mes trajets.

Astuce qui s'est révélée utile : les maquettes chargent **le vrai Bootstrap du projet** en chemin relatif (`../front-php/public/assets/`), pas une copie. Ce que je vois dans la maquette est donc exactement ce que donnera la page finale.

**Quatre versions produites**, en itérant sur les retours :

- **V1** : Bootstrap standard, barre horizontale, cartes avec ombres. Correct mais très générique.
- **V2** : style plat, bordures marquées, barre latérale pour le back-office. Nettement mieux, mais un défaut que je n'avais pas vu.
- **V2.2** : correction de ce défaut (voir ci-dessous) + tri, pagination, modales, indicateurs chiffrés.
- **V2.3** : variante retenue.

**Le défaut de la V2, et ce qu'il m'a appris.** Le vert servait **à la fois** d'accent de marque **et** de signal « validé ». Résultat : il ne voulait plus rien dire — un bouton vert pouvait aussi bien être une action neutre qu'un état positif. La correction en V2.2 a été de donner **un sens unique à chaque couleur** : bleu pour la marque et la navigation, vert *uniquement* pour un état positif, orange pour ce qui attend, rouge pour ce qui bloque. C'est mesurable : le vert est passé de 105 à 39 occurrences.

C'est le genre de règle que je peux défendre à l'oral, contrairement à un choix purement esthétique : **une couleur porte une information, elle ne décore pas**.

**Pourquoi j'ai retenu la V2.3.** Elle pousse le raisonnement un cran plus loin, avec deux densités opposées selon l'usage réel de chaque espace :

- le **back-office est dense** (barre latérale sombre, tableaux compacts, onglets) parce que c'est un outil utilisé toute la journée : on veut voir un maximum de lignes sans faire défiler ;
- le **front-office est aéré** (espace, typographie large, filets au lieu de cartes) parce que c'est une vitrine consultée quelques minutes : on veut du confort de lecture.

L'argument : **la densité d'une interface doit suivre le temps qu'on y passe.**

Deuxième bénéfice, que je n'avais pas anticipé : la barre latérale étant sombre, **le bleu ne sert plus qu'aux boutons d'action** — ils ressortent immédiatement, alors qu'en V2.2 ils se noyaient parmi les autres éléments bleus.

Détail technique à retenir : la barre latérale sombre ne coûte **aucune ligne de CSS**. C'est l'attribut `data-bs-theme="dark"` de Bootstrap 5.3, qui bascule toutes les classes à l'intérieur en version sombre. Les quatre versions de maquettes n'ont **aucune feuille de style écrite à la main**, malgré des rendus très différents — ce qui montre que Bootstrap fournit des briques et n'impose pas un look.

**État à ce stade : les maquettes ne sont que des maquettes.** Aucune n'est branchée sur le vrai front. Le portage de la V2.3 vers les vues PHP est la prochaine étape, notée en tête de ma todo. Je n'ai pas eu le temps de le faire aujourd'hui.

**Un point à ne pas oublier au moment du portage** : le tri des colonnes et la pagination présents dans les maquettes ne sont **pas supportés par l'API actuelle**. Il faudra soit ajouter des paramètres `tri` et `page` aux routes de listing, soit retirer ces éléments des vues. C'est la seule chose des maquettes qui ne repose pas sur du code déjà testé.

---

## 2026-08-03 (suite) — Refonte du multilingue : les traductions passent en base

**Le déclencheur.** J'avais un système i18n classique : quatre fichiers PHP (`app/i18n/fr.php`, `en.php`...) contenant des tableaux `clé => texte`. Ça marchait, mais deux choses me gênaient. D'abord, je n'étais pas sûr que « i18n en fichiers » soit accepté. Ensuite et surtout : **le sujet demande un back-office**, et un tableau PHP figé dans le code ne se modifie pas depuis une interface. Corriger une faute de frappe en italien imposait de modifier un fichier, reconstruire l'image Docker et redéployer. Pour un mot.

J'ai donc repris le système que j'avais codé sur **UpcycleConnect**, où les traductions vivent en base et se gèrent depuis le back-office.

**Ce que j'ai trouvé en relisant UpcycleConnect.** Deux tables (`langue` et `traduction`), un CRUD complet côté API Go, un écran `views/back-office/traductions.php`, et — le point que j'avais oublié — **des fichiers JSON dans `app/locales/`** lus par une fonction `__($key)`.

Au début, ce doublon m'a semblé être une erreur : pourquoi stocker les traductions **deux fois** ? En regardant le code de synchronisation, j'ai compris que c'est le point clé de l'architecture, et que les deux ne servent pas au même usage :

- la **base** sert à **éditer** (back-office, plusieurs personnes, données qui survivent aux conteneurs) ;
- les **fichiers JSON** servent à **lire** (une page affiche 30 à 50 libellés ; les chercher un par un en base ferait autant d'allers-retours réseau **pour un seul écran**).

C'est un **cache**, tout simplement : la base fait autorité, le fichier est une copie rapide à lire. Bénéfice secondaire que je n'avais pas anticipé : **si l'API tombe, le site reste lisible**, puisque les libellés viennent du disque.

**Ce que j'ai gardé tel quel** : le principe des deux tables, les deux boutons de synchronisation (base→fichiers et fichiers→base), et surtout **le garde-fou qui refuse l'export si la base est vide**. Celui-là est capital : sans lui, réinitialiser la base puis cliquer sur « Base vers fichiers » écraserait les quatre fichiers par du vide — et les traductions seraient perdues définitivement, puisqu'elles n'existent plus qu'à cet endroit. Cinq lignes qui protègent d'une perte irréversible.

**Ce que j'ai amélioré, et que je dois savoir défendre :**

1. **Une contrainte `UNIQUE (cle, code_langue)`.** UpcycleConnect ne l'avait pas : rien n'empêchait deux lignes `nav.accueil` en français, et l'affichage devenait imprévisible (celle que la base renvoie en premier gagne, sans garantie). C'est aussi elle qui rend le point 3 possible.

2. **La clé étrangère est le code de langue (`'fr'`), pas un identifiant numérique.** UpcycleConnect utilisait `id_langue`. Mais ce code est déjà ce qu'on manipule partout ailleurs : le nom du fichier (`fr.json`), le paramètre `?lang=fr`, l'attribut `<html lang="fr">`. Avec un entier, il faudrait une jointure à chaque fois pour retrouver `fr` à partir de `2`. Un id numérique se justifie quand la valeur peut changer — un code de langue, non.

3. **`ON CONFLICT (cle, code_langue) DO UPDATE`** pour l'import. UpcycleConnect faisait, pour chaque clé : un SELECT pour savoir si elle existe, puis un INSERT ou un UPDATE selon la réponse. Soit deux fois plus de requêtes, et une **condition de course** (entre le SELECT et l'INSERT, un autre import peut avoir créé la ligne). PostgreSQL sait faire les deux en une seule requête atomique. Conséquence utile : **l'import est idempotent** — le relancer donne le même résultat, sans jamais créer de doublon.

**Le détail qui m'a fait perdre du temps.** Pour convertir les fichiers PHP en JSON, j'ai écrit un script qui cherchait `'cle' => 'valeur'`. Résultat : `fr.json` sortait avec **39 clés au lieu de 40**, les trois autres langues en avaient bien 40. La clé manquante était `back.acces_refuse`, dont le texte français est *« Vous n'avez pas les droits... »* — avec une apostrophe. Je l'avais donc écrite entre **guillemets doubles** en PHP, et mon motif ne capturait que les apostrophes simples. Une seule ligne sur 160, invisible à la relecture.

Ce que ça m'apprend : quand un compteur donne un chiffre inattendu, **c'est le chiffre qui a raison**. Si je n'avais pas comparé les quatre fichiers entre eux, le message d'accès refusé se serait affiché en français au milieu du site italien, et je ne l'aurais découvert qu'en démonstration.

**L'écran de gestion.** Le vrai apport n'est pas le CRUD, c'est l'**affichage**. L'API renvoie une liste à plat : une ligne par (clé, langue), soit 252 lignes pour 63 clés en 4 langues — illisible. Je regroupe par clé, ce qui donne un tableau avec **une ligne par clé et une colonne par langue** : 63 lignes, et surtout une traduction manquante **saute aux yeux** (cellule vide bordée d'orange, remplissable directement). C'est ce qui transforme un CRUD basique en outil utilisable.

J'ai aussi mis l'édition **en place** : chaque cellule est son propre petit formulaire, on corrige un libellé sans quitter la page. Pour un travail de traduction où on corrige dix mots d'affilée, c'est ce qui change tout.

**Deux points de sécurité repris du reste du projet** : le français ne peut pas être supprimé (c'est la langue de référence, celle sur laquelle on retombe quand une clé manque ailleurs — la supprimer laisserait des trous partout), et l'API le refuse **en plus** de masquer le bouton dans la vue. Masquer seul ne protégerait rien.

**Testé une fois Docker revenu — et le test a trouvé un vrai bug.**

Le cycle complet fonctionne : import des fichiers vers la base (63 clés × 4 langues), modification d'un libellé dans le back-office, puis le moment intéressant — **la base contient déjà le nouveau texte mais le site affiche encore l'ancien**. C'est exactement le comportement attendu, et ça m'a permis de vérifier de mes yeux que le cache est bien un cache. Clic sur « Base vers fichiers » : *4 fichier(s) régénéré(s) (EN, FR, IT, PT), 254 libellé(s)*, et le site affiche le nouveau texte.

J'ai aussi testé le garde-fou en conditions réelles : j'ai vidé la table à la main puis tenté un export. Refusé, avec le message prévu, et **les quatre fichiers intacts**. J'ai ensuite pu tout restaurer avec le bouton inverse. C'est rassurant de l'avoir vérifié plutôt que de supposer que ça marche.

**Le bug trouvé.** En testant les cas d'erreur, créer une clé déjà existante renvoyait **500** au lieu de **409**. La base faisait bien son travail (la contrainte `UNIQUE` bloquait le doublon), mais `utils/erreurs.go` ne savait traduire que le code PostgreSQL `23503` (clé étrangère → 400). Le code `23505` (unique_violation) tombait dans le cas général « erreur serveur ».

C'est précisément le genre de défaut que la Phase 10 visait : **un 500 signifie « le serveur a un bug »**, alors qu'ici le serveur va très bien — c'est la donnée envoyée qui entre en conflit avec l'existant. La bonne réponse est 409 Conflict.

Corrigé au bon endroit : dans `ErreurServeur`, pas dans le handler des traductions. Comme les 101 appels du projet passent par cette fonction, **tous les modules en bénéficient d'un coup** — y compris ceux où le cas n'avait jamais été rencontré. Vérifié ensuite : doublon → 409, langue inexistante → 400, et la suite complète relancée deux fois de suite donne **75/75**.

Ce que ça confirme : écrire le test **après** avoir codé sert à quelque chose. J'aurais pu livrer ce module en pensant qu'il marchait — il marchait, mais il mentait sur la nature de l'erreur.

---

## 2026-08-03 (suite 2) — Audit des maquettes : 7 domaines sans écran

**Le déclencheur, c'est une question que je me suis posée** avant de commencer le portage : est-ce que mes maquettes couvrent vraiment tout ce qu'il y a à administrer ?

Plutôt que de répondre au feeling, j'ai croisé les **24 tables** de la base et les **71 routes** de l'API avec les 8 écrans de back-office de la V2.3. Résultat : **7 domaines n'avaient aucune interface**. Ils existaient dans l'API, testés et fonctionnels — mais invisibles.

**Le plus embêtant, et de loin : les adhésions.** Le sujet insiste sur *« prévoir un système de rappel automatique de renouvellement »*. C'est codé, testé, avec le job en goroutine, les relances J-30 / J-7 / ex-adhérents, et l'historique anti-doublon. Mais sans écran, **je n'aurais rien pu en montrer le jour de la démonstration**, alors que c'est précisément ce que le jury va chercher. J'ai fait de cet écran le plus complet : le job est affiché avec son état, un bouton pour le déclencher à la main, la liste des échéances, et l'historique des rappels envoyés.

Les autres manques : **bénéficiaires** (cités dans le sujet, créables uniquement par API), **utilisateurs** (le dernier trou connu — créer un compte staff passe encore par du SQL), **emplacements** (le bouton existait et ne menait nulle part), **liste des tournées** (j'avais le détail d'une tournée mais aucun chemin pour y accéder), **catalogue services/compétences** (on pouvait affecter une compétence, pas en créer), et **campagnes** (4 routes, mon plus gros ajout, invisible).

**Ce que l'audit m'a appris au-delà de la liste.** Compléter les domaines manquants ne suffisait pas : il manquait aussi des écrans pour que la navigation *tienne debout*. Une **liste sans fiche est une impasse** — on voit des lignes sans pouvoir rien ouvrir. J'ai donc ajouté la fiche commerçant (qui regroupe infos, historique des adhésions, collectes et rappels au même endroit), le détail d'une collecte, et un vrai formulaire de création, puisque les boutons « Ajouter » de la V2.3 ne menaient nulle part.

**Un détail de navigation que je n'avais pas anticipé.** Les écrans de détail n'ont pas d'entrée de menu — ils s'ouvrent depuis une liste. Sans précaution, ouvrir une fiche fait donc *perdre toute position dans le menu* : plus rien n'est surligné, on ne sait plus où on est. J'ai ajouté une table de correspondance qui dit quelle entrée garder active pour chaque écran de détail. C'est le genre de détail invisible quand il est bien fait, et déroutant quand il manque.

**Le menu passe de 4 à 5 sections**, organisées selon le parcours métier plutôt que l'ordre des tables : on entre dans le réseau (qui donne), on collecte, on stocke, on distribue ; puis les activités, et enfin l'administration.

**Résultat : 26 écrans au lieu de 14**, et une vérification automatique confirme **19 domaines d'API, 0 sans écran**.

**Trois choses des maquettes ne sont pas supportées par l'API** — autant les connaître avant qu'on me les fasse remarquer : le tri et la pagination des listes, la création d'un compte avec choix du rôle, et la modification de son propre profil. Le reste consomme des routes existantes et testées.

Ce que je retiens : **une API complète ne fait pas une application complète**. J'avais 71 routes qui marchaient et je pensais le back-end fini — il l'était, mais un tiers restait inaccessible à un utilisateur.

---

## 2026-08-03 (suite 3) — Audit du sujet, terminologie, et temps de lecture

**Trois choses faites après avoir relu le sujet ligne par ligne.**

**1. Deux exigences n'avaient aucun écran.** J'ai croisé les 19 citations du sujet avec les 28 maquettes. Le manque le plus sérieux : *« front office (utilisé par **les clients** de NO MORE WASTE) »*. Aujourd'hui un commerçant ou un bénévole qui se connecte ne voit **rien de plus qu'un visiteur anonyme** — le front-office est une vitrine, pas un espace client. J'ai donc ajouté un tableau de bord par rôle.

L'espace commerçant montre l'adhésion en premier, parce que c'est elle qui conditionne tout le reste : un adhérent expiré ne peut plus être collecté. Et surtout un bouton **« demander une collecte »**, qui est l'action principale d'un commerçant et n'existait nulle part.

L'espace bénévole rend visible **le blocage de validation**, document par document. C'est la même règle que l'écran back-office, vue de l'autre côté — sans ça, un bénévole ne comprend pas pourquoi il n'est affecté à aucune mission.

Deuxième manque : les **pages d'erreur** gardaient l'ancien habillage vert alors que le sujet demande *« prévoir réécriture d'URL, codes d'erreurs etc. »*. Refaites au design V2.4. Sur la 500, je n'affiche **aucun détail technique** : un message PostgreSQL ou une trace Go renseignerait un attaquant sur la structure interne. La cause réelle part dans les journaux, via `utils/erreurs.go`.

**2. Une correction de vocabulaire qui compte.** J'appelais « hors sujet » les fonctionnalités absentes du cahier des charges (campagnes, configuration des rappels, profils). C'est faux, et surtout c'est dévalorisant : ce sont des **bonus**. Le tag `[AJOUT PERSO]` est devenu `[BONUS]` dans toute la documentation, et sa définition ne dit plus « ce qu'il faut sacrifier en premier » mais « ce qui montre qu'on a réfléchi au-delà de la commande, et qui se valorise à l'oral ». En cas de manque de temps c'est bien ce qu'on repousse — mais ce n'est pas la même chose que du hors-sujet.

**3. Le temps de lecture affiché partout.** Je lis la documentation hors connexion, en voyage. Savoir si un fichier prend 5 ou 25 minutes change la façon d'organiser une session — et évite de commencer un gros morceau avant un trajet court.

Chaque `.md` porte maintenant un en-tête du type :

Le calcul : `mots / 130 + lignes_de_code / 8`. Deux vitesses différentes parce qu'on ne lit pas du code comme de la prose — on s'arrête dessus, on relit, on compare au fichier source. Arrondi aux 5 minutes.

**Les chiffres m'ont surpris** : **86 fichiers, environ 13 h 50 de lecture attentive au total**, dont 8 h 05 pour le seul parcours conseillé du guide. Le journal de bord est à lui seul le plus long fichier du projet (1 h 30). C'est une information que je n'avais nulle part et qui change ma façon de planifier : je ne rattraperai pas 14 h de lecture la veille de la soutenance.

J'ai aussi ajouté, pour chaque phase, le total *avec* les fichiers détaillés qu'elle référence — la vraie réponse à « si je veux comprendre cette phase à fond, j'en ai pour combien ? ». La Phase 2 (commerçants, adhésions, rappel automatique, campagnes) est la plus lourde : **1 h 50**.

Deux précisions écrites dans la doc pour ne pas se mentir : ces durées valent pour une **première lecture attentive**, et relire pour *maîtriser* — l'objectif des sessions de live coding — prend en général le double.

**État : 32 maquettes, documentation complète et chiffrée.** La suite est le portage PHP, qui reste le gros morceau : 6 vues codées sur 32 écrans dessinés.

---

## 2026-08-03 (suite 4) — L'espace client, et un défaut de modélisation trouvé par un test rejoué

**Le manque que l'audit avait révélé.** Le sujet parle d'un *« front office utilisé par les **clients** »*. Or toutes mes routes métier étaient réservées au personnel : un commerçant qui se connectait ne voyait rien de plus qu'un visiteur anonyme. J'ai ajouté cinq routes `/mon-espace/`.

**La règle que je me suis fixée avant d'écrire une ligne**, parce que c'est là que se joue la sécurité : **aucune de ces routes n'accepte d'identifiant venant du client**. On part toujours du jeton — `jeton → email → compte → sa fiche → ses données`.

J'ai failli écrire `GET /mon-espace/collectes?commercant_id=7`, ce qui paraît naturel. Mais n'importe quel adhérent connecté aurait alors pu essayer `8`, puis `9`, `10`… et lire les collectes de tous les autres. Aucune erreur, aucune trace suspecte : la requête est valide du point de vue du serveur. C'est une faille classique, la **référence directe non sécurisée**. La protection retenue est structurelle : le client n'a aucun moyen de désigner une autre fiche, puisqu'il ne désigne rien du tout.

Même logique sur `POST /mon-espace/collectes` : je ne lis **que** la date du corps de la requête. Le statut est forcé à `demandee`, le bénévole reste vide, et le commerçant vient de sa fiche. Décoder tout l'objet `Collecte` d'un coup aurait laissé un commerçant déclarer sa propre collecte « réalisée », ou en demander une au nom d'un autre. Le principe à retenir : **ne jamais lire du corps de la requête ce que le client n'a pas à décider**.

**Le défaut de modélisation, trouvé en rejouant le test.** Mon script de test passait 17/17 au premier lancement, puis 16/17 au second. La vérification qui échouait : « la fiche retournée est bien la sienne ».

En regardant la base, deux commerçants portaient le **même** `utilisateur_id`. La colonne existait depuis le début du projet, mais **sans contrainte d'unicité** — rien n'empêchait donc deux fiches de pointer vers le même compte. Et `GetCommercantByUtilisateurId` fait un `QueryRow` : il renvoyait l'une ou l'autre, sans garantie. Un comportement non déterministe, du genre qui se manifeste une fois sur deux et qu'on met des heures à comprendre.

Corrigé par un `UNIQUE` dans le schéma. Détail que j'ai vérifié : PostgreSQL ne considère pas deux `NULL` comme égaux, donc plusieurs fiches peuvent rester sans compte associé — ce qui est le cas normal d'un commerçant enregistré par le personnel avant que le gérant crée son compte.

**Ce que ça m'apprend** : ce bug n'était visible qu'en **rejouant** le test. Un test qu'on ne lance qu'une fois ne teste que le cas où la base est vide. C'est la deuxième fois que la rejouabilité me trouve un vrai problème — la première étant les 409 qui m'avaient poussé à rendre le script de test réinitialisant.

**Le dernier trou de l'API est comblé.** `POST /utilisateurs/` crée enfin un compte **avec choix du rôle**. Jusqu'ici, fabriquer un compte staff imposait un `UPDATE utilisateurs SET role=...` à la main — donc ouvrir un client PostgreSQL pour installer l'application, ce qui contredit le *« packagé pour être aisément déployé »* du sujet.

Deux décisions à savoir justifier là-dessus. D'abord, la route est réservée à `admin_back` **et pas à `staff_back`** : pouvoir créer des comptes, c'est pouvoir se fabriquer un accès — un membre du personnel pourrait se créer un second compte administrateur et contourner les limites de son propre rôle. Les permissions ne se répartissent pas par confiance envers les personnes, mais par conséquence de ce que l'action permet. Ensuite, une **liste blanche des quatre rôles** : sans elle, créer un compte `super_admin` réussirait, et ce compte serait refusé par *toutes* les gardes sans que personne comprenne pourquoi.

Il reste le **problème du premier compte** : créer un admin exige d'être admin. Ça n'a pas de solution purement applicative — c'est le rôle du script d'installation (item 12.1). Mais le trou est réduit à *un seul* compte au lieu d'un par membre du personnel.

**Un détail de conception dont je suis content.** Le planning quotidien envoyé par email et le planning personnel du bénévole affichent les mêmes informations : une requête à trois tables jointes. Plutôt que d'en écrire une seconde, j'ai généralisé `ListPlanningDuJour(date)` en `ListPlanning(date, benevoleId)` avec deux filtres facultatifs, et l'ancienne fonction appelle la nouvelle. Aucun appelant existant modifié, et une seule requête à maintenir le jour où le modèle change.

**Vérifications** : `tester-tous-les-endpoints.py` → **77/77**, `tester-espace-client.py` → **17/17**, les deux rejouables trois fois de suite. Le front répond toujours dans les 4 langues — alors que la base venait d'être réinitialisée, ce qui confirme au passage que le cache JSON des traductions fait bien son travail.

---

## 2026-08-03 (suite 5) — Le socle du portage : deux gabarits, et une faille trouvée en chemin

**Le point de départ.** 31 écrans dessinés, 5 vues codées. Et un problème structurel que je n'avais pas mesuré : les maquettes du back-office ont une **barre latérale**, mon layout avait une **barre horizontale**. Ce n'était pas un habillage à changer, c'était la structure de la page.

**La décision qui commandait tout : un ou deux gabarits ?** J'ai écarté le fichier unique avec un `if/else` — le `if` aurait englobé deux structures HTML entières, soit 350 lignes dont on ne lit jamais la moitié, avec des `endif` à 200 lignes de leur `if`. Précisément le fichier qu'on n'arrive plus à expliquer.

Deux gabarits, donc. Restait à choisir lequel utiliser. J'ai hésité entre un paramètre explicite (`Vue::afficher(..., 'back')`) et une détection automatique par le dossier de la vue. **J'ai retenu la détection**, pour une raison défendable : la convention `back/` contre `front/` existe déjà trois fois dans le projet (dossiers de vues, fichiers de routes, préfixe d'URL). La réutiliser n'ajoute aucune règle à retenir.

Mais l'argument décisif est ailleurs. Avec un paramètre explicite dont la valeur par défaut serait `front`, mes contrôleurs de back-office continueraient de fonctionner **en rendant leurs vues dans le mauvais habillage**. Pas d'erreur PHP, pas de 500, juste un écran faux — exactement le genre de bug qu'on ne découvre qu'en démonstration. Avec la détection par dossier, on ne *peut pas* oublier : le chemin est obligatoire.

**Comment j'ai organisé le travail pour ne rien casser.** J'ai construit les étapes pour qu'elles soient **purement additives** : créer le menu, les blocs, les deux gabarits — pendant tout ce temps le site continuait de tourner sur l'ancien layout, puisque personne n'appelait les nouveaux fichiers. **Une seule étape bascule tout**, et elle ne touche qu'un fichier (`Vue.php`). Si quelque chose avait cassé, je savais exactement où regarder.

**La faille trouvée en chemin.** En relisant `Vue::rendre()` avant de le modifier :

```php
$fichier = __DIR__ . '/views/' . $chemin . '.php';
extract($donnees);     // <- ecrase les variables existantes
require $fichier;      // <- donc potentiellement n'importe quel fichier
```

`extract` écrase les variables déjà présentes. Une vue à qui l'on passerait `['fichier' => ...]` — ce qui n'a rien d'absurde pour un écran de documents de bénévoles — ferait charger un fichier arbitraire du serveur. Corrigé d'un mot : `extract($donnees, EXTR_SKIP)`, qui interdit de remplacer une variable existante. Ce n'était pas exploitable aujourd'hui (aucune vue ne reçoit cette clé), mais ça l'aurait été au premier écran de documents.

**Le double titre, anticipé.** Mes trois vues de back-office dessinaient leur propre titre. Après la bascule, elles en affichaient deux : celui du nouvel en-tête et le leur. Je l'avais prévu, mais le nettoyage a servi à autre chose : c'était la **preuve exécutable** que mon contrat `$options` tenait. Si un des écrans existants n'y était pas rentré, mieux valait le savoir avant de porter les 26 suivants. Les deux sous-titres sont passés sans difficulté.

**Un choix à savoir justifier : les compteurs vides.** Les maquettes montrent des pastilles sur la barre latérale (2 adhésions à renouveler, 3 candidatures…). Les alimenter réellement demanderait quatre appels API supplémentaires sur *chaque* page du back-office — et une panne de l'API casserait alors les 22 écrans au lieu d'un seul. J'ai posé la forme (le code lit les compteurs et n'affiche la pastille que si elle est positive) et je remplirai écran par écran. Afficher « 0 candidature à valider » n'aurait de toute façon aucun intérêt.

**Le test qui m'a le plus appris.** Vérifier que la barre latérale se traduit. Un seul libellé écrit en clair dans `menu_back.php` aurait cassé le multilingue sur les 22 écrans d'un coup — et je ne l'aurais vu qu'en changeant de langue, c'est-à-dire peut-être jamais avant la soutenance. Résultat : *Panoramica, Rete, Logistica, Attività, Amministrazione*. C'est maintenant le premier test à refaire après toute modification du menu.

**Piège de traduction confirmé.** Les fichiers `app/locales/*.json` sont un cache régénéré depuis la base. Ajouter mes 23 nouvelles clés dans les JSON seuls les aurait fait disparaître au premier « Base vers fichiers ». Séquence correcte, que j'ai suivie : écrire dans les 4 JSON, puis `/back/traductions`, puis **« Fichiers vers base »**. J'en ai profité pour supprimer les 2 clés `demo.*` que les scripts de test avaient laissées derrière eux.

**Vérifications** : les 9 routes, les gardes (un adhérent sur `/back` renvoie 403 avec message), le filtre par ville toujours fonctionnel, un seul titre par écran, la 404 personnalisée en Bootstrap. Les deux suites API restent au vert (**77/77**, **17/17**) — le front n'y touche pas, mais autant le confirmer.

**Reste à faire** : les 26 écrans métier, par vagues — les modules cités par le sujet d'abord, puis l'espace client, puis le reste.

## 2026-08-07 — Vague 2 : les six modules que le sujet cite, et quatre bugs que seul l'écran révélait

**Ce qui est fait.** Bénévoles, collectes, stocks, emplacements, tournées, services et créneaux — les modules que le sujet nomme explicitement. Neuf écrans, tous testés dans les quatre langues, plus deux fichiers téléchargeables (le récapitulatif PDF d'une livraison, le planning CSV du jour).

**Le fil conducteur de cette vague : porter un écran, c'est tester l'API pour de vrai.** Mes deux suites automatiques passaient à 77/77 et 17/17 avant de commencer, et elles passaient toujours après. Pourtant j'ai trouvé quatre défauts réels. Un test vérifie ce qu'on a pensé à vérifier ; un écran, lui, demande à l'API *tout ce dont il a besoin pour être utilisable*. C'est la leçon que je retiens de cette étape.

### Les quatre défauts, dans l'ordre où je les ai trouvés

**1. Le PDF exigé par le sujet était inatteignable.** `GET /tournees/{id}/etapes` disait qu'un arrêt était livré, mais ne donnait aucun moyen de retrouver *sa* livraison — donc aucun moyen de construire le lien vers le récapitulatif. Une exigence 🟥 du sujet, invisible dans les tests parce qu'aucun test ne demandait « et maintenant, montre-moi le PDF de cet arrêt ».

Correction : un champ `livraison_id`, alimenté par un `LEFT JOIN`. **`LEFT` et non `JOIN` simple** — avec un JOIN ordinaire, les arrêts pas encore clôturés disparaîtraient de la liste, et l'écran ne montrerait plus que le travail déjà fait. Exactement l'inverse de ce qu'on attend d'un écran de tournée.

**2. Les heures s'affichaient « 0000- ».** `heure_prevue` est une colonne `TIME`. Lue dans une chaîne côté Go, `database/sql` la reçoit comme une date complète et la formate en `"0000-01-01T10:30:00Z"` : une heure de passage affublée d'une année zéro.

J'aurais pu découper la chaîne côté PHP. J'ai préféré corriger à la source, avec `to_char(heure_prevue, 'HH24:MI')` dans la requête. La raison : avec la rustine côté client, *chaque* consommateur de l'API devrait savoir qu'il faut ignorer onze caractères. **Une API qui renvoie une heure doit renvoyer une heure.** Le même défaut existait sur les créneaux de service — corrigé pareil.

Détail que je note pour ne pas me faire piéger : le tri SQL porte toujours sur la colonne `TIME`, pas sur le texte produit. Trier des heures comme du texte marche par chance en 24 h, mais pas avec un format sur 12 h.

**3. Le type de service était un champ libre.** La colonne `type` a une contrainte `CHECK` : sept valeurs, pas une de plus. Mon formulaire proposait un champ texte. Taper « cuisine » au lieu de « cours_cuisine » produisait une **erreur 500** pour ce qui est, du point de vue de l'utilisateur, une faute de frappe.

Corrigé en menu déroulant — plus une revalidation côté serveur, parce que le menu ne protège que ceux qui l'utilisent.

**4. Conséquence du précédent, et vrai défaut de l'API :** une violation de contrainte `CHECK` répondait 500. Or c'est une faute du **client**, pas du serveur. Le code PostgreSQL `23514` a donc rejoint `23503` et `23505` dans `utils.ErreurServeur`. L'API répond maintenant 400, avec un message qui dit quoi corriger.

C'est la troisième fois que ce fichier central me sert. Le motif se répète : je découvre un code d'erreur PostgreSQL en testant un écran, je l'ajoute à un seul endroit, et les 78 routes en bénéficient.

### Une décision que je dois savoir défendre : le PDF et le CSV passent par le front

Le lien évident serait `<a href="/api/livraisons/1/pdf">`. Il ne marche pas — et j'ai vérifié plutôt que supposé : **401 Jeton invalide**.

La raison est simple une fois qu'on la voit : le jeton JWT vit dans la **session PHP**. Le navigateur qui suit ce lien n'envoie aucune preuve d'identité à l'API. Le front sert donc de relais : il demande le fichier avec le jeton de la session et le renvoie au navigateur. Bénéfice secondaire, la garde de rôle s'applique aussi au téléchargement — un récapitulatif de livraison n'est pas un document public.

Deux différences entre les deux fichiers : le PDF s'ouvre `inline` (on le relit avant de l'imprimer pour signature), le CSV en `attachment` (il n'a rien à montrer dans un navigateur, on veut l'ouvrir dans Excel).

### Ce que je ne duplique pas

L'affectation d'un bénévole à un créneau exige deux conditions : être validé, et posséder la compétence requise. Le front ne réimplémente **aucune** des deux. Il ne charge que les bénévoles validés — donc les autres ne sont pas proposés — et laisse l'API refuser sur la compétence, en affichant « requiert : cuisinier » pour que le refus soit compréhensible.

Vérifier la compétence côté front demanderait un appel par bénévole, et surtout **maintiendrait la même règle à deux endroits**. Le jour où elle change côté API, le front appliquerait l'ancienne. Même raisonnement que pour le bouton désactivé des bénévoles : on rend l'erreur improbable, on ne prétend pas l'empêcher.

### Un compromis assumé, et sa limite

L'API n'expose pas de route « tous les créneaux » : elle les donne service par service. Le contrôleur boucle donc sur les services, puis appelle une fois de plus par créneau pour compter les inscrits.

C'est acceptable à cette échelle — une association a une poignée de services. Mais je sais où est la limite, et quelle serait la bonne réponse si la liste grandissait : une route `GET /creneaux/` côté API, pas plus d'appels côté front. C'est le contraire du choix fait pour les collectes, où une seule requête suffit à construire un index ; là-bas la route existait, ici non.

### Un message que j'ai corrigé pour ne pas mentir

Le bouton « Envoyer maintenant » déclenche l'envoi des plannings. L'API lance l'opération et répond aussitôt : elle **n'attend pas** le résultat SMTP. Mon message disait « Plannings envoyés ». C'est faux — et particulièrement faux aujourd'hui, puisque les clés Brevo ne sont pas encore renseignées : les journaux montrent bien `535 Authentication failed`.

Il dit maintenant : « Envoi des plannings lancé. Le détail de chaque envoi est dans les journaux du serveur. » Un message d'interface doit décrire ce qui s'est réellement passé, pas ce qu'on espère.

### Une panne qui a servi de démonstration

En plein test, tous les écrans se sont mis à afficher « Erreur de récupération des bénévoles ». Le conteneur PostgreSQL s'était arrêté. Trente secondes pour le diagnostiquer, parce que les journaux disaient exactement ceci :

```
[ERREUR 500] GET /benevoles/ -> Erreur de recuperation des benevoles
  | cause : ListBenevoles : dial tcp: lookup postgres: no such host
```

Sans le travail fait en phase 10 sur les erreurs silencieuses, j'aurais eu « Erreur de récupération » et rien d'autre — et j'aurais cherché le bug dans mon code du jour. Je note quand même une amélioration possible : une base injoignable devrait répondre 503 plutôt que 500, comme le fait déjà `ErreurBaseIndisponible` pour la route de santé.

### La documentation

J'en avais accumulé du retard. J'ai soldé la dette entièrement : **chaque fichier `.php` du front a désormais son `.md`**, y compris les blocs de gabarit et les vues de la vague 1 que j'avais laissés de côté. 130 liens entre documents vérifiés, aucun mort.

Un piège de traduction confirmé au passage : l'import « Fichiers vers base » **ajoute et met à jour, mais ne supprime pas**. En renommant `menu.creneaux` en `menu.services`, l'ancienne clé est restée en base — et le prochain export l'aurait réintroduite dans les fichiers. Il a fallu la supprimer explicitement. À retenir pour tout renommage de clé.

**Vérifications** : les deux suites API au vert (**77/77**, **17/17**) après modification de trois fichiers de l'API. Les 9 écrans du back-office chargés dans les 4 langues, soit 36 pages, sans une seule clé de traduction non résolue. Les gardes testées déconnecté sur les 5 routes sensibles, téléchargements compris. Le cycle complet d'une livraison rejoué de bout en bout : clôture, produits passés à « distribué », PDF disponible, doublon refusé en 409.

**Reste à faire** : la vague 3 (espace commerçant, espace bénévole, services publics, candidature), puis la vague 4 (adhésions, bénéficiaires, campagnes, utilisateurs, profils de rappel). Et les deux exigences 🟥 encore ouvertes : le déploiement sur serveur réel avec HTTPS (11.2) et le script d'installation (12.1) — ce dernier réglant au passage le problème du tout premier compte administrateur.

## 2026-08-07 (suite) — Vague 3 : l'espace client, et une faille d'autorisation trouvée avant d'écrire une ligne de front

**Ce qui est fait.** Espace commerçant, espace bénévole, catalogue public des services, détail d'un service avec inscription, candidature bénévole et sa page de remerciement. Sept écrans, tous testés dans les quatre langues.

Le sujet demande « à la fois un back-office (utilisé par NO MORE WASTE) et un front office (utilisé par les clients) ». Jusqu'ici le second n'existait que de nom : un adhérent connecté ne voyait rien de plus qu'un visiteur de passage.

### La faille : agir au nom d'un autre

Avant de coder l'écran d'inscription à un créneau, j'ai relu la route de l'API. Elle demandait un `commercant_id` **dans le corps de la requête**. Or c'est exactement ce que les routes `/mon-espace` avaient été conçues pour éviter.

Je l'ai testé plutôt que supposé. Deux comptes adhérents, deux boutiques. Le premier envoie l'identifiant de la boutique du second :

```
POST /creneaux/1/inscriptions  {"commercant_id": 4}
-> 201 Created
```

**La boutique d'un tiers venait d'être inscrite à sa place.** Les deux suites de tests étaient au vert.

La correction distingue deux appelants. Le personnel inscrit autrui — c'est son travail, quelqu'un appelle au téléphone. Un adhérent ne peut inscrire que lui-même : ses identifiants sont **écrasés** par ceux déduits de son jeton, quoi qu'il envoie. Le statut aussi est imposé : on ne s'inscrit pas directement « présent ».

J'ai ajouté six vérifications à `tester-espace-client.py`, sous un titre qui dit la règle : *« agir en son nom propre, et pas au nom d'un autre »*. Une correction de sécurité sans test peut régresser en silence.

### Deux trous qui rendaient l'espace client inutilisable

**Le rattachement d'une boutique à un compte n'existait pas.** `CreateCommercant` n'écrivait pas `utilisateur_id` : une boutique créée par l'API n'était reliée à personne, et son propriétaire ne pouvait pas ouvrir son espace. La seule façon de faire la liaison était une requête SQL à la main — c'est d'ailleurs ce que faisaient mes scripts de test, ce qui masquait le problème.

Même trou côté bénévoles. Mais **attention, la règle n'y est pas la même** : `POST /commercants/` est réservé au personnel, donc lire l'identifiant envoyé y est sûr. `POST /benevoles/candidature/` est **publique** — y accepter un `utilisateur_id` du client aurait permis à n'importe qui d'accrocher une fiche au compte d'autrui. J'ai donc déduit le compte du jeton quand il y en a un, et laissé la fiche anonyme sinon. C'est la même règle que pour l'inscription, appliquée à un cas différent.

Ces deux corrections illustrent quelque chose que je veux savoir réexpliquer : **la question n'est pas « d'où vient la donnée », mais « qui a le droit de la choisir »**.

### Un lien mort dans le menu depuis la vague 1

`Auth::urlEspace()` renvoyait `/mon-espace` pour un adhérent. Cette adresse n'a jamais existé — le lien « Mon espace » de l'en-tête répondait **404**.

Il avait été écrit en vague 1, avant que l'écran existe, et rien ne l'avait signalé : personne ne s'était connecté en adhérent depuis. C'est le genre de bug qu'on ne découvre qu'en se mettant réellement dans la peau de l'utilisateur. Je vérifie maintenant les trois rôles à chaque fois : adhérent, bénévole, personnel.

### Le piège du tableau vide en PHP

L'inscription à un créneau n'a rien à envoyer : tout vient du jeton. J'ai donc posté un corps vide. Refusé — « JSON invalide ».

La cause : en PHP, un tableau vide est à la fois une liste et un dictionnaire, et `json_encode([])` produit `"[]"`, pas `"{}"`. L'API attend toujours un **objet** ; elle recevait une valeur du mauvais type.

Corrigé une fois pour toutes dans `ApiClient`, avec un cast vers `stdClass` quand le tableau est vide. Un tableau non vide n'a jamais eu ce problème : dès qu'il a des clés, `json_encode` produit un objet. J'ai relancé toutes les actions POST du back-office pour vérifier que ce changement central ne cassait rien.

### Des choix d'affichage que je dois savoir défendre

**Trois chiffres justes plutôt que quatre dont un inventé.** La maquette de l'espace commerçant montrait « 312 articles donnés ». L'obtenir demanderait un appel par collecte, et l'espace client n'a pas accès à la route des produits — elle est réservée au personnel. J'ai retiré le chiffre. Un compteur approximatif sur un écran client se remarque tout de suite, et discrédite les trois autres.

Le nom du bénévole affecté est absent pour la même raison. C'est d'ailleurs sain : un commerçant n'a pas besoin de savoir qui passera avant que la personne arrive.

**Le seuil des 30 jours n'est pas décoratif.** L'écran passe à l'orange quand il reste 30 jours d'adhésion, parce que c'est exactement le moment où l'association envoie son premier rappel par email. Un autre seuil ferait dire deux choses différentes au site et au mail, et l'adhérent ne saurait pas lequel croire.

**Un 404 qui n'est pas une erreur.** Quand un compte adhérent n'a aucune boutique rattachée, l'API répond 404 — et c'est le bon code, ce n'est pas le compte qui est introuvable mais la fiche. Laisser passer ce 404 afficherait « page introuvable » : faux, et inquiétant. J'ai fait un écran dédié qui explique ce qui manque. Même raisonnement que le code-barre inconnu de l'écran des stocks.

**Le bouton d'inscription a quatre états.** Complet, adhérent, visiteur anonyme, et — celui qu'on oublie — connecté mais pas adhérent. Dire « Se connecter » à un bénévole déjà connecté l'enverrait tourner en rond.

### Ce que l'écran ne fait pas, et pourquoi

Un bénévole ne peut pas déposer un justificatif depuis son espace. La route existe mais elle est réservée au personnel et attend un chemin de fichier, pas un envoi. Un vrai téléversement demanderait de gérer le stockage, les types autorisés, la taille, l'accès aux fichiers déposés : un chantier à part, hors du périmètre du sujet.

L'écran **montre** l'état du dossier et dit à qui s'adresser. C'est honnête, et c'est déjà ce qui manquait au candidat pour comprendre pourquoi il reste bloqué.

**Vérifications** : `77/77` et `23/23` (la suite espace client est passée de 17 à 23 vérifications, et reste rejouable). Les 9 écrans du back-office rechargés après modification d'`ApiClient` et d'`Auth`, deux fichiers utilisés partout. Les 7 nouveaux écrans dans les 4 langues, sans une seule clé non résolue. L'isolation testée dans les six sens : bénévole vers espace commerçant, adhérent vers espace bénévole, adhérent vers back-office, et les trois mêmes en anonyme. 352 clés de traduction par langue, base et fichiers alignés. Chaque fichier `.php` du front a son `.md` : 349 liens vérifiés, aucun mort.

**Reste à faire** : la vague 4 (adhésions, bénéficiaires, fiche commerçant, campagnes, utilisateurs, profils de rappel). Et les deux exigences 🟥 encore ouvertes : le déploiement sur serveur réel avec HTTPS (11.2) et le script d'installation (12.1).

## 2026-08-08 — Vague 4 (les 22 écrans du back-office sont posés), puis une relecture complète de toute la documentation

**Ce qui est fait.** Cinq derniers modules du back-office : adhésions (avec le rappel automatique enfin visible), bénéficiaires, fiche commerçant complète, utilisateurs et rôles, campagnes d'emailing. **22 écrans au total**, tous testés dans les quatre langues. Puis, sur demande explicite, une passe de fond sur l'ensemble des `.md` du projet — pas seulement ceux touchés cette semaine.

### L'écran le plus important du projet : les adhésions

Le rappel automatique de renouvellement — le point le plus cité du sujet — tournait depuis des semaines (une goroutine quotidienne, J-30/J-7/180 jours) sans qu'aucun écran ne le montre. Impossible de le démontrer autrement qu'en lisant les journaux du serveur.

Il manquait une route entière : `GET /adhesions/` n'existait pas. Seule `/adhesions/a-renouveler/` existait, et elle ne montre que ce qui tombe **exactement** à J-30 ou J-7 — impossible de répondre à « combien d'adhésions sont actives ? » ou « lesquelles ont expiré ? ». Ajoutée, avec un filtre par statut protégé par une liste blanche.

**Un 500 devenu 502.** La relance manuelle d'une adhésion répondait « Erreur d'envoi de l'email » en 500 — comme si mon serveur avait un bug, alors que c'est Brevo qui refuse faute de clés SMTP dans le `.env`. Ajouté `utils.ErreurEmail`, sur le modèle du 503 déjà en place pour la base injoignable : 502 Bad Gateway, avec un message qui dit explicitement quoi vérifier. C'est la troisième fonction de ce genre dans `utils/erreurs.go`, qui continue de prouver l'intérêt d'avoir centralisé les erreurs en phase 10.

### Le trou qui bloquait la fiche commerçant

`PUT /commercants/{id}` n'existait pas. Conséquence concrète : une boutique enregistrée sans compte de connexion restait orpheline **pour toujours** — impossible de la rattacher après coup à son propriétaire, qui se serait connecté pour lire « aucune boutique rattachée » sans le moindre recours.

Ajoutée avec une vraie mise à jour partielle : décodage dans une structure de pointeurs, où un champ absent du JSON ne touche à rien, et où seul un champ explicitement vide l'efface. Sans cette distinction, le formulaire de rattachement de compte — qui n'envoie que `utilisateur_id` — aurait effacé silencieusement le SIRET et l'adresse à chaque enregistrement. C'est le piège classique des routes PUT, et il ne se voit qu'après coup, quand une donnée a disparu sans qu'on sache quand.

La liste des commerçants, qui était une impasse depuis la vague 1 (aucun nom cliquable), mène enfin à une fiche.

### Un piège de traduction retrouvé une seconde fois, et cette fois documenté à la source

En unifiant les clés de type de bénéficiaire (`type_association` → `type_association_caritative`, pour qu'une seule convention serve à la fois l'écran des bénéficiaires et celui des tournées), l'ancienne clé est restée en base après le renommage — exactement le même piège que `menu.creneaux` en vague 2. L'import « Fichiers vers base » **ajoute et met à jour, mais ne supprime jamais**.

Cette fois, plutôt que de le noter seulement dans un journal, j'ai écrit la documentation qui manquait sur `app/traductions.go` — un fichier de 326 lignes et 9 routes qui n'avait **jamais eu de `.md`**, alors que c'est lui qui porte ce piège (`EnregistrerTraduction` fait un `ON CONFLICT DO UPDATE`, jamais de suppression). Le risque se voit maintenant à la lecture, pas seulement à l'expérience.

### L'écran des utilisateurs, et une distinction à savoir tenir

`admin_back` peut créer des comptes, `staff_back` non — alors que les deux entrent dans le back-office. Créer un compte, c'est pouvoir se fabriquer un accès ; cette capacité ne se délègue pas. `Auth::exigerStaff()` laisse passer les deux rôles, il a donc fallu une seconde vérification, au rôle exact, dans le contrôleur.

Trouvé en testant : un `staff_back` voyait quand même l'entrée « Utilisateurs » dans son menu, cliquait, et rebondissait sur le tableau de bord. Un lien qui rebondit donne l'impression d'un site cassé. Ajouté une clé `role` sur les entrées du menu, qui les masque selon le compte connecté — en insistant dans le commentaire que ce n'est que du confort, la vraie protection restant dans le contrôleur.

### Les campagnes : un envoi qui ne s'annule pas

Le seul écran du projet où l'action principale est irréversible. La protection tient en trois choix : créer une campagne n'envoie rien (deux routes distinctes, `creer` et `declencher`) ; le bouton d'envoi est tout en bas de la fiche, après la liste complète et nominative des destinataires ; une case à cocher que seul le formulaire produit, revérifiée côté serveur — un POST forgé sans elle ne déclenche rien.

Le chiffre affiché après l'envoi est celui des emails **réellement partis** (`nombre_envoyes`), pas celui des destinataires visés. Aujourd'hui les deux valent 0 sur N, tant que Brevo n'est pas configuré — et l'écran le dit tel quel plutôt que d'afficher un vague « c'est fait ».

### La relecture complète, demandée explicitement

Au-delà des cinq nouveaux modules, j'ai repris tous les `.md` existants pour vérifier qu'ils décrivaient encore le code réel. Plusieurs ne l'étaient plus :

- `utils/erreurs.go.md` ne connaissait que le premier code PostgreSQL (`23503`) alors que le fichier en traite trois de plus depuis ;
- `app/adhesions.go.md` affirmait encore que « le rappel automatique n'est pas codé » — il tournait depuis des semaines, documenté ailleurs (`app/rappels.go.md`) ;
- `Auth.php.md` décrivait encore `urlEspace()` renvoyant `/mon-espace`, le lien mort corrigé en vague 3 ;
- `back_routes.php.md` et `front_routes.php.md` dataient de la phase 9 (2 et 4 routes), quand les fichiers réels en comptent 22 et 14 ;
- `menu_back.php.md` citait encore la rubrique « Créneaux », renommée en « Services » depuis la vague 2 ;
- `db/commercantsRepository.go.md` et `models/commercant.go.md` ignoraient totalement `utilisateur_id`, ajouté avant cette session mais jamais répercuté dans leur documentation.

Deux fichiers de code n'avaient **jamais** eu de `.md` : `app/traductions.go` (326 lignes, 9 routes) et `models/traduction.go`. Écrits cette fois.

**Vérification finale de la documentation** : 409 liens croisés dans tout le projet, aucun mort ; couverture à 100 % — chaque fichier `.php` et `.go` a désormais son `.md`, sans exception.

**Vérifications fonctionnelles** : `tester-tous-les-endpoints.py` → **80/80** (77 + les 3 nouvelles routes : `GET /adhesions/`, `PUT /commercants/{id}`, et le libellé corrigé du test d'échec attendu 500→502), `tester-espace-client.py` → **23/23**, les deux rejouables. Les 22 écrans du back-office chargés un par un, dans les quatre langues, sans une seule clé de traduction non résolue. 1 948 libellés en base après restauration (487 clés × 4 langues, moins les orphelines supprimées en cours de route).

**Ce qui reste à faire** : les deux exigences 🟥 encore ouvertes — le déploiement sur un serveur réel avec HTTPS (11.2) et le script d'installation (12.1), qui réglera au passage le problème du tout premier compte administrateur (créer un admin exige d'être admin, ce qui n'a pas de solution purement applicative).

## 2026-08-08 (suite) — Phase 12.1 : le script d'installation, et le problème de l'œuf et de la poule

**Ce qui est fait.** `install.sh`, qui installe le projet en une seule commande sur une machine neuve : vérifie Docker et curl, crée le `.env` s'il n'existe pas encore (avec un secret JWT généré au hasard, jamais recopié d'un modèle), démarre les conteneurs, attend que l'API réponde vraiment, puis crée le tout premier compte administrateur.

**Le vrai sujet de cette phase, c'est le point 4.** `POST /utilisateurs/` — créer un compte *avec un rôle* — est réservé à `admin_back`, et à raison : pouvoir créer des comptes, c'est pouvoir se fabriquer un accès. Mais sur un serveur tout juste installé, aucun compte n'existe. Personne ne peut donc créer le premier administrateur depuis l'application elle-même : il faudrait déjà en être un. Ce n'est pas un bug — c'est structurel, sans solution purement applicative.

La solution retenue est celle que j'utilisais déjà sans y penser dans `tests/tester-tous-les-endpoints.py` pour préparer son compte de test : créer un compte normal via la route *publique* d'inscription (qui donne toujours le rôle `adherent`), puis le promouvoir en `admin_back` par une requête SQL directe. `install.sh` fait exactement ça, une seule fois, à l'installation.

**Rejouable, comme les scripts de test.** Le script vérifie d'abord si un `admin_back` existe déjà (`SELECT count(*) FROM utilisateurs WHERE role='admin_back'`) avant de proposer d'en créer un. Le relancer sur une installation déjà faite ne crée pas de doublon, et ne touche pas à un `.env` existant — j'ai testé les deux cas explicitement : `.env` absent (secret généré, fichier créé) et `.env` présent (conservé tel quel, aucune écriture).

**Un secret JWT différent par installation.** `.env.example` contient un jeton `change_me_a_generer`, jamais une vraie valeur. Si toutes les installations du projet partageaient le secret de développement, n'importe qui le connaissant pourrait fabriquer un jeton valide pour n'importe quel serveur exécutant ce code — un peu comme si toutes les portes du monde avaient la même clé. Généré avec `/dev/urandom` + `base64` + `tr`, sans dépendance externe (pas d'`openssl`) : même philosophie « bibliothèque standard uniquement » que côté Go.

**Un détail qui a failli passer inaperçu : attendre que l'API soit prête, pas juste lancée.** `docker compose up -d` rend la main dès que les conteneurs sont démarrés, pas dès qu'ils acceptent des connexions — PostgreSQL met quelques secondes à être joignable. Sans une boucle d'attente, la création du compte admin aurait échoué au hasard selon la vitesse de la machine, le genre de bug qui ne se reproduit jamais deux fois pareil et qui aurait été un cauchemar à démontrer en soutenance. La boucle interroge la route de santé de l'API à travers nginx (`curl -f`, qui échoue sur le 503 renvoyé tant que la base n'est pas prête) plutôt que l'API directement : le script s'exécute sur la machine hôte, où `api-go:8080` n'existe pas — ce nom n'est résolu qu'à l'intérieur du réseau Docker, exactement la même distinction que celle déjà documentée côté `ApiClient.php`.

**Testé chaque brique séparément avant l'ensemble.** La détection d'un admin existant, la création + promotion avec un compte jetable (supprimé ensuite), la génération du secret en isolation dans un dossier de test à part (sans toucher au vrai `.env`), puis le script complet exécuté de bout en bout — qui a rebâti les trois images Docker et redémarré tous les conteneurs sans encombre. Les deux suites de non-régression sont restées au vert après ce redémarrage complet (77+3 puis 80/80, 23/23), preuve que le script ne perturbe rien de ce qui tournait déjà.

**Vérifications** : `bash -n install.sh` (syntaxe), les trois branches testées isolément, l'exécution complète du script sur l'installation existante, puis `tester-tous-les-endpoints.py` → **80/80** et `tester-espace-client.py` → **23/23** après le redémarrage qu'il a déclenché. Documentation à jour : `install.sh.md` créé, `README.md` mis à jour avec les deux chemins de démarrage (rapide pour le développement, `install.sh` pour une vraie installation), `.env.example` ajouté aux côtés du `.env` de développement existant.

**Reste à faire** : la phase 11.2 (déploiement sur un serveur réel avec HTTPS) — actuellement bloquée par un problème sur la VM de test, à reprendre une fois celle-ci de nouveau disponible. C'est elle qui utilisera concrètement ce script d'installation pour la première fois en conditions réelles.

<!-- Entrée suivante à compléter au prochain morceau de code (phase 11.2 : deploiement, une fois la VM disponible) -->
