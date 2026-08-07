#!/usr/bin/env bash
set -euo pipefail

ROLE="${ROLE:-app}"
APP_DIR="${APP_DIR:-/var/www/html}"
cd "$APP_DIR"

wait_for_db() {
    local host="${DB_HOST:-mysql}"
    local port="${DB_PORT:-3306}"
    local retries="${DB_WAIT_RETRIES:-60}"
    local i=0

    if [ "${DB_CONNECTION:-}" != "mysql" ] && [ "${DB_CONNECTION:-}" != "mariadb" ]; then
        echo "[entrypoint] DB_CONNECTION=${DB_CONNECTION:-unset} — skipping MySQL wait"
        return 0
    fi

    echo "[entrypoint] Waiting for database at ${host}:${port}..."
    while [ "$i" -lt "$retries" ]; do
        if php -r "
            \$host = getenv('DB_HOST') ?: 'mysql';
            \$port = getenv('DB_PORT') ?: '3306';
            \$db   = getenv('DB_DATABASE') ?: '';
            \$user = getenv('DB_USERNAME') ?: '';
            \$pass = getenv('DB_PASSWORD') ?: '';
            try {
                new PDO(
                    sprintf('mysql:host=%s;port=%s;dbname=%s', \$host, \$port, \$db),
                    \$user,
                    \$pass,
                    [PDO::ATTR_TIMEOUT => 3]
                );
                exit(0);
            } catch (Throwable \$e) {
                exit(1);
            }
        "; then
            echo "[entrypoint] Database is ready"
            return 0
        fi
        i=$((i + 1))
        sleep 2
    done

    echo "[entrypoint] ERROR: database not reachable after ${retries} attempts" >&2
    exit 1
}

run_as_app_user() {
    if [ "$(id -u)" -eq 0 ]; then
        gosu www-data "$@"
    else
        "$@"
    fi
}

fix_storage_permissions() {
    if [ "$(id -u)" -eq 0 ]; then
        chown -R www-data:www-data \
            "$APP_DIR/storage" \
            "$APP_DIR/bootstrap/cache" \
            2>/dev/null || true
        chmod -R ug+rwx \
            "$APP_DIR/storage" \
            "$APP_DIR/bootstrap/cache" \
            2>/dev/null || true
    fi
}

fix_storage_permissions
wait_for_db

case "$ROLE" in
    app)
        echo "[entrypoint] ROLE=app — migrate + optimize"
        run_as_app_user php artisan migrate --force --no-interaction
        # DB_SEED defaults to true via compose; seeders are idempotent (updateOrCreate / exists checks).
        if [ "${DB_SEED:-true}" = "true" ] || [ "${DB_SEED:-true}" = "1" ]; then
            echo "[entrypoint] ROLE=app — db:seed"
            run_as_app_user php artisan db:seed --force --no-interaction
        else
            echo "[entrypoint] ROLE=app — skipping db:seed (DB_SEED=${DB_SEED})"
        fi
        run_as_app_user php artisan config:cache --no-interaction
        run_as_app_user php artisan route:cache --no-interaction
        run_as_app_user php artisan view:cache --no-interaction || true
        run_as_app_user php artisan storage:link --no-interaction || true
        ;;
    worker|scheduler)
        echo "[entrypoint] ROLE=${ROLE} — skipping migrate"
        run_as_app_user php artisan config:cache --no-interaction || true
        ;;
    *)
        echo "[entrypoint] ROLE=${ROLE} — skipping migrate/optimize"
        ;;
esac

# php-fpm master typically runs as root and spawns www-data workers.
# Artisan CLI (worker/scheduler) should run as www-data.
if [ "${1:-}" = "php-fpm" ]; then
    exec "$@"
fi

if [ "$(id -u)" -eq 0 ]; then
    exec gosu www-data "$@"
fi

exec "$@"
