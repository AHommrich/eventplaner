from php:8.3-fpm

# Production Dockerfile – nginx + php-fpm + Vite build
# Systempakete + Nginx + PHP-Extensions
run apt-get update && apt-get install -y \
    git unzip zip curl libpng-dev libonig-dev libxml2-dev libzip-dev \
    mariadb-client nginx ca-certificates \
 && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip opcache \
 && rm -rf /var/lib/apt/lists/*

# Node.js 20
run curl -fsSL https://deb.nodesource.com/setup_20.x | sh - \
 && apt-get update && apt-get install -y nodejs \
 && rm -rf /var/lib/apt/lists/*

# Composer
copy --from=composer:2.7 /usr/bin/composer /usr/bin/composer

workdir /var/www

# Composer (Layer-Caching)
copy composer.json composer.lock ./
run composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

# App-Code
copy . .

# Frontend-Build (Vite/Inertia)
run npm ci --prefer-offline --no-audit --no-fund && npm run build

# Permissions
run mkdir -p /var/www/storage /var/www/bootstrap/cache \
 && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# OPcache
run printf '%s\n' \
  'opcache.enable=1' \
  'opcache.enable_cli=1' \
  'opcache.validate_timestamps=0' \
  'opcache.max_accelerated_files=20000' \
  'opcache.memory_consumption=192' \
  'opcache.interned_strings_buffer=16' \
  > /usr/local/etc/php/conf.d/opcache.ini

# Nginx config (mit map für HTTPS-Erkennung + Proxy-Header)
run mkdir -p /etc/nginx/sites-enabled /var/log/nginx /var/cache/nginx
run printf '%s\n' \
  'user  www-data;' \
  'worker_processes auto;' \
  'pid /run/nginx.pid;' \
  'events { worker_connections 1024; }' \
  'http {' \
  '  include       /etc/nginx/mime.types;' \
  '  default_type  application/octet-stream;' \
  '  sendfile      on;' \
  '  tcp_nopush    on;' \
  '  tcp_nodelay   on;' \
  '  keepalive_timeout  65;' \
  '  types_hash_max_size 4096;' \
  '  server_tokens off;' \
  '  gzip on;' \
  '  # Map X-Forwarded-Proto -> HTTPS für PHP/Laravel' \
  '  map $http_x_forwarded_proto $fastcgi_https { default off; https on; }' \
  '  include /etc/nginx/sites-enabled/*;' \
  '}' \
  > /etc/nginx/nginx.conf

run printf '%s\n' \
  'server {' \
  '    listen 80 default_server;' \
  '    server_name _;' \
  '    root /var/www/public;' \
  '' \
  '    index index.php index.html;' \
  '    client_max_body_size 25m;' \
  '' \
  '    location / {' \
  '        try_files $uri $uri/ /index.php?$query_string;' \
  '    }' \
  '' \
  '    location ~ \.php$ {' \
  '        include fastcgi_params;' \
  '        fastcgi_intercept_errors on;' \
  '        fastcgi_pass 127.0.0.1:9000;' \
  '        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;' \
  '        fastcgi_param PATH_INFO $fastcgi_path_info;' \
  '        # --- Forward original proxy headers to PHP ---' \
  '        fastcgi_param HTTP_X_FORWARDED_PROTO $http_x_forwarded_proto;' \
  '        fastcgi_param HTTP_X_FORWARDED_HOST  $host;' \
  '        fastcgi_param HTTP_X_FORWARDED_FOR   $proxy_add_x_forwarded_for;' \
  '        # Setze HTTPS je nach X-Forwarded-Proto (wir definieren $fastcgi_https in nginx.conf)' \
  '        fastcgi_param HTTPS $fastcgi_https;' \
  '    }' \
  '' \
  '    location ~* \.(?:ico|gif|jpe?g|png|svg|webp|css|js|map|woff2?)$ {' \
  '        expires 7d;' \
  '        access_log off;' \
  '    }' \
  '}' \
  > /etc/nginx/sites-enabled/default

expose 80

# Start (nur /bin/sh, kein bash)
cmd ["/bin/sh","-lc", "\
  php -v && nginx -t && php-fpm -v && \
  echo 'Waiting for app warmup...' ; \
  (php artisan package:discover --ansi || true) && \
  (php artisan config:clear || true) && \
  (php artisan route:clear || true) && \
  (php artisan view:clear || true) && \
  (php artisan storage:link || true) && \
  (php artisan config:cache || true) && \
  (php artisan route:cache || true) && \
  (php artisan view:cache || true) && \
  (php artisan migrate --force || true) && \
  php-fpm -D && \
  exec nginx -g 'daemon off;' \
"]
