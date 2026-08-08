# On part de l'image nginx officielle, on y copie juste notre config et nos pages d'erreur.
FROM nginx:1.25-alpine

# Supprime la config par défaut de nginx pour éviter tout conflit avec la nôtre
RUN rm -f /etc/nginx/conf.d/default.conf

COPY conf.d/ /etc/nginx/conf.d/
COPY errors/ /usr/share/nginx/html/errors/

EXPOSE 80
