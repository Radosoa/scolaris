#!/bin/sh
set -e

# Apache écoute sur le port imposé par l'hébergeur
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

# Migrations automatiques au démarrage (désactivables avec RUN_MIGRATIONS=false)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

php artisan storage:link 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
