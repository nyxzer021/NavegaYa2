#!/bin/sh
set -eu

php artisan config:cache
php artisan route:cache

exec php artisan queue:work --tries=3 --max-time=3600 --sleep=1 --no-interaction
