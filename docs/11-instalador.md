# 11 — Instalador auto-hospedable

Spentz Trackr es open-source y auto-hospedable. Incluye un instalador estilo WordPress que permite dejarla funcionando sin tocar configuraciones manuales: crea el `.env`, genera la clave, ejecuta las migraciones y crea la cuenta de administrador.

Hay **tres vías de instalación**:

1. **Instalador web** (`/install`): wizard de 4 pasos, pensado para quien hospeda en un servidor tradicional (cPanel, VPS con LAMP, etc.).
2. **CLI** (`php artisan app:install`): instalación por línea de comandos, ideal para VPS y Docker.
3. **Docker Compose**: entorno llave en mano (app + MySQL/PostgreSQL/SQLite).

El instalador soporta **SQLite, MySQL y PostgreSQL**.

---

## 1. Requisitos

| Recurso | Requisito |
|---|---|
| PHP | 8.3+ (recomendado 8.5) |
| Extensiones PHP | `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `curl`, `zip`, `bcmath`, `gd`, `fileinfo` |
| Base de datos | SQLite, MySQL 5.7+/8.x o PostgreSQL 12+ |
| Node (solo build) | 22+ (para compilar assets con Vite; CI y Docker usan 22) |
| Composer | 2+ |
| Git | solo para actualizaciones por `git pull` |

> El instalador web y el CLI verifican los requisitos automáticamente (pantalla de Requisitos del wizard).

---

## 2. Instalador web (`/install`)

Pasos típicos en un servidor tradicional:

```bash
git clone https://github.com/SuricatoAviones/spentz-trackr.git
cd spentz-trackr
composer install --no-dev --no-interaction --optimize-autoloader
npm ci && npm run build
cp .env.example .env        # opcional, el instalador lo crea si falta
```

**No hace falta crear ni editar un `.env`.** Abre la URL de la app: como todavía no existe
`storage/installed`, redirige automáticamente a `/install`. La app arranca sin `.env`
porque genera una `APP_KEY` temporal en `storage/app.key` para que el wizard (sesión y
CSRF) funcione; el paso 4 la persiste en el `.env` definitivo.

1. **Requisitos** — versión de PHP, extensiones y permisos de escritura (incluida la raíz
   de la app, donde se escribirá el `.env`).
2. **Base de datos** — SQLite, MySQL o PostgreSQL (host, puerto, BD, usuario, contraseña).
   SQLite solo pide el nombre del archivo.
3. **Aplicación** — nombre, URL pública, idioma y zona horaria; más los datos del
   **administrador** (nombre, correo, contraseña).
4. **Completado** — se escribe un `.env` listo para producción (`APP_ENV=production`,
   `APP_DEBUG=false`, `APP_KEY`, `DB_*`, `SESSION_SECURE_COOKIE` según la URL), se aplican
   las migraciones y se crea el admin. Se crea `storage/installed` y `/install` queda
   bloqueado. Se hace una copia del `.env` anterior en `.env.backup` si existía.

### Bloqueo post-instalación

El middleware global `EnsureInstalled` redirige a `/install` mientras no exista `storage/installed`. Una vez instalado, `/install` responde **403** (a excepción del endpoint `install/execute`). Para reinstalar, borra `storage/installed`.

> **Importante:** tras instalar, si no ves los cambios, ejecuta `composer run dev` o `npm run build` (según tu flujo) para que Vite sirva los assets.

---

## 3. Instalador por CLI (`php artisan app:install`)

Para servidores sin navegador (VPS, SSH):

```bash
php artisan app:install
```

El comando pregunta interactivamente por la base de datos, la app y el administrador. También acepta **flags** para instalación no interactiva (útil en scripts):

```bash
php artisan app:install \
  --db-connection=mysql \
  --db-host=127.0.0.1 \
  --db-port=3306 \
  --db-database=spenttrackr \
  --db-username=spenttrackr \
  --db-password=******** \
  --app-name="Spentz Trackr" \
  --app-url=https://spent.tudominio.com \
  --app-locale=es \
  --timezone=America/Caracas \
  --admin-name=Administrador \
  --admin-email=admin@tudominio.com \
  --admin-password=CambiaEstaClave123
