# TODO — Mission 1 : NO MORE WASTE (Rattrapage Projet Annuel 2 ESGI)

> ⏱️ **Lecture : ~55 min** · 7267 mots

> Objectif : livrer une application web fonctionnelle (Front PHP/JS + API Go) qui gère adhésions, collectes, stocks, tournées, bénévoles et services — déployée sur un vrai serveur, multilingue, avec génération de PDF et d'Excel.
> 
> Règle du jeu : après **chaque** fonctionnalité codée (même avec l'aide de l'IA), tu fais une **session de live coding** avec moi. Le but n'est pas de vérifier que ça marche (ça, tu peux le tester seul), mais de vérifier que **tu es capable de ré-expliquer, modifier et déboguer ce code sans l'IA**. Voir le format en bas du document.

## ✅ VAGUE 1 FAITE — le socle du portage (2026-08-03)

Le socle des 31 écrans est en place et vérifié. **Le site tourne désormais sur deux gabarits.**

- **Deux gabarits** : `layout_back.php` (barre latérale sombre, dense) et `layout_front.php` (aéré). Choisis **automatiquement par le dossier de la vue** (`back/` ou `front/`) — une convention qui existait déjà trois fois dans le projet, donc aucune règle nouvelle à retenir, et impossible à oublier.
- **`app/config/menu_back.php`** : les 5 sections du menu décrites une seule fois, en clés de traduction. La table `parents` gère les écrans de détail (une fiche surligne l'entrée de sa liste).
- **`Vue::afficher()` prend un 4e paramètre `$options`** (fil d'Ariane, sous-titre, boutons, onglets), rétrocompatible : les 6 appels existants n'ont pas bougé.
- **Gardes `exigerAdherent` / `exigerBenevole`** + `Auth::urlEspace()`. `exigerStaff` a gardé sa signature exacte.
- **86 clés i18n** cohérentes dans les 4 langues, importées en base. Les 2 clés `demo.*` parasites supprimées.
- **Pages d'erreur 404/500** refaites en Bootstrap (l'ancien CSS écrit à la main contredisait la contrainte du projet).

🐛 **Une faille corrigée au passage** : `extract($donnees)` écrasait `$fichier` dans `Vue::rendre()`. Une vue à qui l'on aurait passé `['fichier' => ...]` — plausible pour un écran de documents — aurait chargé un fichier arbitraire. Corrigé par `EXTR_SKIP`.

**Vérifié** : les 9 routes, les gardes (adhérent sur `/back` → 403), la barre latérale traduite dans les 4 langues (*Panoramica, Rete, Logistica…*), le filtre par ville toujours fonctionnel, un seul `<h1>` par écran, 404 personnalisée. API non régressée : **77/77** et **17/17**.

---

## 🎯 PROCHAINE ÉTAPE — Vagues 3 et 4 du portage

**Décision prise : c'est la V2.4 qui est retenue** (`Code/maquettes-v2.4/`), 28 écrans, couverture d'API complète.

### ✅ Fait — vague 1 (le socle), le 2026-08-03

- Deux gabarits : `layout_back.php` (barre latérale sombre) et `layout_front.php` (en-tête clair), **choisis automatiquement** par le préfixe du chemin de vue (`back/` ou `front/`) — impossible d'oublier de le préciser.
- `app/config/menu_back.php` : le menu décrit une fois, avec sa table `parents` pour garder l'entrée surlignée sur les écrans de détail.
- `Vue::afficher()` reçoit un 4ᵉ paramètre `$options` (fil d'Ariane, sous-titre, actions, onglets) — **rétrocompatible**, les 5 appels existants n'ont pas bougé.
- Faille corrigée au passage : `extract($donnees, EXTR_SKIP)`, sans quoi une vue recevant une clé `fichier` aurait pu charger un fichier arbitraire.

### ✅ Fait — vague 2 (les modules du sujet), le 2026-08-07

**9 écrans, tous testés dans les 4 langues** : bénévoles (+ fiche), collectes (+ détail avec scan), stocks, emplacements, tournées (+ détail), services et créneaux.

Les deux exigences 🟥 qui n'étaient atteignables par aucun écran le sont maintenant :
- le **récapitulatif PDF** d'une livraison (`/back/livraisons/@id/pdf`) ;
- le **planning CSV** de la journée (`/back/plannings?date=…`).

Les deux passent par le front et non par un lien direct vers l'API : le navigateur n'emporterait pas le jeton, rangé en session PHP — vérifié, l'API répond 401.

**Quatre défauts de l'API trouvés en portant les écrans**, alors que les deux suites de tests étaient au vert (77/77, 17/17) :
1. `GET /tournees/{id}/etapes` ne renvoyait pas `livraison_id` — le PDF exigé par le sujet était donc inatteignable. Corrigé par un `LEFT JOIN`.
2. Les heures (`TIME`) sortaient en `"0000-01-01T14:00:00Z"`. Corrigé par `to_char(..., 'HH24:MI')` côté SQL, sur les tournées **et** les créneaux.
3. Le type de service était un champ libre alors que la base impose 7 valeurs (`CHECK`). Corrigé en menu déroulant + revalidation serveur.
4. Une violation de `CHECK` répondait 500 au lieu de 400. Le code PostgreSQL `23514` a rejoint `23503` et `23505` dans `utils.ErreurServeur`.

**Documentation à jour** : chaque fichier `.php` du front a son `.md` (y compris les blocs de gabarit), 323 liens entre documents vérifiés dans tout le projet, aucun mort. Voir `documentation-par-phase/Phase 11 - Portage du back-office (vagues 1 et 2).md`.

### ✅ Fait — vague 3 (l'espace client), le 2026-08-07

**7 écrans** : espace commerçant, espace bénévole, catalogue public des services, détail d'un service avec inscription, candidature bénévole, page de remerciement, plus deux écrans « compte sans fiche ».

Le sujet demande « à la fois un back-office et un **front office (utilisé par les clients)** ». Jusqu'ici le second n'existait que de nom.

**Une faille d'autorisation trouvée avant d'écrire une ligne de front** : `POST /creneaux/{id}/inscriptions` lisait le `commercant_id` du corps de la requête. Un adhérent envoyant l'identifiant d'un tiers inscrivait **la boutique de ce tiers** — vérifié, la requête répondait 201. Corrigé : le personnel inscrit autrui (c'est son travail), un adhérent ne peut inscrire que lui-même, ses identifiants étant écrasés par ceux du jeton.

**Deux trous qui rendaient l'espace client inutilisable** :
- `CreateCommercant` n'écrivait pas `utilisateur_id` : une boutique n'était reliée à aucun compte, et seul du SQL manuel pouvait faire la liaison ;
- même trou côté bénévoles — mais **pas la même règle** : `POST /commercants/` est réservé au personnel (lire l'identifiant envoyé y est sûr), `POST /benevoles/candidature/` est **publique** (le compte doit venir du jeton, jamais du corps).

**Un lien mort depuis la vague 1** : `Auth::urlEspace()` renvoyait `/mon-espace`, une adresse qui n'a jamais existé. Le lien « Mon espace » de l'en-tête répondait 404 — personne ne s'était connecté en adhérent depuis.

**Le piège du tableau vide** : `json_encode([])` produit `"[]"` et non `"{}"`. L'API attend toujours un objet ; elle répondait « JSON invalide » pour une requête qui n'avait rien à transmettre. Corrigé dans `ApiClient`.

**Vérifié** : `77/77` et `23/23` (la suite espace client passe de 17 à 23 vérifications, +6 sur la nouvelle règle, toujours rejouable). 352 clés de traduction par langue. Documentation : chaque `.php` du front a son `.md`, 353 liens vérifiés dans le projet, aucun mort. Voir `documentation-par-phase/Phase 11b - Portage de l'espace client (vague 3).md`.

### ✅ Fait — vague 4 (les écrans d'administration), le 2026-08-08

**22 écrans au total** pour le back-office : adhésions (+ écran de rappels), bénéficiaires, fiche et création de commerçant, campagnes, utilisateurs et rôles. Tous testés dans les 4 langues.

Deux routes API manquaient et ont été ajoutées en portant les écrans :
- `GET /adhesions/` — sans elle, impossible de voir les adhésions que le back-office est censé gérer, seulement celles qui tombent à J-30/J-7 exactement ;
- `PUT /commercants/{id}` — sans elle, une boutique créée sans compte restait orpheline pour toujours.

Un `500` corrigé en `502` : la relance d'adhésion accusait le serveur d'un bug qui n'était pas le sien (SMTP non configuré). Voir `utils.ErreurEmail`.

**Suivi d'une relecture complète de toute la documentation du projet** (pas seulement des fichiers de la semaine) : plusieurs `.md` décrivaient un état périmé du code (routes disparues, "à faire" déjà fait, champs absents). Deux fichiers de code n'avaient jamais eu de documentation (`app/traductions.go`, `models/traduction.go`) : comblé. Résultat : 409 liens croisés vérifiés dans tout le projet, aucun mort, couverture à 100%.

Voir `documentation-par-phase/Phase 11c - Portage des ecrans d'administration (vague 4).md` pour le détail.

### Le patron suivi, écran par écran

1. Reprendre le HTML de la maquette dans un fichier de vue PHP.
2. Remplacer les données en dur par les variables du contrôleur.
3. Entourer **tous** les libellés de `Langue::t('cle')` et ajouter les clés dans les 4 fichiers `app/locales/*.json`, **puis** cliquer « Fichiers vers base » sur `/back/traductions` — sans quoi le prochain export les effacerait.
4. Passer chaque donnée affichée par `Vue::e()` (protection XSS).
5. Écrire le contrôleur : garde de rôle → appel API → normalisation du `null` que Go renvoie pour une slice vide (`?? []`) → `Vue::afficher`.
6. **Tester les 4 langues.** Un libellé oublié reste en français quand on change de langue — c'est le seul symptôme.

⚠️ **Points des maquettes toujours NON supportés par l'API**, connus et assumés — à décider : enrichir l'API, ou retirer ces éléments des vues.
1. Le **tri** des colonnes et la **pagination** des listes (paramètres `tri` et `page`).
2. La **modification de son propre profil** et le changement de mot de passe (écran Mon compte) — pas codé, non essentiel au sujet.
3. La **configuration des rappels** — délais, fréquence, modèles d'email (écran Configuration des rappels). Aujourd'hui les valeurs J-30 / J-7 / 180 j sont écrites en dur dans `utils/scheduler.go` : les changer impose un redéploiement. Supposerait une table `parametres_rappels` et des routes `GET/PUT /parametres/rappels` — même démarche que pour les traductions.
4. Les **profils de rappel** — un rythme de relance différent selon le commerçant (Standard J-30/J-7, Rapproché, Léger). Maquetté, **non codé**. Choix de conception retenu si on l'implémente : des **profils** (chaque commerçant en a exactement un) plutôt que des *exceptions par groupe*, dont la résolution dépendrait d'un ordre de priorité invisible et difficile à déboguer. **Pas demandé par le sujet** : à présenter comme une piste d'amélioration.

> ✅ La création d'un compte **avec choix du rôle** est comblée depuis la vague 2 : `POST /utilisateurs/`, réservé à `admin_back` — et l'écran qui l'utilise est fait depuis la vague 4.

> Rappel du contexte : les maquettes chargent le Bootstrap du vrai front (`../front-php/public/assets/`), donc l'habillage est strictement identique une fois porté.

---

## 🎯 PROCHAINE ÉTAPE — Ce qu'il reste avant la fin du projet

Les 22 écrans du back-office et les 7 écrans du front office/espace client sont tous posés et testés (80 + 23 vérifications automatiques, rejouables). ✅ Le script d'installation (12.1) est fait le 2026-08-08 — voir Phase 12 ci-dessous.

Il reste une exigence 🟥 du sujet :

1. **Phase 11.2 — Déploiement sur un serveur réel avec HTTPS.** Le projet tourne aujourd'hui uniquement en local via Docker Compose. En cours : bloqué temporairement par un souci sur la VM de test, à reprendre dès qu'elle est de nouveau disponible — `install.sh` sera utilisé pour cette installation.

Le mini cahier des charges (Phase 0.2, 1-2 pages) reste également à rédiger.

---

## Stratégie de séquencement retenue (2026-07-31)

Décision : on termine **toute l'API Go** (Phases 4, 5, 6, 7, puis consolidation 10) avant de basculer sur le front FlightPHP. Les tâches liées au front (1.3 page de connexion multilingue, 2.4 vue back-office, Phase 8 multilingue, Phase 9 back-office centralisé) restent explicitement en attente jusqu'à ce que l'API soit intégralement codée et testée. Ça évite les allers-retours entre les deux codebases et permet de garder le même rythme de travail (endpoint par endpoint, testé via curl) sur tout le périmètre API avant de changer de contexte technique.

> 📄 **Un résumé de lecture par phase existe dans `Documents/documentation-par-phase/`** (un fichier par phase terminée : besoin métier, ce qui a été codé, logique clé, comment tester, liens vers les `.md` détaillés). Niveau intermédiaire entre cette todo (très condensée) et les `.md` à côté de chaque fichier de code (très détaillés) — pratique pour réviser une phase entière avant une session de live coding.

## Légende des tags

- 🟥 **[SUJET]** — explicitement écrit dans le sujet de rattrapage. C'est ce que le jury peut te demander de justifier point par point → priorité absolue pour la maîtrise.
- 🟧 **[ADAPTATION]** — pas nommé littéralement dans le texte, mais indispensable pour réaliser correctement un point du sujet (ex : il faut un modèle de données pour gérer les stocks, même si le mot "modèle" n'apparaît pas).
- 🟦 **[BONUS]** — absent du sujet, ajouté pour aller plus loin. **Ce n'est pas du hors-sujet** : c'est ce qui montre qu'on a réfléchi au-delà de la commande, et ça se valorise à l'oral. En cas de manque de temps, c'est en revanche ce qu'il faut repousser en premier — le jury cherchera d'abord les 🟥.

---

## Phase 0 — Cadrage & fondations (à faire avant tout code métier)

- [x] **0.1** 🟧 Lister les entités du système et leurs relations (schéma simple) : `Commerçant`, `Produit`, `Collecte`, `Tournée`, `Bénévole`, `Service`, `Planning`, `Destinataire` (association caritative / particulier), `Utilisateur/Rôle` _(le sujet ne demande pas de schéma formel, mais liste ces objets métier implicitement via les modules demandés — c'est la base de tout le reste)_
- [ ] **0.2** 🟦 Rédiger un mini cahier des charges (1-2 pages) : ce que fait chaque module, qui y accède
- [x] **0.3** 🟦 Créer la structure du repo : dossier front (PHP/JS), dossier `api` (Go), dossier `docs`
- [x] **0.4** 🟧 Écrire le schéma de BDD (migrations SQL) — tables + clés étrangères
- [x] **0.5** 🟥 **[SUJET]** _"le produit rendu devra être packagé pour pouvoir être aisément déployé (prévoir un script pour installer/copier les répertoires, bibliothèques, fichiers utiles et les bases de données si nécessaire)"_ → mets en place Docker proprement dès le départ (Dockerfile qui installe les dépendances au build, pas au démarrage du conteneur)
- [x] **0.6** 🟦 Premier `docker compose up` qui fonctionne en local

> 🎤 **Live coding #1** : setup Docker + schéma BDD.

**État réel (voir `Code/api-go`, `Code/docker-compose.yml`, `Code/postgres/init/schema.sql`) :**
- Structure du monorepo posée : `api-go/` (Go), `front-php/` (FlightPHP), `nginx/`, `postgres/`.
- `schema.sql` unique (pas d'outil de migration versionnée, conforme au style du cours ESGI) avec toutes les tables métier : utilisateurs, langues, sites, commercants, adhesions, benevoles, competences, benevole_competences, benevole_documents, emplacements_stock, collectes, produits, beneficiaires, tournees, tournee_etapes, livraisons, livraison_produits, services, creneaux_service, inscriptions_service.
- 4 conteneurs Docker fonctionnels : postgres (healthcheck OK), api-go (connecté à la DB), front-php, nginx (point d'entrée unique sur le port 8080).
- 2 bugs Docker trouvés et corrigés en testant : volume `vendor/` PHP écrasé par le bind mount (fixé par un volume nommé dédié), pages d'erreur 404/500 personnalisées non déclenchées pour les erreurs venant des backends (fixé par `proxy_intercept_errors`/`fastcgi_intercept_errors` dans nginx).
- Reste à faire : le mini cahier des charges (0.2) est encore à rédiger — ce n'est pas du code, c'est un livrable écrit séparé.

---

## Phase 1 — Authentification & rôles

- [x] **1.1** 🟧 Système d'auth (login/register) avec rôles (`staff_nmw`, `commercant`, `benevole`) — nécessaire pour distinguer _"back-office (utilisé par NO MORE WASTE)"_ et _"front office (utilisé par les clients de NO MORE WASTE)"_ 🟥 **[SUJET]**
- [x] **1.2** 🟧 Middleware / guard qui restreint l'accès selon le rôle
- [x] **1.3** 🟦 Page de connexion multilingue (prépare la Phase 8)

> 🎤 **Live coding #2** : middleware de vérification de rôle.

**État réel (voir `Code/api-go/app/auth.go`, `Code/api-go/utils/jwt.go`, `Code/api-go/utils/guard.go`, `Code/api-go/app/admin.go`) :**
- `POST /auth/register` : inscription avec mot de passe haché bcrypt, rôle `adherent` par défaut. Testé (201 Created, 409 si email déjà utilisé).
- `POST /auth/login` : vérifie les identifiants, retourne un JWT signé HS256. Testé (200 avec token, 401 si mauvais mot de passe).
- `GET /auth/me` : lit le token dans le header `Authorization`, retourne l'utilisateur courant (sans le mot de passe haché). Testé (200 avec token valide, 401 si token invalide).
- `utils.RequireRole(w, r, ...roles)` : fonction de vérification de rôle appelée manuellement en début de handler (pas de middleware générique qui wrap automatiquement les routes — choix conforme au style "simple" du cours, chaque handler protégé reste explicite sur ce qu'il exige). Testée sur une route de démo `GET /admin/ping` réservée à `admin_back`/`staff_back` : 401 sans token, 403 avec un rôle `adherent`, 200 avec un rôle `staff_back`.
- 1.1 est marqué **partiellement fait** (`[~]`) : les 3 routes de base existent et sont testées côté API Go, le rôle staff est démontré fonctionnel (compte de test inséré manuellement en base, pas encore via un vrai endpoint de création de staff), mais il manque encore un endpoint pour créer proprement un compte `staff_back`/`admin_back` (pas juste via SQL manuel) et toute la partie front (pages de connexion PHP côté back-office et front-office).
- 1.2 fait : `RequireRole` gère plusieurs rôles autorisés à la fois, renvoie 401/403 correctement, validé par 3 scénarios de test (sans token / mauvais rôle / bon rôle).
- **1.3 fait le 2026-08-01** : page de connexion FlightPHP en 4 langues (`app/views/front/connexion.php`). Formulaire en **POST** (jamais GET : le mot de passe apparaîtrait dans l'URL, donc dans l'historique et les logs). Après une erreur, l'email est réaffiché mais **jamais le mot de passe**. Message volontairement vague (« Email ou mot de passe incorrect ») pour ne pas révéler quelles adresses ont un compte.
- Point à savoir défendre sur 1.3 : après la connexion, le front fait un **2e appel** à `GET /auth/me/` pour obtenir le rôle, au lieu de décoder le JWT lui-même. Raison : un JWT n'est pas chiffré, c'est du base64 lisible **et fabricable**. Seule sa signature le rend fiable, et elle se vérifie avec la clé secrète qui vit dans l'API.
- ✅ **1.1 terminé le 2026-08-03** : `POST /utilisateurs/` crée un compte **avec choix du rôle**, réservé à `admin_back`. Le SQL manuel n'est plus nécessaire.
  - `admin_back` seul, pas `staff_back` : **pouvoir créer des comptes, c'est pouvoir se fabriquer un accès**. Un membre du personnel pourrait se créer un second compte administrateur et contourner les limites de son propre rôle.
  - Liste blanche des 4 rôles : sans elle, un rôle inventé (`super_admin`) créerait un compte refusé par **toutes** les gardes, et personne ne comprendrait pourquoi.
  - ✅ **Le problème du premier compte est réglé le 2026-08-08** : créer un admin exigeait d'être admin, sans solution purement applicative possible — `install.sh` (12.1) le fait une seule fois à l'installation (compte créé via la route publique, promu par SQL direct).

---

## Phase 9bis — Espace client du front-office (fait le 2026-08-03)

🟥 Répond à *« front office (utilisé par **les clients** de NO MORE WASTE) »* — un commerçant ou un bénévole connecté ne voyait jusqu'ici rien de plus qu'un visiteur anonyme.

- [x] `GET /mon-espace/commercant` — sa fiche **et** ses adhésions (l'adhésion conditionne tout le reste : un adhérent expiré ne peut plus être collecté)
- [x] `GET /mon-espace/collectes` — ses collectes
- [x] `POST /mon-espace/collectes` — demander une collecte, l'action principale d'un commerçant
- [x] `GET /mon-espace/benevole` — sa fiche, ses documents, ses compétences
- [x] `GET /mon-espace/planning` — ses créneaux à venir

**La règle de sécurité à savoir défendre** : aucune de ces routes n'accepte d'identifiant venant du client. On part toujours du jeton (`jeton → email → compte → sa fiche`). Une route du type `GET /mon-espace/collectes?commercant_id=7` aurait permis à n'importe qui d'essayer `8`, `9`, `10`… et de lire les données des autres. C'est la faille dite de **référence directe non sécurisée**.

**Un vrai défaut trouvé en rejouant le test** : `commercants.utilisateur_id` et `benevoles.utilisateur_id` n'avaient **aucune contrainte d'unicité**. Deux fiches se sont retrouvées liées au même compte, et la recherche « quelle est MA fiche ? » renvoyait l'une ou l'autre selon l'humeur de la base. Corrigé par `UNIQUE` dans le schéma (`NULL` reste autorisé plusieurs fois : une fiche peut exister sans compte).

**Vérifié** : `python tests/tester-espace-client.py` → **17/17**, rejouable. Il teste notamment qu'un bénévole n'accède pas à l'espace commerçant (403), que le personnel passe par le back-office (403), et qu'**un adhérent ne peut pas se promouvoir administrateur** (403).

---

## Phase 2 — Adhésions des commerçants

- [x] **2.1** 🟥 **[SUJET]** _"gérer les adhésions des commerçants (informations générales, identification, …)"_
- [x] **2.2** 🟧 Statut d'adhésion (active / expirée / en attente) + date de renouvellement — nécessaire pour déclencher le rappel ci-dessous
- [x] **2.3** 🟥 **[SUJET]** _"prévoir un système de rappel automatique de renouvellement"_
- [x] **2.4** 🟦 Vue back-office : liste des commerçants + filtre (par **ville** et non par statut, voir état réel ci-dessous)

> 🎤 **Live coding #3** : le job planifié de rappel automatique — c'est un point cité littéralement dans le sujet, à maîtriser à fond.

**État réel de 2.4 (fait le 2026-08-01)** — `Code/front-php/app/controllers/back/CommercantsController.php` + `app/views/back/commercants.php` :
- Tableau des commerçants + menu déroulant qui s'envoie tout seul (`onchange="this.form.submit()"`, le seul JavaScript du projet).
- ⚠️ **Le filtre porte sur la VILLE, pas sur le statut.** À savoir justifier : le statut appartient à l'**adhésion**, pas au commerçant — `GET /commercants/` ne le renvoie pas. Filtrer dessus demanderait un appel d'API par commerçant (trop coûteux) ou une nouvelle route côté API. La ville est directement disponible et pertinente (l'association est implantée dans 7 villes de 4 pays). Le mécanisme est identique, seul le champ change.
- Filtre appliqué **côté PHP** car l'API n'expose pas encore `?ville=`. Limite assumée : on transfère toute la liste à chaque affichage. Sans conséquence au volume d'une association ; à déplacer côté API si la liste devenait très grande.
- Le formulaire est en **GET** (et non POST) pour que la page filtrée soit partageable, rechargeable et ajoutable aux favoris (`/back/commercants?ville=Naples`). Règle : GET pour consulter, POST pour modifier.
- Piège évité : la liste des villes du menu est construite **avant** le filtrage. Dans l'autre ordre, le menu ne contiendrait plus que la ville sélectionnée et on ne pourrait plus en changer.

**État réel (voir `Code/api-go/models/commercant.go`, `Code/api-go/db/commercantsRepository.go`, `Code/api-go/app/commercants.go`, `Code/api-go/models/adhesion.go`, `Code/api-go/db/adhesionsRepository.go`, `Code/api-go/app/adhesions.go`) :**
- `POST /commercants` : crée un commerçant (raison sociale obligatoire, le reste optionnel). Réservé aux rôles `admin_back`/`staff_back`.
- `GET /commercants` : liste tous les commerçants. `GET /commercants/{id}` : récupère un commerçant précis.
- `POST /commercants/{id}/adhesions` : crée une adhésion rattachée à un commerçant précis (vérifie d'abord que le commerçant existe, sinon 404). Champs obligatoires : date_debut, date_fin, statut.
- `PUT /adhesions/{id}` : modifie/renouvelle une adhésion existante. Répond 204 en cas de succès, 404 si l'adhésion n'existe pas.
- 2.1 et 2.2 marques faits : le CRUD de base commerçants (creation 201, raison sociale obligatoire 400, liste, consultation, id inexistant 404) et la creation/modification d'adhesions sont codes et testes via curl le 2026-07-31.

**2.3 fait (2026-07-31), systeme complet (voir `Code/api-go/utils/mailer.go`, `Code/api-go/utils/scheduler.go`, `Code/api-go/db/rappelsRepository.go`, `Code/api-go/app/rappels.go`, plus le systeme de campagnes ci-dessous) :**
- `utils.EnvoyerEmail` : envoi reel via SMTP Brevo (net/smtp stdlib pur, pas de lib externe).
- Job automatique (`utils.DemarrerSchedulerRappels`) : une goroutine avec une boucle infinie + `time.Sleep(24 * time.Hour)` tourne en tache de fond, en parallele du serveur HTTP. Toutes les 24h, verifie 3 choses : adhesions a J-30, adhesions a J-7, et adhesions expirees/resiliees depuis 180 jours (relance "ca fait longtemps").
- Table `adhesion_rappels` (remplace l'ancien champ booleen `rappel_envoye`, insuffisant des qu'il y a plusieurs TYPES de rappel possibles) : garde une trace de chaque email envoye (type, date, destinataire), consultee avant chaque envoi pour ne jamais envoyer le meme rappel deux fois.
- `GET /adhesions/a-renouveler` : liste combinee des adhesions a J-30 et J-7 (pour un dashboard back-office).
- `POST /adhesions/{id}/relancer` : relance manuelle immediate d'une adhesion precise par le staff (type "manuel" dans l'historique).
- `GET /adhesions/{id}/historique-rappels` : historique des rappels deja envoyes pour une adhesion.
- `POST /admin/jobs/rappels-adhesions` : declenche manuellement le meme job que celui qui tourne automatiquement toutes les 24h -- indispensable pour demontrer le systeme en live sans attendre.
- Systeme de campagnes segmentees (demande explicite de l'utilisateur, va au-dela du strict minimum du sujet) : table `campagnes` (nom, sujet, corps, 4 criteres optionnels : ville, pays, statut d'adhesion, anciennete d'expiration en jours) + table `campagne_envois` (historique). `POST /campagnes` (creer), `GET /campagnes` (lister), `GET /campagnes/{id}/destinataires` (previsualiser qui va recevoir AVANT d'envoyer), `POST /campagnes/{id}/declencher` (envoyer reellement, avec personnalisation `{{raison_sociale}}` dans le corps).
- Critere de securite important : la resolution des destinataires (`db.ResoudreDestinatairesCampagne`) construit sa requete SQL avec des criteres FIXES predefinis (pas de query builder libre) -- aucune valeur n'est jamais collee directement dans le texte SQL, tout passe par des parametres `$N`, donc pas de risque d'injection SQL meme si un critere contient du texte malveillant.
- Teste via curl le 2026-07-31 : creation commercant+adhesion a J-30, liste "a renouveler" (trouve bien l'adhesion), declenchement manuel du job (execute sans planter, log une erreur SMTP propre car `.env` a encore des cles Brevo placeholder), verification que l'historique reste vide quand l'envoi echoue (pas de faux positif), creation de 2 campagnes avec criteres de ville differents (Paris trouve le commercant, Lyon ne trouve personne -- la segmentation fonctionne correctement).
- **Reste a faire pour un envoi reellement fonctionnel** : remplir `.env` avec de vraies cles SMTP Brevo (`SMTP_USER`/`SMTP_PASSWORD`), actuellement des placeholders `change_me`. La logique complete est deja validee, seul le compte SMTP manque.

- 2.4 (vue back-office avec filtre) pas commencee, depend du front FlightPHP.
- Manque encore cote API : PUT /commercants/{id} (modification) et DELETE /commercants/{id}, non prioritaires pour l'instant.

---

## Phase 3 — Gestion des stocks (avec code-barres)

- [x] **3.1** 🟧 Modèle `Produit` : nom, catégorie, état, date d'entrée, statut
- [x] **3.2** 🟥 **[SUJET]** _"Chaque produit rapporté au siège devra être référencé (code barre)"_
- [x] **3.3** 🟥 **[SUJET]** _"stocké et retrouvable très rapidement"_ → recherche/lecture rapide par code-barre
- [x] **3.4** 🟧 Système de recherche/filtre dans le stock (catégorie, date, statut)

> 🎤 **Live coding #4** : génération et lecture du code-barre — cité littéralement dans le sujet ("référencé (code barre), stocké et retrouvable très rapidement").

**État réel (2026-07-31, voir `Code/api-go/models/produit.go`, `Code/api-go/db/produitsRepository.go`, `Code/api-go/app/produits.go`, plus le module emplacements) :**
- `POST /emplacements`, `GET /emplacements`, `GET /emplacements/{id}` : CRUD simple des emplacements de stockage (entrepot/zone/rayon/etagere).
- `POST /produits` : crée un produit (code_barre + libelle obligatoires), valeurs par défaut appliquées si non fournies (quantite=1, statut="en_stock"), rejette un code-barre déjà utilisé (409 Conflict).
- `GET /produits?code_barre=XXX` : **la recherche rapide exigée par le sujet** — une simple égalité SQL sur une colonne indexée (`idx_produits_code_barre` dans `schema.sql`), quasi instantanée même avec beaucoup de produits.
- `GET /produits?categorie=...&statut=...` : liste filtrée combinable (mêmes deux query params optionnels, la même route bascule entre recherche exacte et liste filtrée selon si `code_barre` est fourni).
- `GET /produits/{id}` : consultation d'un produit précis.
- `PUT /produits/{id}` : déplace un produit (change son emplacement et/ou son statut), via un DTO dédié qui ne peut modifier QUE ces deux champs.
- Décision déjà validée : saisie manuelle/douchette USB (pas de décodage d'image côté serveur) — confirmée, aucune lib de lecture de code-barre nécessaire.
- Testé via curl le 2026-07-31 : création emplacement (201), création produit (201), doublon de code-barre rejeté (409), recherche par code-barre réussie, changement de statut/emplacement (204), liste filtrée par statut (trouve bien le produit modifié, liste vide pour l'ancien statut).
- `CollecteId` sur `Produit` reste optionnel pour l'instant (Phase 4, gestion des collectes, pas encore codée) — un produit peut exister sans être rattaché à une collecte, ce lien sera exploité une fois la Phase 4 codée.

---

## Phase 4 — Collectes

- [x] **4.1** 🟥 **[SUJET]** _"gérer le système des collectes"_
- [x] **4.2** 🟧 Statuts de la collecte (demandée / planifiée / effectuée)
- [x] **4.3** 🟧 Lien collecte → produits rapportés (rattache les produits scannés à la collecte)

> 🎤 **Live coding #5** : parcours complet d'une collecte.

**État réel (2026-07-31, voir `Code/api-go/models/collecte.go`, `Code/api-go/db/collectesRepository.go`, `Code/api-go/app/collectes.go`) :**
- `POST /collectes` : crée une collecte pour un commerçant OU un particulier (vérifie qu'au moins l'un des deux est fourni, 400 sinon), statut par défaut "demandee".
- `GET /collectes`, `GET /collectes/{id}` : liste (filtrable par statut) et consultation.
- `PUT /collectes/{id}` : change le statut et/ou affecte un bénévole (chauffeur), via un DTO dédié. Quand le statut passe à "realisee", `date_realisee` est rempli AUTOMATIQUEMENT côté SQL (`now()`), sans que le client ait besoin de l'envoyer.
- `POST /collectes/{id}/produits` : enregistre un produit DIRECTEMENT rattaché à la collecte (flux réaliste : le produit est scanné pendant la collecte, il n'existe pas avant) — réutilise `db.CreateProduit`/`db.GetProduitByCodeBarre` de la Phase 3, pas de duplication de code.
- `GET /collectes/{id}/produits` : liste tous les produits rattachés à une collecte précise.
- Testé via curl le 2026-07-31 : création (201), validation commerçant/particulier obligatoire (400), ajout de produit à une collecte (201), liste des produits d'une collecte, changement de statut vers "realisee" avec vérification que `date_realisee` a bien été rempli automatiquement (204 puis vérification GET), ajout de produit à une collecte inexistante (404), liste filtrée par statut.
- `BenevoleId` sur `Collecte` reste optionnel pour l'instant (Phase 6, bénévoles, pas encore codée).

---

## Phase 5 — Tournées de distribution | en cours

- [x] **5.1** 🟥 **[SUJET]** _"gérer les tournées de distribution (associations caritatives, particuliers en détresse, …)"_
- [x] **5.2** 🟧 Affectation des produits en stock à une tournée
- [x] **5.3** 🟥 **[SUJET]** _"Chaque livraison donnera lieu à l'émission d'un récapitulatif au format PDF"_
- [x] **5.4** 🟦 Historique des tournées consultable en back-office

> 🎤 **Live coding #6** : génération du PDF de récapitulatif — cité littéralement dans le sujet, à savoir expliquer précisément (quelle lib, comment les données y arrivent).

**État réel (2026-07-31, voir `Code/api-go/models/tournee.go`, `Code/api-go/db/tourneesRepository.go`, `Code/api-go/utils/pdf.go`, `Code/api-go/app/tournees.go`) :**
- `POST /beneficiaires`, `GET /beneficiaires?type=...` : gestion des destinataires (associations caritatives / particuliers en détresse).
- `POST /tournees` (vérifie que le bénévole chauffeur est "valide"), `GET /tournees?statut=...`, `GET /tournees/{id}`, `PUT /tournees/{id}`.
- `POST /tournees/{id}/etapes`, `GET /tournees/{id}/etapes` : les arrêts de la tournée, avec ordre de passage et heures prévues/réelles.
- `POST /tournee-etapes/{id}/livraison` : **le point central** — clôture un arrêt en 5 opérations (vérif anti-doublon 409, vérif que tous les produits existent AVANT d'en insérer aucun, création de la livraison, rattachement des produits **qui passent au statut "distribue" dans le stock**, marquage de l'étape "livre" avec `CURRENT_TIME`).
- `GET /livraisons/{id}/pdf` : **le récapitulatif PDF exigé par le sujet**. `GET /livraisons/{id}` : les mêmes données en JSON.
- **PDF généré en stdlib pur, sans aucune librairie** : le fichier PDF est écrit octet par octet (en-tête `%PDF-1.4`, 5 objets numérotés, instructions de dessin `BT/Tf/Td/Tj/ET`, table `xref` avec positions sur 10 chiffres, trailer, `%%EOF`). Résultat : un vrai `.pdf` de ~1,5 Ko qui s'ouvre dans n'importe quel lecteur. Limite assumée : accents convertis en équivalents simples (é→e), car gérer l'UTF-8 complet exigerait d'embarquer une police entière.
- **Phase qui connecte le plus de modules** : Phase 3 (les produits livrés sortent du stock), Phase 6 (seul un bénévole validé peut conduire), Phase 4 (le circuit collecte → stock → distribution est bouclé).
- Testé via curl le 2026-07-31 : création bénéficiaire/tournée/étape (201), clôture de livraison avec 2 produits (201 + URL du PDF), refus du doublon (409), vérification que les produits sont passés en "distribue", que l'étape est "livre" avec heure réelle automatique, et que le PDF (1584 octets) est structurellement valide avec tout le contenu attendu (en-tête, bénéficiaire, tableau des produits, total, ligne de signature).

---

## Phase 6 — Bénévoles

- [x] **6.1** 🟥 **[SUJET]** _"gérer le suivi des bénévoles, depuis leur candidature jusqu'à leur affectation à un service donné"_ + _"prenant en compte les différentes capacités qu'ils ont (chauffeurs, cuisiniers, plombiers, …)"_
- [x] **6.2** 🟥 **[SUJET]** _"chacun peut s'inscrire (…) à condition de valider un certain nombre de conditions"_ → validation de candidature avant affectation
- [x] **6.3** 🟥 **[SUJET]** affectation selon capacité (suite du point 6.1)

> 🎤 **Live coding #7** : logique de validation avant affectation — directement issue du texte du sujet, à bien maîtriser.

**État réel (2026-07-31, voir `Code/api-go/models/benevole.go`, `Code/api-go/db/benevolesRepository.go`, `Code/api-go/app/benevoles.go`) :**
- `POST /benevoles/candidature` : route **publique** (pas d'authentification), n'importe qui peut candidater. Statut "candidat" forcé côté serveur.
- `GET /benevoles`, `GET /benevoles/{id}` : consultation par le staff (filtre par statut possible).
- `PUT /benevoles/{id}/validation` : **le point central de la Phase 6**. Refuse de passer un bénévole au statut "valide" tant que TOUS ses documents ne sont pas eux-mêmes validés (`db.TousLesDocumentsSontValides`) — modélise fidèlement "valider un certain nombre de conditions" avant affectation. `date_validation` remplie automatiquement côté SQL.
- `POST /benevoles/{id}/documents`, `GET /benevoles/{id}/documents`, `PUT /benevoles/{id}/documents/{docId}/validation` : gestion des pièces/conditions à valider une par une par le staff.
- `GET /competences` : référentiel fixe (chauffeur, cuisinier, plombier, electricien, bricoleur).
- `GET /benevoles/{id}/competences`, `POST /benevoles/{id}/competences/{competenceId}`, `DELETE /benevoles/{id}/competences/{competenceId}` : gérer les capacités d'un bénévole (permet ensuite de l'affecter à une collecte comme chauffeur, ou à un service selon sa compétence).
- Testé via curl le 2026-07-31 : candidature sans token (201), tentative de validation sans document (400), ajout d'un document (201), tentative de validation avec document non validé (400), validation du document (204), validation du bénévole réussie (204) avec `date_validation` bien remplie, ajout de compétence (204), doublon de compétence rejeté (409), liste des compétences, retrait de compétence (204) — tous réussis.

---

## Phase 7 — Services (offres, planning, inscriptions)

- [x] **7.1** 🟥 **[SUJET]** _"la gestion des services (propositions, plannings, inscriptions)"_
- [x] **7.2** 🟥 **[SUJET]** inscriptions des bénévoles à un service (suite du point 7.1)
- [x] **7.3** 🟥 **[SUJET]** _"tous les jours, des plannings sont créés, édités et envoyés aux différents bénévoles sous la forme de fichiers Excel"_

> 🎤 **Live coding #8** : génération du planning Excel quotidien — cité littéralement, point très probable à l'oral.

**État réel (2026-07-31, voir `Code/api-go/models/service.go`, `Code/api-go/db/servicesRepository.go`, `Code/api-go/app/services.go`, `Code/api-go/utils/planning.go`, `Code/api-go/utils/schedulerPlanning.go`) :**
- `POST /services` (staff), `GET /services` et `GET /services/{id}` : **routes publiques** — le sujet dit que les services sont "accessibles aux adhérents", donc le catalogue est consultable sans authentification.
- `POST /services/{id}/creneaux`, `GET /services/{id}/creneaux` : gestion des créneaux (date, horaires, lieu, capacité).
- `PUT /creneaux/{id}/affectation` : **le point clé** — affecte un bénévole à un créneau, avec DEUX règles cumulatives : le bénévole doit être au statut "valide" (toutes ses conditions remplies, Phase 6) ET posséder la compétence exigée par le service s'il en exige une.
- `POST /creneaux/{id}/inscriptions` (accessible aux adhérents), `GET /creneaux/{id}/inscriptions` : inscriptions avec contrôle de capacité (409 si complet, 400 si créneau annulé).
- `GET /plannings?date=...` : télécharge le planning au format CSV (en-têtes HTTP de téléchargement de fichier).
- `POST /admin/jobs/plannings?date=...` : déclenche manuellement l'envoi des plannings par email.
- **Job automatique quotidien** ajouté dans la goroutine existante : chaque jour, pour chaque bénévole ayant au moins un créneau, génère son planning CSV et l'envoie par email **en pièce jointe** (MIME multipart + base64, stdlib pur).
- **Choix "Excel" à justifier à l'oral** : le sujet dit "fichiers Excel", mais le cours interdit toute librairie externe (pas d'`excelize`). Solution retenue : CSV généré avec `encoding/csv` (package standard, cité dans l'énoncé d'examen du cours), qu'Excel ouvre nativement. Avec BOM UTF-8 (accents corrects) et séparateur point-virgule (colonnes correctes en Excel français).
- **Changement de schéma** : ajout d'une colonne `email` sur la table `benevoles` — nécessaire pour leur envoyer le planning, la table ne stockait aucune adresse joignable auparavant.
- Testé via curl le 2026-07-31 : création service + créneau, catalogue public sans token, affectation refusée si bénévole non validé (400), refusée si compétence manquante (400), réussie une fois les deux conditions remplies (204), inscriptions avec capacité atteinte (409 au 3e sur capacité 2), génération CSV avec dates lisibles (`31/07/2026;14:00;17:00`), déclenchement du job d'envoi (trouve le bon bénévole, échoue proprement sur l'authentification SMTP placeholder).

---

## Phase 8 — Multilingue (i18n)

- [x] **8.1** 🟥 **[SUJET]** _"comme l'association s'est installée à l'étranger, à la demande des municipalités, le site devra être multilingue"_
- [x] **8.2** 🟧 Sélecteur de langue accessible sur le front
- [x] **8.3** 🟧 Vérifier que back-office ET front-office sont couverts (les deux sont cités dans le sujet, voir Phase 1)

> 🎤 **Live coding #9** : ajout d'une langue en direct.

**⚠️ REFONTE DU 2026-08-03 — les traductions sont maintenant en BASE, gérées depuis le back-office.**

Le système en fichiers PHP figés a été remplacé par celui du projet UpcycleConnect, adapté à PostgreSQL. Raison : un tableau PHP écrit en dur dans le code **n'est pas administrable** — corriger une faute de frappe en italien imposait de redéployer l'application. Le sujet demande un back-office ; il fallait que les libellés s'y modifient.

Architecture retenue (identique à UpcycleConnect dans le principe) :
- **Base** = source de vérité : tables `langues` et `traductions(cle, valeur, code_langue)`. C'est ce que le back-office édite.
- **Fichiers `front-php/app/locales/*.json`** = cache de lecture. Une page affiche 30 à 50 libellés : les chercher un par un en base ferait autant d'allers-retours réseau pour un seul écran.
- **Deux synchronisations manuelles** depuis `/back/traductions` : « Base vers fichiers » (le cas courant) et « Fichiers vers base » (mise en place / restauration après reset).

Ce qui a été ajouté par rapport à UpcycleConnect, et qu'il faut savoir justifier :
1. Contrainte **`UNIQUE (cle, code_langue)`** — UpcycleConnect ne l'avait pas : rien n'empêchait deux `nav.accueil` en français, et l'affichage devenait imprévisible. C'est elle qui rend l'`ON CONFLICT` possible.
2. **Clé étrangère = le code de langue** (`'fr'`) et non un id numérique : ce code est déjà ce qu'on manipule partout (nom du fichier, `?lang=fr`, `<html lang>`), donc plus de jointure pour le retrouver.
3. **`ON CONFLICT ... DO UPDATE`** : l'import est *idempotent*, on peut le relancer sans créer de doublon. Sans lui, il faudrait un SELECT puis un INSERT/UPDATE par clé — deux fois plus de requêtes, et une condition de course.
4. **Garde-fou** : l'export est refusé si la base est vide, pour ne pas écraser les fichiers JSON par du vide (perte irréversible).

Ce qui a été livré :
- API Go : `models/traduction.go`, `db/traductionsRepository.go`, `app/traductions.go`, **8 routes** (les 2 GET sont publiques, le reste réservé au staff).
- Front : `Langue.php` lit désormais les JSON, `services/Traductions.php` gère les 2 synchros, écran `/back/traductions` (tableau une ligne par clé × une colonne par langue, édition en place, traductions manquantes en orange).
- Migration : les 4 fichiers `app/i18n/*.php` ont été convertis puis **supprimés** (le dossier n'existe plus). **63 clés × 4 langues**, vérifiées cohérentes.

✅ **Testé en conditions réelles le 2026-08-03** — cycle complet validé :
- import fichiers → base : 63 clés × 4 langues chargées ;
- modification d'un libellé en back-office → la base contient le nouveau texte, **le site affiche encore l'ancien** (comportement attendu : le cache n'est pas régénéré) ;
- clic « Base vers fichiers » → *4 fichier(s) régénéré(s) (EN, FR, IT, PT), 254 libellé(s)* → **le site affiche le nouveau texte** ;
- **garde-fou validé** : table vidée puis export → refusé avec le bon message, les 4 fichiers intacts, site toujours affichable ;
- restauration fichiers → base après ce vidage : OK ;
- français protégé contre la suppression (400) ; import relancé 2 fois → 1 seule ligne (idempotent).

🐛 **Un vrai bug trouvé par ce test, et corrigé** : créer une clé déjà existante renvoyait **500** au lieu de **409**. La contrainte `UNIQUE` était bien respectée par la base, mais `utils/erreurs.go` ne traduisait pas le code PostgreSQL `23505` (unique_violation) — il ne gérait que `23503` (clé étrangère). Corrigé de façon **centralisée** : tous les handlers en bénéficient d'un coup. Vérifié : doublon → 409, langue inexistante → 400. Suite complète relancée 2 fois : **75/75**.

**État précédent (2026-08-01, remplacé) :** `app/middleware/Langue.php` + 4 fichiers de traduction (`fr`, `en`, `it`, `pt`), **40 clés chacun, vérifiés identiques**.

- **8.1** Choix de la langue par ordre de priorité : `?lang=xx` dans l'URL → session → en-tête `Accept-Language` du navigateur → français. Du plus explicite au plus deviné : un clic volontaire l'emporte toujours sur une déduction automatique.
- **8.2** Sélecteur de langue dans l'en-tête, présent sur **toutes** les pages. Les liens sont de la forme `?lang=it` sans chemin, donc on change de langue **sans quitter la page** où on se trouve.
- **8.3** Back-office **et** front-office traduits, y compris les en-têtes de colonnes des tableaux.
- Double filet de sécurité : une clé absente de la langue active retombe sur le français ; absente partout, c'est **la clé elle-même** qui s'affiche à l'écran — l'oubli devient visible au lieu de laisser un trou blanc.
- Point à savoir défendre : traduire n'est pas remplacer mot à mot. `SIRET` devient *Partita IVA* (it) et *NIF* (pt), car c'est une notion française qui n'existe pas ailleurs.
- Décision confirmée : multilingue limité à l'interface, pas au contenu métier saisi.
- Ajouter une langue = 1 fichier + 1 ligne dans `config.php`. C'est exactement l'exercice du live coding #9.

---

## Phase 9 — Back-office centralisé (finalisation)

- [x] **9.1** 🟥 **[SUJET]** _"il y a ici à la fois un back-office (utilisé par NO MORE WASTE) et un front office (utilisé par les clients de NO MORE WASTE)"_
- [x] **9.2** 🟧 Vérifier la séparation claire front-office / back-office dans les routes et permissions

> 🎤 **Live coding #10** : parcours complet du dashboard.

**État réel (fait le 2026-08-01) :**

- **9.1** Deux fichiers de routes distincts (`front_routes.php` / `back_routes.php`), toutes les adresses internes sous le préfixe `/back`, et deux thèmes de couleur (vert public / ocre interne) pour voir instantanément dans quel espace on se trouve pendant une démonstration.
- **9.2** `Auth::exigerStaff()` appelé **en première ligne** de chaque contrôleur de back-office — appel explicite plutôt que protection automatique, même logique que `utils.RequireRole` côté Go : la protection **se voit** en lisant le code, donc un oubli se remarque.
- Testé : adhérent connecté tentant `/back` → bloqué + message traduit ; lien back-office masqué dans son menu. ⚠️ Masquer le lien est du **confort**, pas de la sécurité — c'est la garde serveur qui protège.
- Distinction 401 (« je ne sais pas qui tu es » → page de connexion) et 403 (« je sais qui tu es, mais tu n'as pas le droit » → accueil + message).

**État réel :** pas commencé. Décision déjà validée : une seule app FlightPHP avec routes séparées (`/back/...` vs `/`), pas deux conteneurs distincts.

---

## Phase 10 — API Go : consolidation

- [x] **10.1** 🟥 **[SUJET]** _"Une application WEB (API en Go et front PHP ou dérivé de JavaScript)"_ → le choix Go pour l'API n'est pas optionnel, c'est imposé
- [x] **10.2** 🟦 Gestion des erreurs propre (pas de 500 silencieux)
- [x] **10.3** 🟦 (Bonus) Documentation des endpoints (Swagger/Postman)

> 🎤 **Live coding #11** : tu expliques 2-3 endpoints Go — le choix du langage étant imposé par le sujet, sois prêt à justifier pourquoi Go et pas autre chose.

**État réel (fait le 2026-07-31) :** phase de relecture, **aucune route ajoutée** — on repasse sur les 63 routes existantes.

- **10.1** respecté par construction (API en Go dès le départ, `net/http` stdlib).
- **10.2** — 5 corrections :
  1. Les **101** réponses 500 renvoyaient un message générique sans jamais écrire la vraie cause nulle part. Nouvelle fonction `utils.ErreurServeur(w, r, message, err)` qui log la cause côté serveur et répond au client. Appliquée aux 101 endroits.
  2. Une donnée invalide envoyée par le client (un `emplacement_id` inexistant) répondait **500** au lieu de **400**. Corrigé une seule fois dans `ErreurServeur` → les 101 handlers en bénéficient. A nécessité de convertir **97 lignes** de `db/` de `%v` vers `%w` (sinon l'erreur PostgreSQL d'origine était détruite et impossible à inspecter).
  3. Le health check faisait `panic(err)` si la base tombait → le client recevait une connexion coupée. Remplacé par un **503**.
  4. nginx remplaçait les messages d'erreur de l'API par ses pages HTML (`proxy_intercept_errors on` sur `/api/`) → passé à `off`. Les pages personnalisées restent actives pour le **site** (exigence du sujet préservée).
  5. Seule incohérence de nommage du projet corrigée : le champ `password` (anglais) devient `mot_de_passe`, cohérent avec tous les autres champs. Fait **avant** le front PHP, car changer un champ JSON casse le contrat d'API.
- **10.3** — `Code/api-go/NO-MORE-WASTE.postman_collection.json` : les 63 routes en 14 dossiers, corps d'exemple réalistes, rôle requis, variables `base_url`/`token`, capture automatique du JWT. Dossiers ordonnés selon les **dépendances réelles**, donc rejouable de haut en bas sur une base vide.
  - 🟦 En plus : `Code/tests/tester-tous-les-endpoints.py` rejoue les 66 requêtes et vérifie les codes HTTP → **66 OK / 0 échec**. Ce script a trouvé de vrais défauts (les deux 500 du point 2, un enchaînement métier impossible dans la doc, 3 exemples faux).
  - ❌ **Swagger non fait, volontairement** : la collection Postman remplit déjà ce rôle ; Swagger imposerait soit une librairie externe (interdite par le cours), soit un fichier OpenAPI écrit à la main qui doublonnerait la collection. À présenter comme un choix, pas comme un oubli.

**Confirmé correct par l'audit (rien à corriger) :** aucune fuite d'info (zéro `http.Error(w, err.Error(), ...)`), 404 bien gérés (18 repositories traitent `sql.ErrNoRows`), doublons en 409, codes 401/403 cohérents.

---

## Phase 11 — Déploiement final

- [x] **11.1** 🟦 Dépendances installées au build de l'image, pas au démarrage du conteneur (retour d'expérience de ton ancien projet)
- [ ] **11.2** 🟥 **[SUJET]** _"un serveur WEB personnel devra être configuré pour accueillir le site de NO MORE WASTE. La démonstration devra être effectuée sur ce serveur"_ — un site en localhost ne sera pas corrigé (règle explicite dans le sujet original d'UpcycleConnect, à prendre comme règle d'or ici aussi)
- [ ] **11.3** 🟦 Reverse proxy + HTTPS (Caddy ou nginx+certbot)
- [~] **11.4** 🟥 **[SUJET]** _"prévoir réécriture d'URL, codes d'erreurs etc …"_
- [ ] **11.5** 🟦 Test complet en conditions réelles

> 🎤 **Live coding #12** : tu refais le déploiement complet devant moi — le serveur perso + réécriture d'URL + codes d'erreur sont explicitement demandés, c'est un point de contrôle quasi certain.

**État réel :**
- 11.1 fait : Dockerfiles Go et PHP installent bien toutes les dépendances (`go mod download`/`composer install`) à l'étape de build de l'image, jamais au démarrage du conteneur.
- 11.4 marqué **partiellement fait** (`[~]`) : la réécriture d'URL (`try_files ... /index.php?$query_string`) et les pages d'erreur personnalisées 404/500 fonctionnent et sont testées **en local**, mais tout ça doit encore être re-testé une fois déployé sur le vrai serveur perso (11.2), pas seulement en localhost.
- 11.2/11.3/11.5 pas commencés : nécessitent un vrai serveur (pas juste `docker compose up` en local sur la machine de dev).

---

## Phase 12 — Packaging & documentation

- [x] **12.1** 🟥 **[SUJET]** _"prévoir un script pour installer/copier les répertoires, bibliothèques, fichiers utiles et les bases de données si nécessaire"_ — fait le 2026-08-08
- [x] **12.2** 🟦 README clair (comment lancer le projet en dev et en prod) — les deux chemins de démarrage documentés le 2026-08-08
- [~] **12.3** 🟦 Vérifier que rien n'est exposé publiquement par erreur (`.env`, `.git`, `vendor`) — le blocage nginx (`location ~ /\.(env|git) { deny all; }`) existe déjà ; reste à vérifier en conditions réelles une fois 11.2 fait
- [ ] **12.4** 🟦 Relecture finale du cahier des charges vs fonctionnalités livrées

**État réel — 12.1 fait le 2026-08-08** : `install.sh` (à la racine de `Code/`) installe le projet en une commande sur une machine neuve.

- Vérifie Docker + Docker Compose + curl avant de commencer.
- Crée `.env` depuis `.env.example` **seulement s'il n'existe pas déjà** (ne touche jamais à une configuration existante), avec un **secret JWT généré au hasard** — jamais recopié d'un modèle, pour qu'aucune installation ne partage le secret de développement.
- Démarre les conteneurs, puis **attend réellement que l'API réponde** (`docker compose up -d` rend la main dès que les conteneurs sont lancés, pas prêts — sans cette attente, l'étape suivante échouerait au hasard selon la vitesse de la machine).
- Résout **le problème du premier compte administrateur** noté depuis la Phase 1 : `POST /utilisateurs/` (créer un compte avec un rôle) exige déjà d'être `admin_back`, donc personne ne peut créer le premier administrateur depuis l'application. Le script crée un compte normal via la route publique `POST /auth/register/`, puis le promeut en `admin_back` par une requête SQL directe — même motif que `tests/tester-tous-les-endpoints.py` pour son compte de test, mais fait une seule fois, à l'installation.
- **Rejouable** : relancé sur une installation déjà faite, il détecte l'administrateur existant et ne fait rien de plus.

**Vérifié** : chaque branche testée séparément (`.env` absent/présent, admin absent/présent, génération du secret en isolation), puis le script complet exécuté de bout en bout (reconstruit les 3 images, redémarre tous les conteneurs). `tester-tous-les-endpoints.py` → **80/80** et `tester-espace-client.py` → **23/23** après ce redémarrage complet.

Documentation : `install.sh.md` (nouveau), `README.md` mis à jour avec les deux chemins de démarrage (`docker compose up -d` pour le développement, `./install.sh` pour une vraie installation).

---

## Résumé — les points 🟥 [SUJET] à maîtriser en priorité absolue

Ce sont les phrases qui apparaissent mot pour mot ou presque dans le sujet de rattrapage. Si tu dois choisir où concentrer ton effort de compréhension personnelle, c'est ici :

1. Adhésions commerçants + rappel automatique de renouvellement
2. Système des collectes
3. Stocks avec référencement code-barre, recherche rapide
4. Tournées de distribution + PDF récapitulatif à chaque livraison
5. Suivi bénévoles : candidature → validation de conditions → affectation par capacité
6. Services : propositions, planning quotidien envoyé en Excel
7. Back-office (staff) vs front-office (clients) bien séparés
8. Site multilingue
9. API en Go (imposé) + front PHP/JS
10. Déploiement sur serveur perso (pas de localhost), réécriture d'URL, codes d'erreur
11. Script de packaging/installation

---

## Format d'une session de live coding

À chaque checkpoint, prépare-toi à faire ça sans IA, sans copier-coller :

1. **Explique** en 2-3 phrases ce que fait la fonctionnalité et pourquoi tu l'as codée ainsi.
2. **Montre le flux** : de la requête utilisateur jusqu'à la réponse (front → API → BDD → retour).
3. **Modifie quelque chose en direct** (un champ, une règle, un filtre) pour prouver que tu comprends le code, pas juste que tu l'as généré.
4. **Débug volontaire** : je te donne une erreur ou un comportement inattendu, tu dois le localiser dans le code.

Priorise ces sessions sur les points 🟥 [SUJET] — ce sont ceux que le jury peut te questionner dessus précisément, en te demandant de justifier un choix qui correspond à une exigence explicite du cahier des charges.
