#!/usr/bin/env sh
set -eu

php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RUN_SCHEDULER:-1}" != "0" ]; then
  nohup php artisan schedule:work --no-interaction &
fi

# Modo clásico: escucha en $PORT, sin worker. HTTPS lo termina el proxy de Railway.
export SERVER_NAME=":${PORT:-8080}"
exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
