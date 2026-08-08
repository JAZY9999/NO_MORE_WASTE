# Image PHP-FPM (PHP tourne en arrière-plan, nginx lui transmettra les requêtes .php)
FROM php:8.3-fpm-alpine

# Extensions PHP nécessaires : pdo_pgsql pour parler à Postgres si besoin plus tard,
# zip pour Composer, mbstring/xml souvent requis par les libs PHP courantes.
RUN apk add --no-cache postgresql-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip

# Composer (gestionnaire de dépendances PHP) copié depuis son image officielle
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

# On copie d'abord composer.json/lock pour profiter du cache Docker :
# tant que les dépendances ne changent pas, "composer install" ne se relance pas.
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist || true

# Puis on copie le reste du code de l'application.
COPY . .

EXPOSE 9000
CMD ["php-fpm"]
