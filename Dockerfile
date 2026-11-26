FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
COPY tailwind.config.js postcss.config.js ./
RUN npm run build

# Modo clásico: php_server atiende cada petición desde cero, sin worker de Laravel
# y sin paquetes nuevos de Composer. nginx + php-fpm exigiría un supervisor extra.
FROM dunglas/frankenphp:1-php8.4-bookworm
WORKDIR /var/www/html
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl gnupg git unzip \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client-17 \
    && install-php-extensions pdo_pgsql zip gd mbstring bcmath pcntl dom opcache \
    && printf '%s\n' \
        'upload_max_filesize=8M' \
        'post_max_size=24M' \
        'opcache.enable=1' \
        'opcache.enable_cli=0' \
        'opcache.validate_timestamps=0' \
        'opcache.memory_consumption=128' \
        'opcache.max_accelerated_files=20000' \
        > /usr/local/etc/php/conf.d/navegaya.ini \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && chmod +x deploy/railway-start.sh deploy/railway-queue.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
ENV SERVER_ROOT=/var/www/html/public
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD sh -c 'curl -fsS "http://127.0.0.1:${PORT:-8080}/up" || exit 1'
CMD ["sh", "deploy/railway-start.sh"]
