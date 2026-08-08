# Phases 8 & 9 — Le front FlightPHP : socle, multilingue, back-office

> ⏱️ **Lecture : ~10 min** · 1513 mots, 2 lignes de code

> **Légende** : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour un point du sujet · 🟦 = bonus, absent du sujet (assumé, pas du hors-sujet).
>
> Ce document couvre le **socle du front** et couvre en une fois plusieurs points de la todo : 1.3 (page de connexion multilingue), 2.4 (vue back-office), Phase 8 (multilingue) et Phase 9 (séparation back/front-office).

## Le besoin

Jusqu'ici, tout le projet était une API : utilisable avec Postman, invisible pour un humain. Le sujet demande **une application web** avec *« à la fois un back-office (utilisé par NO MORE WASTE) et un front office (utilisé par les clients) »*, et précise que *« le site devra être multilingue »* puisque l'association s'est implantée à l'étranger.

C'est ce que cette phase met en place.

## Ce qui a été construit

### Le socle (🟧)

| Fichier | Rôle |
|---|---|
| `public/index.php` | point d'entrée unique de tout le site |
| `app/config/config.php` | réglages centralisés |
| `app/services/ApiClient.php` | **le seul fichier qui parle à l'API Go** |
| `app/Vue.php` | moteur de rendu + protection XSS |
| `app/views/layout.php` | gabarit commun (en-tête, menu, messages) |
| `public/assets/bootstrap/` + `icons/` | **Bootstrap 5.3 + Bootstrap Icons**, stockés en local |

### Le multilingue — Phase 8 (🟥)

- `app/middleware/Langue.php` + 4 fichiers de traduction (`fr`, `en`, `it`, `pt`), **40 clés chacun**.
- Choix de la langue par priorité : URL (`?lang=it`) → session → navigateur (`Accept-Language`) → français.
- Sélecteur de langue dans l'en-tête, sur **toutes** les pages (8.2 🟧).
- Le back-office **et** le front-office sont traduits, y compris les en-têtes de colonnes des tableaux (8.3 🟧).

### La séparation back/front — Phase 9 (🟥)

- Deux fichiers de routes distincts : `front_routes.php` et `back_routes.php`.
- Toutes les adresses internes sous le préfixe `/back`.
- `Auth::exigerStaff()` appelé **en première ligne** de chaque contrôleur de back-office.
- Deux thèmes de barre de navigation (vert `bg-success` public / sombre `bg-dark` interne) pour voir instantanément où l'on est.

### Les écrans (1.3 et 2.4 🟦)

- **Page de connexion multilingue** (item 1.3) : formulaire POST, email conservé après erreur, mot de passe jamais réaffiché.
- **Liste des commerçants avec filtre** (item 2.4) : tableau + menu déroulant qui s'envoie tout seul.
- Accueil public et tableau de bord du back-office (6 modules, 5 grisés car pas encore développés).

## Les points à savoir justifier à l'oral

### 1. Pourquoi le front ne parle jamais à la base de données

Le front n'a **aucun** accès à PostgreSQL. Il passe systématiquement par l'API. Trois raisons : les règles métier sont écrites une seule fois (sinon elles divergeraient), le sujet impose une API en Go (la contourner la rendrait décorative), et d'autres clients pourront réutiliser les mêmes règles plus tard.

### 2. `api-go` et non `localhost`

Dans le navigateur, l'API est à `localhost:8080/api`. Dans le code PHP, elle est à **`http://api-go:8080`**. Le code s'exécute dans le conteneur, pour qui `localhost` désigne lui-même. Docker fournit le nom du service comme adresse réseau.

### 3. Pourquoi demander le rôle à l'API plutôt que décoder le JWT

Le rôle est écrit dans le jeton, on pourrait le lire directement. Mais **un JWT n'est pas chiffré** : c'est du base64 que n'importe qui peut lire et fabriquer. Ce qui le rend fiable est sa **signature**, vérifiable seulement avec la clé secrète — qui vit dans l'API. Décoder sans vérifier reviendrait à croire un badge sans regarder s'il est authentique.

> Nuance importante : même en trompant le front, on n'obtient rien de plus — l'API revérifie le rôle à chaque requête. **Le front affiche ou masque ; la vraie barrière est côté API.**

### 4. Masquer un lien n'est pas sécuriser

Le lien « Back-office » n'apparaît pas dans le menu d'un adhérent. C'est du **confort**. Ce qui le bloque réellement, c'est `Auth::exigerStaff()` côté serveur. Il faut les deux, et ne jamais compter sur le premier seul.

### 5. Multilingue ≠ traduction mot à mot

Le champ `SIRET` devient *Partita IVA* en italien et *NIF* en portugais : c'est une notion **française** qui n'existe pas telle quelle ailleurs. Le multilingue est une adaptation locale, pas un simple remplacement de mots.

Ce qui est traduit : **l'interface**. Ce qui ne l'est pas : le **contenu métier** (noms, adresses, descriptions saisies). Traduire le contenu imposerait une table de traductions et une saisie en 4 langues pour chaque enregistrement — hors de proportion avec le sujet.

## Les choix techniques et leur raison

