#!/bin/sh
set -e

echo "[start.sh] Running migrations..."
php artisan migrate --force

if [ "$RUN_SEEDS" = "true" ]; then
  echo "[start.sh] RUN_SEEDS=true — Running seeders..."
  php artisan db:seed --force
fi

echo "[start.sh] Starting web server..."
exec /usr/bin/supervisord
