# nginx/conf.d/nmw.conf

> ⏱️ **Lecture : ~10 min** · 762 mots, 22 lignes de code

## Rôle

C'est le fichier de configuration principal de nginx, le serveur qui reçoit toutes les requêtes HTTP en premier (point d'entrée unique du projet, exposé sur le port 8080 de la machine). Il décide où envoyer chaque requête : vers l'API Go, vers le front PHP, ou vers une page d'erreur.

## Comment ça marche, bloc par bloc

### error_page

```
error_page 404 /errors/404.html;
error_page 500 502 503 504 /errors/500.html;
```

Dit à nginx : "si une réponse a le code 404 (ou 500/502/503/504), affiche plutôt le contenu de ce fichier HTML, en gardant le code d'erreur d'origine dans la réponse au client."

### location /errors/

Rend ce dossier accessible uniquement en interne (`internal`), pour que personne ne puisse taper directement `/errors/404.html` dans son navigateur — ces fichiers ne doivent être servis que via le mécanisme `error_page`.

### location /api/

Toute URL commençant par `/api/` est transmise (proxifiée) au conteneur `api-go`, sur son port 8080 interne.

**`proxy_intercept_errors off;` — et c'est volontaire.** Cette ligne était à `on` jusqu'à la Phase 10 ; elle a été inversée après un test qui a révélé le problème. Voir la section « Le deuxième piège » plus bas : c'est le point le plus intéressant de ce fichier.

### location / (réécriture d'URL)

```
try_files $uri $uri/ /index.php?$query_string;
```

Nginx essaie dans l'ordre : le fichier demandé existe-t-il tel quel (`$uri`) ? un dossier de ce nom existe-t-il (`$uri/`) ? Sinon, on redirige tout vers `index.php`, en gardant les paramètres de la requête d'origine (`$query_string`). C'est ce qui permet à FlightPHP de gérer des URLs "propres" comme `/commercants/12` sans que ce fichier existe physiquement sur le disque — c'est exactement la "réécriture d'URL" demandée par le sujet.

### location ~ \.php$

Chaque fois qu'une URL se termine par `.php` (ou après la réécriture ci-dessus), la requête est transmise en FastCGI au conteneur `front-php` (PHP-FPM), qui exécute le code PHP et retourne une réponse. `fastcgi_intercept_errors on;` a le même rôle que `proxy_intercept_errors` plus haut, mais pour PHP-FPM.

## Premier piège (Phase 0) : les pages d'erreur ne se déclenchaient pas

Sans `proxy_intercept_errors on;` et `fastcgi_intercept_errors on;`, les pages d'erreur personnalisées (`error_page 404 ...`) ne se déclenchent QUE pour les erreurs générées par nginx lui-même (ex: un fichier statique manquant). Si l'erreur 404 est renvoyée par un service derrière nginx (l'API Go via `http.Error(w, ..., 404)`, ou FlightPHP qui ne trouve pas de route), nginx affichait par défaut la page d'erreur brute du backend (message PHP-FPM brut, ou juste le texte simple envoyé par Go) au lieu de notre page stylisée. On l'a découvert en testant une route inexistante et en voyant un message d'erreur PHP brut au lieu de notre 404.html.

On a donc activé les deux options. Et c'est là qu'un second problème est apparu, bien plus tard.

## Deuxième piège (Phase 10) : un site n'est pas une API

En testant l'API après la consolidation, on obtenait ceci :

```bash
curl -X POST http://localhost:8080/api/produits/ -d '{"emplacement_id":99999}'
```

```html
<!DOCTYPE html>
<html lang="fr">
    <h1>500</h1>
    <p>Une erreur est survenue côté serveur…</p>
```

**Une page HTML complète, en réponse à un appel d'API.** Le message précis calculé par le code Go (`« Erreur de creation du produit : un des elements references n'existe pas »`) avait été **jeté et remplacé** par nginx.

C'est logique : `proxy_intercept_errors on;` dit à nginx « quand le backend renvoie une erreur, remplace sa réponse par ma page ». Ce qui est exactement ce qu'on veut pour un **site web**… et exactement ce qu'on ne veut pas pour une **API**.

La distinction à retenir :

| | Client | Ce qu'il attend en cas d'erreur |
|---|---|---|
| **Le site** (`/`) | un humain avec un navigateur | une jolie page qui explique |
| **L'API** (`/api/`) | un programme (le front PHP, Postman, une appli mobile) | un message court et exploitable |

D'où la configuration actuelle :

- `location /api/` → `proxy_intercept_errors **off**` : l'API garde ses propres messages.
- `location ~ \.php$` → `fastcgi_intercept_errors **on**` : le site garde ses pages personnalisées.

L'exigence du sujet (« pages d'erreur personnalisées ») reste donc parfaitement remplie — elle s'applique au site, qui est ce que le sujet désigne.

**Vérification :**

```bash
curl http://localhost:8080/api/commercants/999 -H "Authorization: $TOKEN"
# -> Commercant introuvable          (message de l'API, code 404)

curl http://localhost:8080/page-qui-nexiste-pas
# -> <h1>404</h1> ...                (page HTML personnalisée)
```

## ⚠️ Piège pratique : modifier ce fichier ne suffit pas

Ce fichier n'est **pas monté en volume** : il est copié dans l'image au moment du build (voir `nginx/Dockerfile`, ligne `COPY conf.d/ /etc/nginx/conf.d/`).

Conséquence : après avoir modifié `nmw.conf`, faire

```bash
docker compose restart nginx     # ❌ ne change RIEN
```

ne sert à rien — le conteneur redémarre avec **l'ancienne** configuration, celle qui est dans l'image. Il faut reconstruire :

```bash
docker compose up -d --build nginx     # ✅
```

C'est une perte de temps classique : on croit que le changement ne marche pas, alors qu'il n'a simplement jamais été appliqué. Ça a été rencontré pendant la Phase 10, en corrigeant précisément le `proxy_intercept_errors` ci-dessus.
