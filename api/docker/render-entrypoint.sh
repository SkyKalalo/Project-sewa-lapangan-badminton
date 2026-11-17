#!/bin/sh
set -eu

if [ -n "${APP_KEY:-}" ] && [ "${APP_KEY#base64:}" = "$APP_KEY" ]; then
    export APP_KEY="base64:${APP_KEY}"
fi

if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

php artisan config:clear
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link --force
chown -R www-data:www-data storage bootstrap/cache

php artisan schedule:work --no-interaction &
exec apache2-foreground
