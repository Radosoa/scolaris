#!/bin/sh
set -e

# Apache écoute sur le port imposé par l'hébergeur
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

# Certificat de la base MySQL hébergée (Aiven…) fourni en texte dans DB_SSL_CA_PEM
if [ -n "${DB_SSL_CA_PEM}" ]; then
    printf '%s\n' "${DB_SSL_CA_PEM}" > /var/www/html/storage/mysql-ca.pem
    export MYSQL_ATTR_SSL_CA=/var/www/html/storage/mysql-ca.pem
fi

# Migrations automatiques au démarrage (désactivables avec RUN_MIGRATIONS=false)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    if [ "${ECOLES_MULTI:-false}" = "true" ]; then
        # Base centrale puis base de chaque école
        php artisan ecole:migrer
        # Premier démarrage : école de démonstration complète (ECOLE_DEMO_CODE=demo par exemple).
        # Lancée en arrière-plan : le remplissage prend quelques minutes, l'API démarre sans attendre.
        if [ -n "${ECOLE_DEMO_CODE}" ]; then
            (php artisan ecole:creer "${ECOLE_DEMO_CODE}" "${ECOLE_DEMO_NOM:-Collège de démonstration}" --demo --si-absente \
                && chown -R www-data:www-data storage) >&2 &
        fi
    else
        php artisan migrate --force
    fi
fi

# Fichiers créés par root ci-dessus (base SQLite, PDF…) rendus à Apache
chown -R www-data:www-data storage bootstrap/cache

php artisan storage:link 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
