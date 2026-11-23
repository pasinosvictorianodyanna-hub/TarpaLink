#!/bin/sh
# Runs once per container start. Everything here must be idempotent: the same
# image is started as a web process and as a queue worker, restarted on every
# deploy, and must never depend on being the first run.
set -e

cd /var/www/html

echo "[entrypoint] starting as: ${1:-web}"

# Platforms that route to a port of their own choosing (Render sets PORT, and
# only *usually* detects a bound port) need nginx to listen where the router is
# looking. Rewriting the one directive keeps the image identical everywhere:
# PORT unset means port 80, which is what compose publishes.
if [ -n "${PORT:-}" ] && [ "${PORT}" != "80" ]; then
    echo "[entrypoint] nginx will listen on port ${PORT}"
    sed -i "s/listen 80;/listen ${PORT};/" /etc/nginx/http.d/default.conf
fi

# Composer ran with --no-scripts during the build (artisan cannot boot without
# an environment), so package discovery is done here instead. Without it the
# Filament and Livewire providers are never registered.
php artisan package:discover --ansi

# Filament publishes its CSS, JS and fonts into public/ during install. Those
# paths are gitignored, so an image built from source has to regenerate them.
php artisan filament:upgrade >/dev/null 2>&1 || echo "[entrypoint] filament:upgrade skipped"

# Proof files are served from public/storage. Idempotent; fails harmlessly if
# the link already exists.
php artisan storage:link >/dev/null 2>&1 || true

# Only the web process migrates. A worker doing it too would race on the same
# schema during a rolling restart.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[entrypoint] running migrations"
    php artisan migrate --force
fi

# Platforms with no shell and no one-off jobs (Render's free tier has neither)
# cannot be seeded by hand, so the seed rides on the boot. Safe on every start:
# ProductionSeeder is updateOrCreate throughout and changes nothing on a second
# run. Off by default, because a compose deploy seeds deliberately.
if [ "${RUN_SEEDER:-false}" = "true" ]; then
    echo "[entrypoint] seeding reference data"
    php artisan db:seed --class=ProductionSeeder --force
fi

# Hosts with no scheduler (Render's free plan has none) still have to bill.
# `billing:issue` asks "does this period have an invoice?" rather than "has it
# been a month" (#83), so running it on every boot issues each period exactly
# once however often the container starts — and a free instance boots on the
# first request after every spin-down, which is more often than a nightly cron.
# Off by default: a host with a real scheduler should not bill twice for it.
#
# The limit is worth knowing: it bills the period containing *today*. A service
# that receives no traffic for a whole calendar month never boots, and that
# month's invoice is never issued — see docs/DEPLOY-RENDER.md.
if [ "${RUN_BILLING:-false}" = "true" ]; then
    echo "[entrypoint] issuing subscription invoices"
    php artisan billing:issue
fi

# Caches are built at start rather than at build time because config:cache bakes
# in environment variables, and the image is built without any.
if [ "${CACHE_CONFIG:-true}" = "true" ]; then
    echo "[entrypoint] caching config, routes and views"
    php artisan optimize
fi

# artisan ran as root; php-fpm runs as www-data and must be able to write logs
# and sessions.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

case "${1:-web}" in
    worker)
        shift
        echo "[entrypoint] queue worker up"
        exec php artisan queue:work --sleep=1 --tries=3 --max-time=3600 --verbose "$@"
        ;;
    scheduler)
        shift
        echo "[entrypoint] scheduler up"
        # schedule:work keeps a long-lived loop and fires whatever is due. It
        # does not need a lock of its own: billing:issue is idempotent, so a
        # second copy running for a few seconds issues nothing.
        exec php artisan schedule:work "$@"
        ;;
    migrate)
        exec php artisan migrate --force
        ;;
    *)
        exec supervisord -c /etc/supervisord.conf
        ;;
esac
