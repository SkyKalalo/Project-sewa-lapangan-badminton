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

# Normalize Apache's enabled modules at runtime as well as at image build time.
find /etc/apache2/mods-enabled -maxdepth 1 -type l -name 'mpm_*.load' -delete
find /etc/apache2/mods-enabled -maxdepth 1 -type l -name 'mpm_*.conf' -delete
ln -s ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -s ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
a2enmod rewrite
apache2ctl -M 2>&1 | grep 'mpm_prefork_module'
if apache2ctl -M 2>&1 | grep -Eq 'mpm_(event|worker)_module'; then
    echo 'Apache must load exactly one MPM module.' >&2
    exit 1
fi

php artisan schedule:work --no-interaction &
exec apache2-foreground
