# NO MORE WASTE — Mission 1 (rattrapage projet annuel ESGI)

> ⏱️ **Lecture : ~15 min** · 1849 mots, 7 lignes de code

Application web pour l'association NO MORE WASTE (anti-gaspillage) : API en Go + front en FlightPHP, le tout en Docker.

## Démarrer le projet

**En local, pour lire/tester le code** (le `.env` fourni contient déjà des valeurs de développement) :

```bash
docker compose up -d
```

**Pour une vraie installation sur un serveur neuf** — 🟥 exigence du sujet (« application packagée pour être aisément déployée »), voir [`install.sh.md`](install.sh.md) :

```bash
./install.sh
```

En plus de démarrer les conteneurs, ce script génère un secret JWT propre à l'installation et crée le tout premier compte administrateur — impossible à faire depuis l'application elle-même, puisque créer un compte avec un rôle exige déjà d'être administrateur. Le script est rejouable : le relancer sur une installation déjà faite ne casse rien.

Le site est accessible sur `http://localhost:8080/` (front) et `http://localhost:8080/api/` (API).

## Documentation du code

Chaque fichier de code important a son propre fichier `.md` juste à côté (même nom + `.md`), qui explique ce qu'il fait, ligne par ligne, en partant du principe qu'on n'a jamais codé en Go. C'est fait exprès pour préparer les sessions de live coding (voir `Documents/TODO-Mission1-NoMoreWaste.md`).

> ⏱️ **Combien de temps ça prend**
>
> Chaque fichier `.md` affiche son temps de lecture en tête. Le parcours complet ci-dessous représente environ **8 h 50**, et l'ensemble de la documentation du projet (`Code/` + `Documents/`) environ **15 h 15**.
>
> Ce sont des durées de **première lecture attentive**, pas de survol. Relire pour *maîtriser* — l'objectif des sessions de live coding — prend en général le double. Un sujet déjà connu se lit deux fois plus vite.

### Ordre de lecture recommandé (suit le vrai chemin d'une requête)

**Socle (Phase 0)**
1. [api-go/app.go.md](api-go/app.go.md) — le point d'entrée du programme Go, le routage  *(⏱ ~5 min)*
2. [api-go/config/config.go.md](api-go/config/config.go.md) — d'où viennent les réglages (port, adresse DB...)  *(⏱ ~5 min)*
3. [api-go/db/db.go.md](api-go/db/db.go.md) — la connexion à Postgres  *(⏱ ~10 min)*
4. [docker-compose.yml.md](docker-compose.yml.md) — comment les 4 conteneurs s'assemblent, piège du dossier `vendor/`  *(⏱ ~5 min)*
5. [nginx/conf.d/nmw.conf.md](nginx/conf.d/nmw.conf.md) — réécriture d'URL, pages d'erreur personnalisées  *(⏱ ~10 min)*

**Authentification & rôles (Phase 1) — à lire, dans cet ordre :**
6. [api-go/models/utilisateur.go.md](api-go/models/utilisateur.go.md) — la forme des données utilisateur (structs)  *(⏱ ~10 min)*
7. [api-go/db/utilisateursRepository.go.md](api-go/db/utilisateursRepository.go.md) — les requêtes SQL (⏳ à terminer)  *(⏱ ~10 min)*
8. [api-go/app/auth.go.md](api-go/app/auth.go.md) — inscription / connexion / profil (⏳ à terminer)  *(⏱ ~20 min)*
9. [api-go/utils/jwt.go.md](api-go/utils/jwt.go.md) — créer et vérifier un token JWT (⏳ à terminer)  *(⏱ ~15 min)*
10. [api-go/utils/guard.go.md](api-go/utils/guard.go.md) — vérifier le rôle avant d'autoriser l'accès (⏳ à terminer)  *(⏱ ~10 min)*
11. [api-go/app/admin.go.md](api-go/app/admin.go.md) — exemple concret de route protégée par rôle (⏳ à terminer)  *(⏱ ~5 min)*

