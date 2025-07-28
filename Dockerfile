# Basis-Image: PHP 8.3 mit FPM
FROM php:8.3-fpm

# Systempakete installieren (ohne alte Node-Version)
RUN apt-get update && apt-get install -y \
    git unzip zip curl libpng-dev libonig-dev libxml2-dev libzip-dev \
    mariadb-client nginx \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Node.js 20 installieren (von NodeSource)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Composer installieren
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Arbeitsverzeichnis setzen
WORKDIR /var/www

# Composer-Abhängigkeiten installieren (mit Cache)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Projektcode kopieren
COPY . .

# Composer Autoload & Laravel Key (nur wenn Laravel)
RUN composer run-script post-autoload-dump || true \
    && if [ -f artisan ]; then php artisan key:generate --force || true; fi

# Vue/Inertia Build
RUN npm install && npm run build

# Rechte setzen
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Nginx Konfiguration kopieren
COPY docker/nginx/default.conf /etc/nginx/sites-enabled/default

# Expose Ports (Standard PHP-FPM + Nginx)
EXPOSE 80

# Startbefehl
CMD service nginx start && php-fpm
