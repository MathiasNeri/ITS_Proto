# Image de déploiement pour ITS_Proto (PHP pur, sans dépendance Composer).
# Fonctionne sur tout hébergeur qui sait lancer un Dockerfile (Render,
# Fly.io, Railway...). Utilise le serveur intégré de PHP avec le routeur
# maison (public/router.php) pour reproduire le comportement Apache
# (ErrorDocument 404, etc.) même sans .htaccess.

FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        pkg-config \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_sqlite

WORKDIR /app
COPY . /app

# La base SQLite doit être réinscriptible par le process PHP.
RUN mkdir -p /app/database && chmod -R 777 /app/database

# Render (et la plupart des PaaS) fournissent le port d'écoute via $PORT.
ENV PORT=8000
EXPOSE 8000

CMD php -S 0.0.0.0:${PORT} -t public public/router.php