> ⏳ = à terminer de lire (session de live coding #2 en attente).

**Commerçants & adhésions (Phase 2, en cours) — nouveaux fichiers à lire à leur tour :**
12. [api-go/models/commercant.go.md](api-go/models/commercant.go.md) — la forme des données d'un commerçant  *(⏱ ~5 min)*
13. [api-go/db/commercantsRepository.go.md](api-go/db/commercantsRepository.go.md) — les requêtes SQL (Create/Get/List, dont la nouveauté "lire plusieurs lignes")  *(⏱ ~10 min)*
14. [api-go/app/commercants.go.md](api-go/app/commercants.go.md) — les 4 handlers (créer / lister / obtenir un commerçant, créer une adhésion), protégés par rôle  *(⏱ ~20 min)*
15. [api-go/models/adhesion.go.md](api-go/models/adhesion.go.md) — la forme des données d'une adhésion  *(⏱ ~5 min)*
16. [api-go/db/adhesionsRepository.go.md](api-go/db/adhesionsRepository.go.md) — les requêtes SQL (dont le premier `UPDATE` du projet)  *(⏱ ~5 min)*
17. [api-go/app/adhesions.go.md](api-go/app/adhesions.go.md) — modifier/renouveler une adhésion (`PUT`), pourquoi PUT et pas PATCH  *(⏱ ~5 min)*

**Rappel automatique de renouvellement (Phase 2.3, point le plus cité dans le sujet) :**
18. [api-go/utils/mailer.go.md](api-go/utils/mailer.go.md) — envoyer un vrai email via SMTP (Brevo)  *(⏱ ~10 min)*
19. [api-go/db/rappelsRepository.go.md](api-go/db/rappelsRepository.go.md) — trouver les adhésions à relancer, éviter les doublons  *(⏱ ~5 min)*
20. [api-go/utils/scheduler.go.md](api-go/utils/scheduler.go.md) — **le plus important à maîtriser** : qu'est-ce qu'une goroutine, comment le "robot" tourne en tâche de fond  *(⏱ ~10 min)*
21. [api-go/app/rappels.go.md](api-go/app/rappels.go.md) — les routes back-office pour piloter les rappels (liste à renouveler, relance manuelle, historique, déclenchement du job)  *(⏱ ~5 min)*
22. [api-go/models/campagne.go.md](api-go/models/campagne.go.md) — la forme d'une campagne ciblée (critères optionnels)  *(⏱ ~5 min)*
23. [api-go/db/campagnesRepository.go.md](api-go/db/campagnesRepository.go.md) — comment on construit une requête SQL avec des critères optionnels, **sans risque d'injection SQL**  *(⏱ ~10 min)*
24. [api-go/app/campagnes.go.md](api-go/app/campagnes.go.md) — créer/prévisualiser/déclencher une campagne segmentée (ville, pays, statut d'adhésion, ancienneté)  *(⏱ ~10 min)*

**Stocks & code-barre (Phase 3) :**
25. [api-go/models/emplacement.go.md](api-go/models/emplacement.go.md) — où se trouve un produit physiquement  *(⏱ ~5 min)*
26. [api-go/db/emplacementsRepository.go.md](api-go/db/emplacementsRepository.go.md) — CRUD simple, sert de brique pour les produits  *(⏱ ~5 min)*
27. [api-go/app/emplacements.go.md](api-go/app/emplacements.go.md) — les 3 handlers emplacements  *(⏱ ~5 min)*
28. [api-go/models/produit.go.md](api-go/models/produit.go.md) — un produit rapporté au siège (code-barre, poids, statut)  *(⏱ ~5 min)*
29. [api-go/db/produitsRepository.go.md](api-go/db/produitsRepository.go.md) — **la recherche rapide par code-barre** (exigence du sujet), le rôle de l'index SQL  *(⏱ ~5 min)*
30. [api-go/app/produits.go.md](api-go/app/produits.go.md) — créer/rechercher/consulter/déplacer un produit, une route à double usage (recherche exacte vs liste filtrée)  *(⏱ ~10 min)*

**Collectes (Phase 4) :**
31. [api-go/models/collecte.go.md](api-go/models/collecte.go.md) — commerçant OU particulier, jamais aucun des deux  *(⏱ ~5 min)*
32. [api-go/db/collectesRepository.go.md](api-go/db/collectesRepository.go.md) — remplissage automatique de `date_realisee` selon le statut  *(⏱ ~5 min)*
33. [api-go/app/collectes.go.md](api-go/app/collectes.go.md) — CRUD collectes + rattachement de produits scannés pendant la collecte (`POST /collectes/{id}/produits`)  *(⏱ ~10 min)*

**Bénévoles (Phase 6, module le plus riche du projet) :**
34. [api-go/models/benevole.go.md](api-go/models/benevole.go.md) — bénévole, compétences, documents (les "conditions à valider")  *(⏱ ~5 min)*
35. [api-go/db/benevolesRepository.go.md](api-go/db/benevolesRepository.go.md) — **`TousLesDocumentsSontValides`**, la fonction clé qui vérifie les conditions avant affectation  *(⏱ ~5 min)*
36. [api-go/app/benevoles.go.md](api-go/app/benevoles.go.md) — candidature publique (première route sans authentification), validation conditionnée, documents, compétences, première route `DELETE`  *(⏱ ~10 min)*

**Services & planning (Phase 7) :**
37. [api-go/models/service.go.md](api-go/models/service.go.md) — services, créneaux, inscriptions, lignes de planning  *(⏱ ~5 min)*
38. [api-go/db/servicesRepository.go.md](api-go/db/servicesRepository.go.md) — la requête à **3 tables jointes** du planning, le contrôle de capacité  *(⏱ ~5 min)*
39. [api-go/utils/planning.go.md](api-go/utils/planning.go.md) — **la génération CSV** (pourquoi pas de `.xlsx`), le BOM UTF-8, le format de date Go  *(⏱ ~5 min)*
40. [api-go/utils/schedulerPlanning.go.md](api-go/utils/schedulerPlanning.go.md) — le regroupement par bénévole avec une `map`  *(⏱ ~5 min)*
41. [api-go/app/services.go.md](api-go/app/services.go.md) — les 11 handlers, **les deux règles d'affectation** (validé + compétent)  *(⏱ ~5 min)*

**Tournées & PDF (Phase 5) :**
42. [api-go/models/tournee.go.md](api-go/models/tournee.go.md) — bénéficiaires, tournées, étapes, livraisons  *(⏱ ~5 min)*
43. [api-go/db/tourneesRepository.go.md](api-go/db/tourneesRepository.go.md) — 4 tables jointes, le double effet sur le stock  *(⏱ ~5 min)*
44. [api-go/utils/pdf.go.md](api-go/utils/pdf.go.md) — **comment est fait un fichier PDF**, écrit sans aucune librairie  *(⏱ ~5 min)*
45. [api-go/app/tournees.go.md](api-go/app/tournees.go.md) — les 12 handlers, les 5 opérations de la clôture de livraison  *(⏱ ~10 min)*

**Consolidation de l'API (Phase 10) — à lire une fois tout le reste compris :**
46. [api-go/utils/erreurs.go.md](api-go/utils/erreurs.go.md) — **le plus important de cette phase** : pourquoi une erreur 500 muette est un problème, quand répondre 400 plutôt que 500, et le piège `%w` contre `%v`  *(⏱ ~15 min)*
47. [api-go/NO-MORE-WASTE.postman_collection.json.md](api-go/NO-MORE-WASTE.postman_collection.json.md) — la documentation des 63 endpoints, et pourquoi l'ordre des dossiers suit les dépendances  *(⏱ ~10 min)*
48. [tests/tester-tous-les-endpoints.py.md](tests/tester-tous-les-endpoints.py.md) — le script qui rejoue les 66 requêtes, et les vrais bugs qu'il a trouvés  *(⏱ ~10 min)*

> À relire aussi après la Phase 10, car leur contenu a changé : [nginx/conf.d/nmw.conf.md](nginx/conf.d/nmw.conf.md) (le réglage `proxy_intercept_errors` a été **inversé** pour l'API) et [api-go/models/utilisateur.go.md](api-go/models/utilisateur.go.md) (le champ `password` est devenu `mot_de_passe`).

**Espace client et gestion des comptes (Phase 9bis) — les deux fichiers les plus sensibles côté sécurité :**
48b. [api-go/app/monEspace.go.md](api-go/app/monEspace.go.md) — **à lire attentivement** : comment un client voit *ses* données sans jamais pouvoir désigner celles d'un autre (faille de **référence directe non sécurisée**), et le défaut de modélisation qu'un test rejoué a révélé  *(⏱ ~10 min)*
48c. [api-go/app/utilisateurs.go.md](api-go/app/utilisateurs.go.md) — la création de comptes avec choix du rôle, pourquoi `admin_back` seul, et le **problème du tout premier compte**  *(⏱ ~10 min)*

---

## Le front FlightPHP (Phases 8 et 9)

Deuxième moitié du projet. À lire une fois l'API comprise, car le front ne fait que la consommer.

> 📎 **Convention** : quelques `.md` en couvrent plusieurs fichiers, quand les expliquer séparément n'apporterait rien. `BenevolesController.php.md` couvre le contrôleur **et ses deux vues** ; `layout_back.php.md` couvre **les deux gabarits et leurs blocs** ; `CONTROLEURS-SIMPLES.md` couvre les contrôleurs qui ne font que désigner une vue.

**Le socle — dans cet ordre :**
49. [front-php/public/index.php.md](front-php/public/index.php.md) — **à lire en premier** : le point d'entrée unique, la réécriture d'URL, et **le schéma complet du chemin d'une requête** (navigateur → nginx → PHP → API → base)  *(⏱ ~10 min)*
50. [front-php/app/config/config.php.md](front-php/app/config/config.php.md) — les réglages centralisés  *(⏱ ~5 min)*
51. [front-php/app/services/ApiClient.php.md](front-php/app/services/ApiClient.php.md) — **le seul fichier qui parle à l'API** : cURL, le piège `localhost` vs `api-go`, le jeton sans `Bearer`  *(⏱ ~10 min)*
52. [front-php/app/Vue.php.md](front-php/app/Vue.php.md) — le rendu des pages, la temporisation de sortie, et **la protection contre les failles XSS**  *(⏱ ~15 min)*
53. [front-php/app/views/layout.php.md](front-php/app/views/layout_back.php.md) — le gabarit commun, les messages à usage unique  *(⏱ ~10 min)*

**Sécurité et multilingue (les deux exigences 🟥 du sujet) :**
54. [front-php/app/middleware/Auth.php.md](front-php/app/middleware/Auth.php.md) — **le plus important du front** : sessions, fixation de session, 401 contre 403, et pourquoi le `exit` après une redirection est obligatoire  *(⏱ ~15 min)*
55. [front-php/app/middleware/Langue.php.md](front-php/app/middleware/Langue.php.md) — le site en 4 langues, l'ordre de priorité, le double filet de sécurité  *(⏱ ~10 min)*
56. [front-php/app/locales/LISEZ-MOI.md](front-php/app/locales/LISEZ-MOI.md) — les fichiers de traduction (⚠️ **régénérés depuis la base**, à ne pas modifier à la main), et pourquoi `SIRET` devient *Partita IVA* en italien  *(⏱ ~10 min)*

**La séparation back-office / front-office (Phase 9) :**
57. [front-php/app/routes/front_routes.php.md](front-php/app/routes/front_routes.php.md) — les routes publiques, GET contre POST pour un formulaire  *(⏱ ~5 min)*
58. [front-php/app/routes/back_routes.php.md](front-php/app/routes/back_routes.php.md) — les routes internes, la convention du préfixe `/back`  *(⏱ ~5 min)*

**Les écrans :**
59. [front-php/app/controllers/front/AuthController.php.md](front-php/app/controllers/front/AuthController.php.md) — la connexion, et **pourquoi on redemande le rôle à l'API au lieu de décoder le JWT**  *(⏱ ~10 min)*
60. [front-php/app/views/front/connexion.php.md](front-php/app/views/front/connexion.php.md) — la page de connexion multilingue (item 1.3)  *(⏱ ~5 min)*
61. [front-php/app/controllers/back/CommercantsController.php.md](front-php/app/controllers/back/CommercantsController.php.md) — **le modèle de tout écran de back-office** (item 2.4) : garde → appel API → filtre → vue  *(⏱ ~10 min)*
62. [front-php/app/views/back/commercants.php.md](front-php/app/views/back/commercants.php.md) — le tableau et son filtre  *(⏱ ~5 min)*
63. [front-php/app/controllers/CONTROLEURS-SIMPLES.md](front-php/app/controllers/CONTROLEURS-SIMPLES.md) — les contrôleurs et vues sans logique particulière  *(⏱ ~5 min)*
64. [front-php/public/assets/BOOTSTRAP.md](front-php/public/assets/BOOTSTRAP.md) — **Bootstrap** : pourquoi il est stocké en local et pas chargé depuis internet, le piège des polices d'icônes, les classes utilisées  *(⏱ ~10 min)*

> **Si tu ne dois en lire que trois** avant une session de live coding sur le front : le **49** (comment une requête traverse tout le système), le **54** (la sécurité des sessions) et le **59** (le point le plus questionnable : pourquoi ne pas décoder le JWT côté PHP).

## Vérifier que tout marche

```bash
docker compose up -d
python tests/tester-tous-les-endpoints.py
```

Doit afficher `66 requetes | 66 OK | 0 en echec`.

Un second script vérifie l'espace client et **l'isolation des données** (un bénévole ne doit pas voir l'espace d'un commerçant, un adhérent ne doit pas pouvoir se promouvoir administrateur) :

```bash
python tests/tester-espace-client.py
```

Doit afficher `17 verifications | 17 OK`. Il monte lui-même son contexte (comptes + fiches rattachées) et est rejouable.

Le premier script **vide les données métier avant de commencer**, il est donc rejouable autant de fois que tu veux (les tables `langues` et `competences` sont préservées). Pour le lancer sans rien effacer : `--garder-donnees` — mais attends-toi alors à des 409 « existe déjà », qui sont normaux. Détails dans son [`.md`](tests/tester-tous-les-endpoints.py.md).

**Le portage des maquettes (vague 1 : le socle) :**
57. [front-php/app/Vue.php.md](front-php/app/Vue.php.md) — **à relire** : le choix du gabarit par le dossier de la vue, `$options`, et le correctif de sécurité `EXTR_SKIP`  *(⏱ ~15 min)*
58. [front-php/app/views/layout_back.php.md](front-php/app/views/layout_back.php.md) — les **deux gabarits**, pourquoi pas un seul, et le piège `min-width:0` de flexbox  *(⏱ ~10 min)*
59. [front-php/app/config/menu_back.php.md](front-php/app/config/menu_back.php.md) — la barre latérale décrite une fois, la table `parents`, et pourquoi un libellé en dur casserait le multilingue sur 22 écrans  *(⏱ ~10 min)*

**Le portage (vague 2 : les modules du sujet) :**
60. [front-php/app/controllers/back/BenevolesController.php.md](front-php/app/controllers/back/BenevolesController.php.md) — **l'écran le plus important du back-office** : la validation conditionnée rendue visible avant le clic, une seule route POST pour cinq boutons  *(⏱ ~10 min)*

## Autres questions

- **Un résumé par phase** (le besoin métier, ce qui a été codé, la logique clé, comment tester) : `Documents/documentation-par-phase/` — niveau de lecture intermédiaire entre la todo et les `.md` détaillés, utile pour réviser une phase entière d'un coup avant une session de live coding.
- **Le journal de bord chronologique** (le fil du raisonnement, date par date : ce qui a été codé, pourquoi, les pièges rencontrés et comment ils ont été résolus) : `Documents/JOURNAL-DE-BORD.md` — à lire si un `.md` de fichier ne suffit pas à comprendre le "pourquoi" derrière une décision.
- **Le plan d'architecture complet** (modèle de données, choix techniques, phases) : `Documents/plan-architecture/plan-architecture-mission1.md`
- **La todo détaillée avec l'avancement réel coché** : `Documents/TODO-Mission1-NoMoreWaste.md`
- **Le sujet officiel du rattrapage** : `Documents/Rattrapages 2i 2025-2026.docx-1.pdf`
- **Une question sur un fichier précis** : ouvrir son `.md` jumeau (même nom, `.md` en plus) dans le même dossier.
