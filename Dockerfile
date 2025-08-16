# Basis: PHP 8.3 mit FPM
FROM php:8.3-fpm

# Systempakete + Nginx + Build-Tools
RUN apt-get update && apt-get install -y \
    git unzip zip curl libpng-dev libonig-dev libxml2-dev libzip-dev \
    mariadb-client nginx ca-certificates \
  && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip opcache \
  && rm -rf /var/lib/apt/lists/*

# Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | sh - \
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
RUN /bin/sh -lc 'cat > /etc/nginx/nginx.conf << "EOF"
user  www-data;
worker_processes auto;
pid /run/nginx.pid;
events { worker_connections 1024; }
http {
  include       /etc/nginx/mime.types;
  default_type  application/octet-stream;
  sendfile      on;
  tcp_nopush    on;
  tcp_nodelay   on;
  keepalive_timeout  65;
  types_hash_max_size 4096;
  server_tokens off;
  gzip on;
  include /etc/nginx/sites-enabled/*;
}
EOF'
RUN /bin/sh -lc 'cat > /etc/nginx/sites-enabled/default << "EOF"
server {
    listen 80 default_server;
    server_name _;
    root /var/www/public;

    index index.php index.html;
    client_max_body_size 25m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_intercept_errors on;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    location ~* \.(?:ico|gif|jpe?g|png|svg|webp|css|js|map|woff2?)$ {
        expires 7d;
        access_log off;
    }
}
EOF'

# Expose für Coolify (interner Port)
EXPOSE 80

# Start: Nginx im Vordergrund + php-fpm + Laravel Warmup
CMD /bin/sh -lc '\
  php -v && nginx -t && php-fpm -v && \
  echo "\nWaiting for DB (if configured)..." ; \
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
