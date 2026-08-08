# `public/index.php` — la porte d'entrée du site

> ⏱️ **Lecture : ~10 min** · 793 mots, 39 lignes de code

> **À lire en premier** parmi les fichiers du front. C'est l'équivalent exact de `app.go` côté API.
> **Phases** : 1.3, 2.4, 8, 9.

## L'idée centrale : une seule porte d'entrée

Sur un site PHP « à l'ancienne », chaque page est un fichier : `contact.php`, `liste.php`, `login.php`… L'adresse dans le navigateur correspond à un fichier réel sur le disque.

Ici, **non**. Toutes les adresses du site — `/`, `/connexion`, `/back/commercants` — passent par **ce seul fichier**. Aucun dossier `back/` n'existe sur le disque.

C'est ce qu'on appelle un **front controller** (contrôleur frontal). C'est nginx qui rend ça possible :

```nginx
try_files $uri $uri/ /index.php?$query_string;
```

Traduction : « le fichier demandé existe ? sers-le. Sinon, envoie tout à `index.php` ». C'est exactement la **réécriture d'URL** demandée par le sujet.

**L'avantage** : les adresses deviennent libres et propres (`/back/commercants` plutôt que `/liste.php?type=commercants`), et il n'existe qu'un seul endroit où passe la sécurité — impossible d'oublier de protéger un fichier oublié dans un coin.

## Les 5 étapes du fichier

### 1. `session_start()`

Démarre la session PHP, qui permet de se souvenir d'un visiteur d'une page à l'autre (son jeton de connexion, sa langue).

⚠️ **Cette ligne doit venir avant tout affichage.** La session repose sur un cookie, un cookie voyage dans les **en-têtes** HTTP, et les en-têtes partent **avant** le contenu de la page. Si le moindre caractère est affiché avant — même un espace après un `?>` dans un fichier inclus — PHP répond `headers already sent` et la session ne marche plus. C'est l'erreur PHP la plus classique qui soit.

### 2. Charger la configuration

```php
$config = require __DIR__ . '/../app/config/config.php';
```

`require` sur un fichier qui fait `return [...]` récupère le tableau retourné. C'est la façon simple de charger de la configuration en PHP, sans librairie.

`__DIR__` est le dossier de *ce* fichier. On l'utilise plutôt qu'un chemin relatif comme `../app/`, parce qu'un chemin relatif dépend du dossier depuis lequel PHP a été lancé — donc il casse dès qu'on change quelque chose.

### 3. Langue et client d'API

```php
Langue::initialiser($config);
$api = new ApiClient($config['api_base_url']);
```

`Langue::initialiser` détermine dans quelle langue afficher le site (voir [Langue.php.md](../app/middleware/Langue.php.md)). `ApiClient` est l'objet qui parlera à l'API Go (voir [ApiClient.php.md](../app/services/ApiClient.php.md)).

On les crée **une seule fois ici**, puis on les passe aux contrôleurs. C'est plus simple à suivre que si chaque contrôleur fabriquait les siens.

### 4. Brancher les routes

```php
require __DIR__ . '/../app/routes/front_routes.php';
require __DIR__ . '/../app/routes/back_routes.php';
```

Deux fichiers séparés, et c'est **volontaire** : le sujet demande « à la fois un back-office (utilisé par NO MORE WASTE) et un front office (utilisé par les clients) ». La séparation est visible dans l'organisation même des fichiers, pas seulement dans le code.

Ces fichiers utilisent les variables `$api` et `$config` définies juste au-dessus. C'est possible parce que `require` exécute le fichier **dans le contexte courant** : il voit les variables déjà déclarées, comme si son contenu était écrit ici.

### 5. La page 404 — et un piège

```php
Flight::map('notFound', function () {
    Flight::response()->status(404)->send();
});
```

`Flight::map('notFound', ...)` remplace le comportement de Flight quand aucune route ne correspond.

⚠️ **Le piège rencontré ici.** La première version utilisait la fonction PHP habituelle :

```php
http_response_code(404);   // ❌ ne marche pas dans Flight
```

Le navigateur recevait quand même un **200 avec une page vide**. La raison : Flight ne se contente pas de laisser PHP répondre, il **construit sa propre réponse** et l'envoie à la fin avec son propre statut (200 par défaut) — ce qui écrase le nôtre. Il faut donc passer par son objet réponse.

Une fois le vrai 404 envoyé, nginx l'intercepte (`fastcgi_intercept_errors on`) et affiche la page personnalisée `404.html` — l'exigence du sujet.

On ne lit **pas** le fichier `404.html` depuis PHP : il vit dans le conteneur **nginx**, pas dans le conteneur PHP. Chaque conteneur a son propre disque.

## Le chemin complet d'une requête

Bon schéma à savoir redessiner en live coding :

```
Navigateur
   |  GET /back/commercants
   v
nginx  (port 8080)
   |  aucun fichier de ce nom -> try_files -> index.php
   v
public/index.php
   |  session -> config -> langue -> routes
   v
back_routes.php  ->  CommercantsController::liste()
   |  Auth::exigerStaff()  (garde de rôle)
   v
ApiClient  ->  http://api-go:8080/commercants/   (jeton JWT joint)
   |
   v
API Go  ->  PostgreSQL
   |
   v  JSON
Vue::afficher('back/commercants')  ->  layout.php  ->  HTML
   |
   v
Navigateur
```

Le point à retenir : **le front ne touche jamais la base de données**. Il ne sait même pas qu'elle existe. Il ne parle qu'à l'API.

## Question probable en live coding

**« Pourquoi le front ne se connecte-t-il pas directement à PostgreSQL ? Ce serait plus rapide. »**

Trois raisons. D'abord, les règles métier (un bénévole doit avoir ses documents validés avant d'être affecté, un produit livré passe en `distribue`…) sont écrites une seule fois dans l'API ; si le front attaquait la base directement, il faudrait les réécrire et elles finiraient par diverger. Ensuite, le sujet impose une API en Go — la contourner la rendrait décorative. Enfin, ça permet à d'autres clients (application mobile, partenaire) d'utiliser les mêmes règles plus tard.

## Fichiers liés

- [../app/config/config.php.md](../app/config/config.php.md) — les réglages chargés à l'étape 2
- [../app/services/ApiClient.php.md](../app/services/ApiClient.php.md) — le pont vers l'API Go
- [../app/middleware/Langue.php.md](../app/middleware/Langue.php.md) — le multilingue
- [../app/routes/front_routes.php.md](../app/routes/front_routes.php.md) — les routes publiques
- [../app/routes/back_routes.php.md](../app/routes/back_routes.php.md) — les routes du back-office
- [../../nginx/conf.d/nmw.conf.md](../../nginx/conf.d/nmw.conf.md) — la réécriture d'URL qui rend tout ça possible
