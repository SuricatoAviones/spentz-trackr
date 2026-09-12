# 09 — Despliegue en Dokploy (VPS + Docker)

Guía para publicar Spentz Trackr en un VPS gestionado con [Dokploy](https://dokploy.com) (PaaS self-hosted sobre Docker + Traefik). Dokploy se encarga de: build del Dockerfile, reverse proxy con dominio propio, SSL automático (Let's Encrypt), redes internas y volúmenes persistentes.

## Requisitos del VPS

| Requisito | Mínimo recomendado | Notas |
|---|---|---|
| VPS | 2 vCPU / 2 GB RAM (+ 2 GB swap) | La app es ligera, pero el *build* compila extensiones PHP y assets: sin swap, 2 GB se quedan cortos y el build muere con `exit code: 137` |
| Docker | 24+ | Lo instala Dokploy con `curl -sSL https://dokploy.com/install.sh \| sh` |
| Dominio | 1 (A/AAAA → IP del VPS) | Para el panel de Dokploy y para la app |
| Puerto 80/443 | Abiertos | Traefik hace TLS automático |

## 1. Preparar el repo

Añade un `.dockerignore` en la raíz (nunca subir `vendor`, `node_modules` ni `.env`):

```gitignore
.git
.env
.env.*
!.env.example
node_modules
vendor
public/build
public/storage
storage/*.key
storage/logs/*
storage/framework/cache/data/*
storage/framework/sessions/*
storage/framework/views/*
tests
.docker
```

Y un `Dockerfile` multi-etapa (dependencias PHP + types Wayfinder + assets frontend + imagen runtime). Usa la imagen `serversideup/php` (PHP 8.5 + FPM + Nginx + cron ya incluido):

```dockerfile
# --- Etapa 0: base PHP + extensiones (compartida por las etapas con PHP) ---
FROM serversideup/php:8.5-fpm-nginx AS php-base

USER root
# install-php-extensions compila gd e intl desde fuente con `make -j$(nproc)`. En un VPS
# pequeño (2 GB, con Dokploy + Traefik + MySQL encima) los gcc en paralelo agotan la RAM y
# el kernel mata el build ("Killed", exit 137). IPE_PROCESSOR_COUNT=1 lo compila en serie.
# Además, al vivir en su propia etapa base, este paso corre ANTES del `npm run build`
# (la etapa frontend depende de wayfinder, que depende de esta) en vez de competir con él.
RUN IPE_PROCESSOR_COUNT=1 install-php-extensions bcmath gd intl
USER www-data

# --- Etapa 1: dependencias PHP ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

# --- Etapa 2: types TypeScript de Wayfinder (la etapa frontend no tiene PHP) ---
FROM php-base AS wayfinder
WORKDIR /var/www/html

USER root
COPY --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data . .
USER www-data

RUN php artisan wayfinder:generate --with-form

# --- Etapa 3: assets frontend (Vite/Inertia/React) ---
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=wayfinder /var/www/html/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /var/www/html/resources/js/routes ./resources/js/routes
COPY --from=wayfinder /var/www/html/resources/js/wayfinder ./resources/js/wayfinder
ENV SKIP_WAYFINDER=1
RUN npm run build

# --- Etapa 4: runtime ---
FROM php-base
WORKDIR /var/www/html

USER root
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY --chown=www-data:www-data . .
# APP_KEY persistente + migraciones al arrancar
COPY --chmod=755 ./docker/entrypoint.d/ /etc/entrypoint.d/
USER www-data

RUN php artisan package:discover --ansi && php artisan storage:link

EXPOSE 80
```

> **¿Por qué una etapa `php-base` y `IPE_PROCESSOR_COUNT=1`?** `serversideup/php` solo trae
> `opcache pcntl pdo_mysql pdo_pgsql redis zip`, así que `bcmath`, `gd` e `intl` hay que
> compilarlas con `install-php-extensions`. Por defecto compila con `make -j$(nproc)` y, en un
> VPS de 2 GB con Dokploy + Traefik + MySQL corriendo, esos gcc en paralelo agotan la RAM: el
> kernel mata el proceso y el build termina en `Killed` / `exit code: 137`.
> `IPE_PROCESSOR_COUNT=1` compila en serie, y poner las extensiones en una etapa base de la
> que dependen las demás hace que ese paso corra **antes** del `npm run build` en vez de
> pelearse con Vite por la memoria. Si el build sigue muriendo por OOM, añade swap al VPS
> (`fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile`,
> más la línea en `/etc/fstab`) o sube el plan a 4 GB.

> **¿Por qué la etapa `wayfinder`?** El plugin `@laravel/vite-plugin-wayfinder` ejecuta `php artisan wayfinder:generate --with-form` en cada build de Vite, pero `node:22-alpine` no incluye PHP. Por eso se generan los types TS en una etapa con PHP (la misma imagen del runtime), se copian a la etapa frontend y, en `vite.config.ts`, el plugin mapea su opción `command` a un no-op (`node -e 0 --`) cuando existe la variable `SKIP_WAYFINDER=1` (solo seteada en el build). En local (`npm run dev`) sigue generando los types automáticamente.

> `--no-scripts` en `composer install` evita que el autoloader ejecute `artisan` en la etapa `vendor` (donde aún no se copió la app); el `package:discover` se ejecuta explícitamente luego en la etapa runtime.

> Si `serversideup/php:8.5-fpm-nginx` no estuviera publicado aún, usa `8.4-fpm-nginx` (la app requiere 8.4.1+).

## 2. Crear la aplicación en Dokploy

1. **Projects → Nuevo proyecto** (ej. `spent-trackr`).
2. **Nueva aplicación → Git** → conecta el repo (GitHub/GitLab/Bitbucket) y elige la rama `main`.
3. **Build**: método **Dockerfile** (ruta `Dockerfile`). Dokploy hace `docker build` y el deploy automático en cada push.
4. **Dominio**: añade el dominio/subdominio (ej. `spent.tudominio.com`) → Dokploy crea el certificado SSL automáticamente (Let's Encrypt) y lo enruta por Traefik. No hace falta exponer puertos.

## 3. Base de datos MySQL

1. En el mismo proyecto: **Nuevo recurso → Database → MySQL** (8.x). Dokploy la levanta con volumen persistente propio.
2. Crea la BD y un usuario con privilegios (botón en el detalle de la base, o entra al terminal del contenedor y usa el cliente `mysql`).
3. La app se conecta por la **red interna de Docker**: `DB_HOST` = nombre del servicio de la BD (lo verás en el detalle del recurso; normalmente `mysql` o `mysql-<id>`), puerto `3306`.

## 4. Variables de entorno

En el detalle de la app → **Environment**:

```env
APP_NAME=Spentz Trackr
APP_ENV=production
APP_DEBUG=false
APP_URL=https://spent.tudominio.com
# APP_KEY: NO hace falta. Se genera y persiste solo en storage/app.key
# (dentro del volumen de storage). Solo fíjala aquí si quieres controlarla tú.

APP_LOCALE=es
APP_TIMEZONE=America/Caracas

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=spenttrackr
DB_USERNAME=spenttrackr
DB_PASSWORD=********

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=public

ADMIN_NAME=Administrador
ADMIN_EMAIL=admin@tudominio.com
ADMIN_PASSWORD=CambiaEstaClave123
```

> **`APP_KEY` se resuelve sola.** El entrypoint del contenedor (`98-spentz-key.sh`) la
> genera en `storage/app.key` en el primer arranque; como `storage/` es un volumen
> persistente, la misma clave se reutiliza en cada redeploy y las sesiones / tokens CSRF
> siguen válidos. Si prefieres fijarla, ponla en `APP_KEY` y ganará.
>
> **`QUEUE_CONNECTION=sync`**: el único job de la app (`SyncExchangeRates`, cada 5 min) se ejecuta inline cuando el scheduler lo dispara; no necesitas worker. Si más adelante añades jobs pesados, cambia a `database` y agrega un servicio worker (`php artisan queue:work --tries=3`).

`DB_*` las lee Laravel directamente. `ADMIN_*` las lee `php artisan admin:create`, que ejecutas **una vez** desde el Terminal tras el primer deploy (ver sección 6).

## 5. Volúmenes persistentes

Monta `/var/www/html/storage` completo como volumen persistente: conserva la `APP_KEY` (`storage/app.key`), los comprobantes (`storage/app/public`), los logs y las sesiones/colas entre despliegues; sin él, cada redeploy generaría una clave nueva e invalidaría todas las sesiones.

- En **Persistent Storage** de la app agrega un volumen con ruta de contenedor:
  `/var/www/html/storage`

El `php artisan storage:link` ya se ejecutó en el build, así que `public/storage` apunta al volumen.

## 6. Arranque del contenedor

La app **no tiene instalador**: se configura solo con las variables de entorno de la
sección 4. Dos scripts de entrypoint hacen el resto en **cada** arranque:

1. **`98-spentz-key.sh`** — si no pasaste `APP_KEY`, la genera y la persiste en
   `storage/app.key` (volumen), y la materializa en un `.env` mínimo para que php-fpm y
   `artisan` la vean. Si defines `APP_KEY` en el entorno, gana la tuya y el script no hace
   nada.
2. **`99-spentz-migrate.sh`** — ejecuta `php artisan migrate --force` (idempotente: no hace
   nada si el esquema está al día) y luego `php artisan optimize`. Si la base de datos aún
   no responde reintenta hasta 30 veces con 5 s de espera (`MIGRATE_DB_RETRIES` /
   `MIGRATE_DB_RETRY_DELAY`). Si falla definitivamente, el contenedor no arranca.

### Crear el administrador (una sola vez, a mano)

`admin:create` **no se ejecuta solo en ningún deploy, ni siquiera en el primero**: es el único
paso manual de la puesta en marcha. Tras el primer deploy, desde el **Terminal** de la app en
Dokploy:

```bash
php artisan admin:create
```

Lee `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` del entorno e imprime las credenciales en
consola (si `ADMIN_PASSWORD` está vacío genera una aleatoria de 16 caracteres: anótala). Crea
el admin con sus categorías y orígenes por defecto.

**Por qué no está en el entrypoint:** el comando es un *upsert* por email
(`firstOrNew` + `forceFill`), no un "crear si no existe": reescribe la contraseña con
`ADMIN_PASSWORD` **en cada ejecución**. Si corriera en cada arranque, todo redeploy revertiría
la contraseña que el admin hubiera cambiado desde la app. Por eso: una vez y nunca más.

Higiene recomendada tras el primer login: cambia la contraseña desde el perfil y **borra
`ADMIN_PASSWORD`** del Environment de Dokploy.

### Datos por defecto (categorías y orígenes)

No requieren ningún comando. Las 8 categorías (Alimentación, Transporte, Servicios, Salud,
Ocio, Ropa, Educación, Otros) y los 8 orígenes de pago (Efectivo, Zelle, PayPal, Binance, Pago
Móvil, BDV, Banesco, Mercantil) se crean **por usuario, al crearse la cuenta**, en
`app/Actions/Users/AssignDefaultUserDataAction.php`. Los dos caminos de creación la invocan:
el registro normal (`Fortify\CreateNewUser`) y `admin:create`. La acción es idempotente —
solo crea el set que falte — así que volver a correr `admin:create` nunca duplica nada.

Las preferencias de usuario (moneda por defecto, idioma, comisión mínima y %, presupuesto,
tipo de tracking) son columnas de `users` con default en migración: tampoco hay que seedear
nada. La tasa de cambio se llena sola (cron cada 5 min + `ensureFreshRate()` en el render).

> **Instalaciones anteriores a esta versión.** El admin creado antes de que `admin:create`
> asignara los defaults se quedó sin categorías ni orígenes. Se reparan una sola vez con los
> seeders de backfill, que solo tocan usuarios que no tengan **ninguno**:
>
> ```bash
> php artisan db:seed --class=DefaultCategoriesSeeder --force
> php artisan db:seed --class=DefaultPaymentSourcesSeeder --force
> ```
>
> `--force` es obligatorio porque en `APP_ENV=production` `db:seed` pide confirmación
> interactiva. ⚠️ **Nunca corras `php artisan db:seed` sin `--class`**: el `DatabaseSeeder`
> crea un usuario de prueba `test@example.com` por factory.

### En despliegues posteriores

Nada manual. Las migraciones corren solas en cada arranque y los datos por defecto ya existen,
así que un redeploy —incluso con cambios de BD— no necesita ningún comando.

## 7. Scheduler (tasa cada 5 min)

La imagen `serversideup/php` ya incluye cron ejecutando `php artisan schedule:run` cada minuto, y en `bootstrap/app.php` está registrado `SyncExchangeRates` con `everyFiveMinutes()` → la tasa BCV/paralela se actualiza sola cada 5 minutos.

Verifica con:

```bash
php artisan schedule:list
# */5 * * * * App\Jobs\SyncExchangeRates
```

Si usaras otra imagen sin cron, agrega un servicio en un `docker-compose.yml` de Dokploy:

```yaml
scheduler:
  image: <tu-imagen>
  command: ["php", "/var/www/html/artisan", "schedule:work"]
```

## 8. Actualizaciones

Cada push a la rama hace build + redeploy (o usa **Deploy** manual). El volumen de `storage` y la BD (recurso Database) se conservan, y el entrypoint aplica las migraciones pendientes en cada arranque, así que un deploy con cambios de BD no necesita comandos manuales.

## 9. Verificación final

- [ ] `https://spent.tudominio.com` carga con SSL.
- [ ] Los logs del contenedor muestran las migraciones aplicadas sin errores y puedes iniciar sesión.
- [ ] Crear gasto en Bs → el formulario muestra tasa BCV/paralela real.
- [ ] `php artisan schedule:list` muestra el job cada 5 min; el log `storage/logs/laravel.log` no acumula errores de dolarapi.com.
- [ ] Subir un comprobante → aparece en `storage/app/public/receipts` y sobrevive a un redeploy.
- [ ] `/admin` accesible con la cuenta creada con `admin:create`.
- [ ] Panel admin → **Sistema** muestra `database_ok: true` y storage escribible.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| API dolarapi.com inaccesible desde el VPS | Fallback: tasa manual por usuario + último valor persistido |
| Volumen de storage borrado en redeploy | Persistir `storage/app/public` (paso 5); nunca depender del filesystem efímero |
| Migraciones olvidadas tras un deploy | `99-spentz-migrate.sh` las aplica en cada arranque; si fallan, el contenedor no levanta (visible en logs) |
| `.env` con credenciales en el repo | Dokploy inyecta variables por entorno; `.dockerignore` excluye `.env` |
| Worker inexistente con `QUEUE_CONNECTION=database` | Usar `sync` (recomendado hoy) o servicio worker aparte |
| Backups | El panel admin genera backup JSON (`/admin/system`); adicionalmente, snapshot periódico de la BD en Dokploy |