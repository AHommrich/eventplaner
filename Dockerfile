# Basis: PHP 8.3 mit FPM
FROM php:8.3-fpm

# Systempakete + Nginx + Build-Tools
RUN apt-get update && apt-get install -y \
    git unzip zip curl libpng-dev libonig-dev libxml2-dev libzip-dev \
    mariadb-client nginx ca-certificates \
  && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip opcache \
  && rm -rf /var/lib/apt/lists/*

# Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
  && apt-get update && apt-get install -y nodejs \
  && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Arbeitsverzeichnis
WORKDIR /var/www

# Nur Composer Files vorab (Layer-Caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

# Projektcode
COPY . .

# Frontend Build (Vite/Inertia)
RUN npm ci --prefer-offline --no-audit --no-fund && npm run build

# Permissions für Laravel
RUN mkdir -p /var/www/storage /var/www/bootstrap/cache \
  && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# OPcache config (produktionstauglich)
RUN { \
  echo "opcache.enable=1"; \
  echo "opcache.enable_cli=1"; \
  echo "opcache.validate_timestamps=0"; \
  echo "opcache.max_accelerated_files=20000"; \
  echo "opcache.memory_consumption=192"; \
  echo "opcache.interned_strings_buffer=16"; \
} > /usr/local/etc/php/conf.d/opcache.ini

# Nginx Konfiguration (serve /public, fastcgi zu php-fpm)
RUN mkdir -p /var/log/nginx /var/cache/nginx /etc/nginx/sites-enabled
RUN bash -lc 'cat > /etc/nginx/nginx.conf << "EOF"\n\
user  www-data;\n\
worker_processes auto;\n\
pid /run/nginx.pid;\n\
events { worker_connections 1024; }\n\
http {\n\
  include       /etc/nginx/mime.types;\n\
  default_type  application/octet-stream;\n\
  sendfile      on;\n\
  tcp_nopush    on;\n\
  tcp_nodelay   on;\n\
  keepalive_timeout  65;\n\
  types_hash_max_size 4096;\n\
  server_tokens off;\n\
  gzip on;\n\
  include /etc/nginx/sites-enabled/*;\n\
}\n\
EOF'

RUN bash -lc 'cat > /etc/nginx/sites-enabled/default << "EOF"\n\
server {\n\
    listen 80 default_server;\n\
    server_name _;\n\
    root /var/www/public;\n\
\n\
    index index.php index.html;\n\
    client_max_body_size 25m;\n\
\n\
    location / {\n\
        try_files $uri $uri/ /index.php?$query_string;\n\
    }\n\
\n\
    location ~ \\.php$ {\n\
        include fastcgi_params;\n\
        fastcgi_intercept_errors on;\n\
        fastcgi_pass 127.0.0.1:9000;\n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\
        fastcgi_param PATH_INFO $fastcgi_path_info;\n\
    }\n\
\n\
    location ~* \\.(?:ico|gif|jpe?g|png|svg|webp|css|js|map|woff2?)$ {\n\
        expires 7d;\n\
        access_log off;\n\
    }\n\
}\n\
EOF'

# Expose für Coolify (interner Port)
EXPOSE 80

# Start: Nginx im Vordergrund + php-fpm + Laravel Warmup
#  - storage:link (idempotent)
#  - caches bauen (env-aware)
#  - migrations best-effort (scheitert nicht hart)
CMD bash -lc '\
  php -v && nginx -t && php-fpm -v && \
  php -r "echo \"\\nWaiting for DB (if configured)...\\n\";" ; \
  (php artisan config:clear || true) && \
  (php artisan route:clear || true) && \
  (php artisan view:clear || true) && \
  (php artisan storage:link || true) && \
  (php artisan config:cache || true) && \
  (php artisan route:cache || true) && \
  (php artisan view:cache || true) && \
  (php artisan migrate --force || true) && \
  php-fpm -D && \
  exec nginx -g "daemon off;" \
'
