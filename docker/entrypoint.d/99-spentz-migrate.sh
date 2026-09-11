#!/bin/sh
set -eu

# Run pending migrations on every boot. Migrations are idempotent, so this is a
# no-op once the schema is up to date. The admin user is NOT created here on
# purpose: `admin:create` resets the password from ADMIN_PASSWORD every time it
# runs, so it stays a one-off manual step (see docs/09-despliegue-dokploy.md).

APP_DIR="${APP_BASE_DIR:-/var/www/html}"

if [ ! -f "$APP_DIR/artisan" ]; then
  echo "❌ Artisan no encontrado en $APP_DIR"
  exit 1
fi

max_attempts="${MIGRATE_DB_RETRIES:-30}"
retry_delay="${MIGRATE_DB_RETRY_DELAY:-5}"
attempt=1

while [ "$attempt" -le "$max_attempts" ]; do
  if php "$APP_DIR/artisan" migrate --force --no-interaction; then
    echo "✅ Migraciones al día. Optimizando..."
    php "$APP_DIR/artisan" optimize --no-interaction

    exit 0
  fi

  echo "⚠️  Intento $attempt/$max_attempts falló (¿base de datos aún no lista?). Reintentando en ${retry_delay}s..."
  attempt=$((attempt + 1))
  sleep "$retry_delay"
done

echo "❌ Las migraciones fallaron tras $max_attempts intentos."
exit 1
