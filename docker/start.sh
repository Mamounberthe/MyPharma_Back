#!/bin/sh
set -e

echo "[start.sh] Writing environment variables to .env..."
cat > /var/www/html/.env << EOF
APP_NAME=MyPharma
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}
APP_FRONTEND_URL=${APP_FRONTEND_URL}

LOG_CHANNEL=${LOG_CHANNEL:-stderr}
LOG_LEVEL=${LOG_LEVEL:-error}

DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}
DB_SSLMODE=require
DB_URL=postgresql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT:-5432}/${DB_DATABASE}?sslmode=require

CACHE_STORE=${CACHE_STORE:-file}
CACHE_DRIVER=${CACHE_DRIVER:-file}
SESSION_DRIVER=${SESSION_DRIVER:-file}
SESSION_LIFETIME=120
SESSION_DOMAIN=${SESSION_DOMAIN}

QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}
FILESYSTEM_DISK=${FILESYSTEM_DISK:-public}

SANCTUM_STATEFUL_DOMAINS=${SANCTUM_STATEFUL_DOMAINS}
EOF

echo "[start.sh] Running migrations..."
php artisan migrate --force

if [ "$RUN_SEEDS" = "true" ]; then
  echo "[start.sh] RUN_SEEDS=true — Running seeders..."
  php artisan db:seed --force
fi

echo "[start.sh] Starting web server..."
exec /usr/bin/supervisord
