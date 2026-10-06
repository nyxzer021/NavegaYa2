#!/usr/bin/env bash
set -euo pipefail

# Ejecutar desde la carpeta del proyecto en el servidor, tras crear .env.
php artisan down --render="errors::503" --retry=60
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan navegaya:production-readiness
php artisan queue:restart
php artisan up