| Choix | Alternative écartée | Pourquoi |
|---|---|---|
| **cURL natif** | Guzzle (retiré du projet) | 130 lignes explicables entièrement, une dépendance de moins |
| **PHP natif dans les vues** | Twig, Blade | PHP sait déjà mélanger HTML et données |
| **Bootstrap (+ Bootstrap Icons)** | CSS écrit à la main | standard du marché : rendu cohérent, responsive et accessible sans réinventer l'existant |
| **Bootstrap stocké en local** | chargement depuis un CDN | le site garde son style **sans connexion internet** (train, wifi de l'école le jour de la soutenance) |
| **Garde appelée dans chaque contrôleur** | protection automatique sur `/back` | la protection **se voit** en lisant le code (même logique que `utils.RequireRole` en Go) |

Ligne directrice à savoir formuler : **le front utilise Bootstrap ; l'API, elle, reste en Go sans framework**, parce que c'est ce que le cours impose et que c'est là que se trouve la vraie logique du projet. Autrement dit, on ne réinvente pas la mise en forme, mais on maîtrise le métier.

> **Changement du 2026-08-02** : le projet a d'abord eu un `style.css` de 250 lignes écrit à la main. La consigne « le front doit être fait uniquement avec Bootstrap, icônes comprises » a conduit à le supprimer entièrement et à refaire les 5 vues en classes Bootstrap.

## Deux pièges rencontrés

**1. `http_response_code(404)` ne fonctionne pas dans Flight.** Le navigateur recevait un **200 avec une page vide**. Flight construit sa propre réponse et l'envoie avec son statut (200 par défaut), écrasant le nôtre. Il faut passer par son objet réponse :

```php
Flight::response()->status(404)->send();
```

**2. Le `exit` après une redirection est obligatoire.** `header('Location: ...')` ajoute seulement une consigne : PHP **continue** d'exécuter le script et génère la page réservée en entier. Le navigateur ne l'affiche pas, mais `curl` la lit sans difficulté. C'est une fuite de données réelle.

## Ce qui a été vérifié (2026-08-01)

| Test | Résultat |
|---|---|
| Les 4 langues sur `/connexion` | Connexion / Sign in / Accedi / Entrar ✅ |
| Détection via `Accept-Language: it` | italien ✅ |
| Persistance du choix en session | ✅ |
| Cohérence des 4 fichiers de langue | 40 clés, aucun manque ✅ |
| `/back` sans connexion | 302 → `/connexion` ✅ |
| Connexion staff | 302 → `/back` ✅ |
| Connexion adhérent | 302 → `/` ✅ |
| Adhérent tentant `/back` | bloqué + message traduit ✅ |
| Lien back-office pour un adhérent | masqué ✅ |
| Liste des commerçants + filtre par ville | ✅ |
| Route inconnue | 404 + page personnalisée ✅ |

### Vérifié après le passage à Bootstrap (2026-08-02)

| Test | Résultat |
|---|---|
| Les 4 fichiers Bootstrap servis | 200, bon type MIME (dont `font/woff2`) ✅ |
| Barre verte en front-office / sombre en back-office | ✅ |
| Tableau de bord : 5 cartes grisées, 1 active | ✅ |
| Tableau des commerçants + filtre | ✅ |
| Les 4 langues après refonte | Connexion / Sign in / Accedi / Entrar ✅ |
| Plus aucune référence à l'ancien `style.css` | ✅ |
| API (non-régression) | 66/66 ✅ |

## Ce qu'il reste à faire

**Front-office** — les deux pages annoncées dans le menu ne sont pas encore écrites. Elles consomment des routes d'API **déjà prêtes et publiques** :
- catalogue des services (`GET /services/`, `GET /services/{id}/creneaux`)
- candidature bénévole (`POST /benevoles/candidature/`)
- inscription à un créneau pour les adhérents connectés

**Back-office** — cinq modules restent grisés : bénévoles (validation des documents, le plus riche), collectes, stocks (recherche par code-barre), tournées (téléchargement du PDF), services.

Le squelette est en place : chaque nouvel écran suit exactement la structure de `CommercantsController` (garde → appel API → normalisation → vue).

**Point hérité** : il n'existe toujours pas d'endpoint pour créer un compte staff (on passe par une requête SQL). À traiter avec l'écran « utilisateurs » du back-office.

## Pour aller plus loin (fichiers `.md` détaillés)

- [front-php/public/index.php.md](../../Code/front-php/public/index.php.md) — **à lire en premier** : le point d'entrée unique, le chemin complet d'une requête
- [front-php/app/services/ApiClient.php.md](../../Code/front-php/app/services/ApiClient.php.md) — le pont vers l'API, le piège `localhost`
- [front-php/app/middleware/Auth.php.md](../../Code/front-php/app/middleware/Auth.php.md) — sessions, fixation de session, 401 vs 403
- [front-php/app/middleware/Langue.php.md](../../Code/front-php/app/middleware/Langue.php.md) — le multilingue en détail
- [front-php/app/Vue.php.md](../../Code/front-php/app/Vue.php.md) — le rendu et la protection XSS
- [front-php/app/routes/back_routes.php.md](../../Code/front-php/app/routes/back_routes.php.md) — la séparation des deux espaces
