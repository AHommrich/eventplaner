# Dockerfile.dev — Laravel + Artisan Serve (für lokale Entwicklung)
FROM php:8.3-cli

# Optional: UID/GID an den Host anpassen, damit es keine Rechteprobleme mit dem Bind-Mount gibt
ARG PUID=1000
ARG PGID=1000

# Systempakete & Build-Deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip curl \
    pkg-config \
    libonig-dev \
    libjpeg62-turbo-dev libpng-dev libfreetype6-dev \
    libzip-dev libxml2-dev \
    mariadb-client \
 && rm -rf /var/lib/apt/lists/*

# PHP-Extensions (GD mit JPEG/FreeType)
RUN docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install -j"$(nproc)" \
    pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Arbeitsverzeichnis (Code kommt per Bind-Mount)
WORKDIR /var/www

# www-data an Host-UID/GID angleichen (vermeidet Schreib-/Besitzprobleme im Mount)
RUN groupmod -o -g ${PGID} www-data \
 && usermod  -o -u ${PUID} -g www-data www-data \
 && mkdir -p /var/www/storage /var/www/bootstrap/cache \
 && chown -R www-data:www-data /var/www

# Dev-Startscript: Composer installieren (falls nötig), .env anlegen, Key generieren,
# Caches leeren, optional migrate, dann Artisan-Server starten
RUN printf '%s\n' \
'#!/bin/sh' \
'set -e' \
'cd /var/www' \
'echo "[start] Laravel Dev Bootstrap…"' \
'# Rechte sicherstellen (Mount kann Besitz verlieren)' \
'chown -R www-data:www-data storage bootstrap/cache || true' \
'mkdir -p storage bootstrap/cache' \
'# Composer installieren, falls vendor fehlt' \
'if [ ! -d vendor ]; then' \
'  echo "[start] Running composer install (dev)";' \
'  composer install --no-interaction --prefer-dist;' \
'fi' \
'# .env aus Beispiel übernehmen, falls nicht vorhanden' \
'if [ ! -f .env ] && [ -f .env.example ]; then' \
'  cp .env.example .env;' \
'fi' \
'# App-Key generieren, falls leer' \
'su -s /bin/sh -c "php artisan key:generate --force || true" www-data' \
'# Caches leeren (damit Compose-ENV sicher greift)' \
'su -s /bin/sh -c "php artisan config:clear || true" www-data' \
'su -s /bin/sh -c "php artisan cache:clear || true"  www-data' \
'su -s /bin/sh -c "php artisan route:clear  || true" www-data' \
'su -s /bin/sh -c "php artisan view:clear   || true" www-data' \
'# (Optional) Migrations – im Dev ok; Fehler ignorieren wenn DB noch nicht ready' \
'su -s /bin/sh -c "php artisan migrate --force || true" www-data' \
'echo "[start] Starting artisan serve on 0.0.0.0:${APP_PORT:-8080}"' \
'exec php artisan serve --host=0.0.0.0 --port="${APP_PORT:-8080}"' \
> /usr/local/bin/dev-start.sh \
 && chmod +x /usr/local/bin/dev-start.sh

EXPOSE 8080
USER www-data
CMD ["/usr/local/bin/dev-start.sh"]
