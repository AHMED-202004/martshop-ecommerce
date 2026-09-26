#!/usr/bin/env bash
set -e

php artisan key:generate --force
php artisan storage:link || true
chmod -R 775 storage bootstrap/cache || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force || true
fi

apache2-foreground