```

Para SQLite:

```bash
php artisan app:install --db-connection=sqlite --db-database=database.sqlite --admin-email=... --admin-password=...
```

### Fallback a variables de entorno (modo headless)

En instalaciones por contenedor (Docker/Dokploy) no se pasan flags: `app:install` lee del entorno con prioridad **flag CLI → variable de entorno → prompt**. En modo no interactivo (`--no-interaction`) no hay prompts: si falta un valor obligatorio, el comando falla listando el error de validación.

```bash
# Variables usadas (todas opcionales según la conexión):
#   DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
#   APP_NAME, APP_URL, APP_LOCALE, TIMEZONE
#   ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD
php artisan app:install --no-interaction
```

Si ya existe `storage/installed`, el comando aborta a menos que pases `--force`.

---

## 4. Docker Compose

Se incluyen tres composiciones:

| Comando | BD |
|---|---|
| `docker compose up -d --build` | MySQL 8.4 |
| `docker compose -f docker-compose.yml -f docker-compose.pgsql.yml up -d --build` | PostgreSQL 16 |
| `docker compose -f docker-compose.yml -f docker-compose.sqlite.yml up -d --build` | SQLite |

### MySQL (default)

```bash
cp .env.docker .env
# edita .env: DB_PASSWORD, ADMIN_EMAIL, ADMIN_PASSWORD, APP_URL.
# NO hace falta APP_KEY: se genera y persiste en storage/app.key (volumen).
docker compose up -d --build
```

En el **primer arranque** el contenedor se instala solo: el script `docker/entrypoint.d/99-spentz-install.sh` invoca `php artisan app:install --no-interaction` (que lee `DB_*`, `APP_*`/`TIMEZONE` y `ADMIN_*` del entorno), genera la clave, migra, crea el admin y marca `storage/installed`. Reintenta si la BD aún no responde; si falla del todo, el contenedor no arranca.

La app queda en `http://localhost:8080` (o `APP_PORT`). Con `APP_INSTALL_MODE=headless` el instalador web (`/install`) queda bloqueado (404); para usarlo, quita `APP_INSTALL_MODE` o ponlo a `wizard`.

### PostgreSQL

```bash
docker compose -f docker-compose.yml -f docker-compose.pgsql.yml up -d --build
```

### SQLite

```bash
docker compose -f docker-compose.yml -f docker-compose.sqlite.yml up -d --build
```

La instalación headless crea e migra `database/database.sqlite` automáticamente (el volumen del compose persiste el archivo).

> **Volúmenes:** el compose persiste `/var/www/html/storage` completo (comprobantes, logs, sesiones y el marcador `storage/installed`, lo que evita reinstalar en cada recreación) y, en la variante SQLite, `/var/www/html/database` con el archivo de la BD. La BD MySQL/PostgreSQL también es persistente. Todo sobrevive a `down` y `up`.

---

## 5. Actualizaciones

Se eligió el modelo simple por **`git pull`**.

### Desde la CLI

```bash
php artisan app:update
```

Ejecuta en orden: `git pull --ff-only`, `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force` y `php artisan optimize:clear`.

> En **producción** el comando se bloquea; pasa `--force` para permitirlo.

### Manual (same steps)

```bash
git pull --ff-only
composer install --no-dev --no-interaction --prefer-dist
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
```

---

## 6. Notas técnicas

- El **lock file** es `storage/installed` (guarda la fecha ISO de instalación). Todos los middlewares y controles lo consultan.
- **`APP_KEY` sin `.env`**: el middleware global `EnsureInstalled` garantiza una clave en cada request — la lee de `storage/app.key` o la genera y persiste ahí. Como `storage/` es persistente (volumen en Docker, disco en cPanel), la clave sobrevive redeploys y recreaciones de contenedor sin depender de un `.env` escribible. En Docker, el entrypoint `98-spentz-key.sh` la materializa antes de que arranque php-fpm.
- Con **`APP_INSTALL_MODE=headless`** (Docker/Dokploy) el instalador web `/install` responde **404** y el arranque del contenedor llama `app:install --no-interaction` automáticamente si falta `storage/installed` (`docker/entrypoint.d/99-spentz-install.sh`). En cPanel no se setea la variable y se usa el wizard.
- La lógica compartida del instalador vive en `App\Services\Installer`, usada por el controlador web (`InstallController`) y por el comando CLI (`app:install`). Antes de migrar, aplica la config de BD del wizard a `config()` en tiempo de ejecución, así funciona aunque `config/database.php` se haya cargado del entorno viejo.
- El `.env` que escribe es **completo y de producción** (`APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, `DB_*`, `SESSION_SECURE_COOKIE` según el esquema de la URL) y fuerza `SESSION_DRIVER`/`QUEUE_CONNECTION`/`CACHE_STORE=database` para no necesitar Redis/Memcached. Si ya existía un `.env`, lo respalda en `.env.backup`.
- Para reinstalar tras un error, borra `storage/installed` y vuelve a ejecutar el instalador.
- Backups del panel admin (`/admin/system`) exportan la BD a JSON; documentado en `docs/08-panel-admin.md`.
