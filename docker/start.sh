#!/bin/sh
# AGAPAY cloud copy: prepare, then run the scheduler (daily backups) next to Apache.
set -e
cd /var/www/html

# Apache's default listen port would clash with the host's ${PORT}.
sed -i 's/^Listen 80$//' /etc/apache2/ports.conf
# Exactly one Apache process model (prefork, which mod_php needs); some hosts otherwise fail with
# "AH00534: More than one MPM loaded".
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod mpm_prefork >/dev/null

# A persistent volume mounted on storage/ starts empty: recreate the folders Laravel needs.
mkdir -p storage/app/private storage/app/backups storage/app/backup-temp storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

# The database service may still be starting (e.g. a fresh deploy): retry for up to 2 minutes.
tries=0
until php artisan migrate --force; do
    tries=$((tries + 1))
    if [ "$tries" -ge 40 ]; then echo "AGAPAY: the database is not reachable; check the DB_* settings." >&2; exit 1; fi
    echo "AGAPAY: waiting for the database ($tries)..."
    sleep 3
done
# First start of a demo copy (AGAPAY_SAMPLE_DATA=true): load the sample records and the test accounts once.
# A real copy instead creates its Administrator once from the host's shell: php artisan agapay:install
if [ "${AGAPAY_SAMPLE_DATA:-false}" = "true" ] && ! php artisan agapay:install --status >/dev/null 2>&1; then
    php artisan agapay:install --sample-data
fi
# Demo copies made before farm locations existed: give the sample farmers one, for the photo GPS check.
if [ "${AGAPAY_SAMPLE_DATA:-false}" = "true" ]; then
    php artisan agapay:demo-farm-locations --force
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache

# The scheduler runs as www-data so backup files belong to the web server.
su -s /bin/sh www-data -c "php artisan schedule:work" &

exec apache2-foreground
