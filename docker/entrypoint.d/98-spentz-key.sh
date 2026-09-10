#!/bin/sh
set -eu

# Persist APP_KEY in the storage volume so it survives container recreation,
# independent of any .env file. If the operator provides APP_KEY via the
# environment it always wins and this script is a no-op.

APP_DIR="${APP_BASE_DIR:-/var/www/html}"
KEY_FILE="$APP_DIR/storage/app.key"

if [ -n "${APP_KEY:-}" ]; then
  exit 0
fi

if [ ! -f "$KEY_FILE" ]; then
  printf 'base64:%s\n' "$(head -c 32 /dev/urandom | base64)" > "$KEY_FILE"
  echo "🔑 APP_KEY generado y persistido en storage/app.key"
fi

KEY="$(cat "$KEY_FILE")"

# Materialise a minimal .env so php-fpm and `php artisan` see the key even
# though the image ships without one.
if [ ! -f "$APP_DIR/.env" ]; then
  printf 'APP_KEY=%s\n' "$KEY" > "$APP_DIR/.env"
elif ! grep -q '^APP_KEY=' "$APP_DIR/.env"; then
  printf 'APP_KEY=%s\n' "$KEY" >> "$APP_DIR/.env"
fi
