# docker-compose.yml

> ⏱️ **Lecture : ~5 min** · 467 mots, 3 lignes de code

## Rôle

Décrit tous les conteneurs (services) du projet, comment ils sont construits, comment ils communiquent entre eux, et quels ports/volumes ils utilisent. C'est le fichier qu'on lance avec `docker compose up` pour démarrer tout le projet d'un coup.

## Les 4 services

- **postgres** : la base de données. `healthcheck` vérifie régulièrement qu'elle est prête à accepter des connexions (`pg_isready`), ce qui permet aux autres services d'attendre qu'elle soit vraiment prête avant de démarrer (`depends_on: condition: service_healthy`).
- **api-go** : l'API backend en Go. Elle attend que postgres soit "healthy" avant de démarrer.
- **front-php** : le site FlightPHP.
- **nginx** : le seul service qui expose un port sur la machine (`${NGINX_PORT}:80`) — tous les autres communiquent uniquement entre eux via le réseau Docker interne, jamais directement accessibles depuis l'extérieur.

## Le piège du volume `vendor/` (à bien comprendre pour le live coding)

Le service `front-php` monte deux volumes :
```
- ./front-php:/var/www/app
- front_vendor:/var/www/app/vendor
```

Le premier (`./front-php:/var/www/app`) est un **bind mount** : il fait apparaître le dossier local `front-php/` du projet directement dans le conteneur, ce qui permet de modifier le code PHP sans reconstruire l'image à chaque fois (pratique en développement).

Problème : au moment du build de l'image (`Dockerfile`), `composer install` génère un dossier `vendor/` avec toutes les dépendances PHP (FlightPHP, Guzzle...). Mais dès que le conteneur démarre avec le bind mount ci-dessus, le contenu du dossier local `front-php/` (qui n'a PAS de `vendor/`, puisque ce dossier n'est jamais commité) **écrase** ce qui a été généré dans l'image au build. Résultat : le conteneur démarre sans `vendor/autoload.php`, et PHP plante avec une erreur `Failed to open stream` dès qu'on essaie de charger une page.

La solution : ajouter un **second volume**, nommé (`front_vendor`), monté spécifiquement sur `/var/www/app/vendor`. Docker copie automatiquement le contenu de ce dossier (tel qu'il existe dans l'image au premier démarrage) dans ce volume nommé, qui persiste ensuite indépendamment du bind mount. Comme ce volume est monté APRÈS le bind mount plus général, il "regagne" la priorité sur ce sous-dossier précis — le code source vient du disque local, mais `vendor/` reste celui généré par le build.

`nginx` monte les deux mêmes volumes en lecture seule (`:ro`), car c'est lui qui sert les fichiers statiques et qui doit voir exactement la même arborescence que `front-php` (qui exécute le PHP).

## Piège annexe : pourquoi ça a marché "tout seul" au build initial

La toute première fois qu'on a lancé le projet (avant d'ajouter `front_vendor`), on n'avait pas encore testé de vraie page PHP qui déclenche une erreur volontaire — les tests précédents (`/`) fonctionnaient par coïncidence parce que la page de test ne fait qu'un `echo`, sans passer par une vraie logique métier qui aurait révélé le problème plus tôt. C'est en testant une route 404 qu'on est tombé sur l'erreur `vendor/autoload.php` manquant.
