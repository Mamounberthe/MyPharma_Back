#!/bin/sh
set -e

echo "[start.sh] Writing environment variables to .env..."

# Utiliser printf pour éviter l'interpolation des caractères spéciaux dans APP_KEY et DB_PASSWORD
{
  printf 'APP_NAME=MyPharma\n'
  printf 'APP_ENV=%s\n'          "${APP_ENV:-production}"
  printf 'APP_KEY=%s\n'          "${APP_KEY}"
  printf 'APP_DEBUG=%s\n'        "${APP_DEBUG:-false}"
  printf 'APP_URL=%s\n'          "${APP_URL:-http://localhost}"
  printf 'APP_FRONTEND_URL=%s\n' "${APP_FRONTEND_URL}"
  printf '\n'
  printf 'LOG_CHANNEL=%s\n'      "${LOG_CHANNEL:-stderr}"
  printf 'LOG_LEVEL=%s\n'        "${LOG_LEVEL:-error}"
  printf '\n'
  printf 'DB_CONNECTION=%s\n'    "${DB_CONNECTION:-pgsql}"
  printf 'DB_HOST=%s\n'          "${DB_HOST}"
  printf 'DB_PORT=%s\n'          "${DB_PORT:-5432}"
  printf 'DB_DATABASE=%s\n'      "${DB_DATABASE}"
  printf 'DB_USERNAME=%s\n'      "${DB_USERNAME}"
  printf 'DB_PASSWORD=%s\n'      "${DB_PASSWORD}"
  printf 'DB_SSLMODE=require\n'
  printf '\n'
  printf 'CACHE_STORE=%s\n'      "${CACHE_STORE:-file}"
  printf 'CACHE_DRIVER=%s\n'     "${CACHE_DRIVER:-file}"
  printf 'SESSION_DRIVER=%s\n'   "${SESSION_DRIVER:-file}"
  printf 'SESSION_LIFETIME=120\n'
  printf 'SESSION_DOMAIN=%s\n'   "${SESSION_DOMAIN}"
  printf '\n'
  printf 'QUEUE_CONNECTION=%s\n' "${QUEUE_CONNECTION:-sync}"
  printf 'FILESYSTEM_DISK=%s\n'  "${FILESYSTEM_DISK:-public}"
  printf '\n'
  printf 'SANCTUM_STATEFUL_DOMAINS=%s\n' "${SANCTUM_STATEFUL_DOMAINS}"
} > /var/www/html/.env

echo "[start.sh] Running migrations..."
php artisan config:clear
php artisan cache:clear
php artisan migrate --force

if [ "$RUN_SEEDS" = "true" ]; then
  echo "[start.sh] RUN_SEEDS=true — Running seeders..."
  php artisan db:seed --force
fi

echo "[start.sh] Starting web server..."
exec /usr/bin/supervisord
