#!/bin/sh
set -eu

APP_DIR="${APP_BASE_DIR:-/var/www/html}"

if [ ! -f "$APP_DIR/artisan" ]; then
  echo "❌ Artisan no encontrado en $APP_DIR"
  exit 1
fi

if [ -f "$APP_DIR/storage/installed" ]; then
  exit 0
fi

echo "👉 Spentz Trackr no está instalado: ejecutando instalación headless..."

max_attempts="${INSTALL_DB_RETRIES:-30}"
retry_delay="${INSTALL_DB_RETRY_DELAY:-5}"
attempt=1

while [ "$attempt" -le "$max_attempts" ]; do
  if php "$APP_DIR/artisan" app:install --no-interaction; then
    echo "✅ Instalación completada. Optimizando..."
    php "$APP_DIR/artisan" optimize --no-interaction

    exit 0
  fi

  echo "⚠️  Intento $attempt/$max_attempts falló (¿base de datos aún no lista?). Reintentando en ${retry_delay}s..."
  attempt=$((attempt + 1))
  sleep "$retry_delay"
done

echo "❌ Instalación headless falló tras $max_attempts intentos."
exit 1