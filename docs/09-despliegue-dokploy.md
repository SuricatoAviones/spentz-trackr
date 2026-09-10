# 09 — Despliegue en Dokploy (VPS + Docker)

Guía para publicar Spentz Trackr en un VPS gestionado con [Dokploy](https://dokploy.com) (PaaS self-hosted sobre Docker + Traefik). Dokploy se encarga de: build del Dockerfile, reverse proxy con dominio propio, SSL automático (Let's Encrypt), redes internas y volúmenes persistentes.

## Requisitos del VPS

| Requisito | Mínimo recomendado | Notas |
|---|---|---|
| VPS | 2 vCPU / 2 GB RAM | La app es ligera; Dokploy + Traefik + MySQL consumen lo suyo |
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
# --- Etapa 1: dependencias PHP ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

# --- Etapa 2: types TypeScript de Wayfinder (la etapa frontend no tiene PHP) ---
FROM serversideup/php:8.5-fpm-nginx AS wayfinder
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
FROM serversideup/php:8.5-fpm-nginx
WORKDIR /var/www/html

USER root
RUN install-php-extensions bcmath gd intl
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY --chown=www-data:www-data . .
# Instalación headless al arrancar (solo si no existe storage/installed)
COPY --chmod=755 ./docker/entrypoint.d/ /etc/entrypoint.d/
USER www-data

RUN php artisan package:discover --ansi && php artisan storage:link

EXPOSE 80
```

> **¿Por qué la etapa `wayfinder`?** El plugin `@laravel/vite-plugin-wayfinder` ejecuta `php artisan wayfinder:generate --with-form` en cada build de Vite, pero `node:22-alpine` no incluye PHP. Por eso se generan los types TS en una etapa con PHP (la misma imagen del runtime), se copian a la etapa frontend y, en `vite.config.ts`, el plugin mapea su opción `command` a un no-op (`node -e 0 --`) cuando existe la variable `SKIP_WAYFINDER=1` (solo seteada en el build). En local (`npm run dev`) sigue generando los types automáticamente.

> `--no-scripts` en `composer install` evita que el autoloader ejecute `artisan` en la etapa `vendor` (donde aún no se copió la app); el `package:discover` se ejecuta explícitamente luego en la etapa runtime.

> Si `serversideup/php:8.5-fpm-nginx` no estuviera publicado aún, usa `8.4-fpm-nginx` (la app corre en 8.3+).

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
TIMEZONE=America/Caracas
# 'headless' instala y bloquea /install (Docker). 'wizard' deja el instalador web (solo cPanel).
APP_INSTALL_MODE=headless

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

`ADMIN_*` y `DB_*` alimentan la **instalación headless**: en el primer arranque el contenedor ejecuta automáticamente `php artisan app:install --no-interaction`, que lee estas variables (ver sección 6).

## 5. Volúmenes persistentes

Monta `/var/www/html/storage` completo como volumen persistente: conserva el marcador de instalación (`storage/installed`), los comprobantes (`storage/app/public`), los logs y las sesiones/colas entre despliegues; sin él, cada redeploy intentaría reinstalar la app.

- En **Persistent Storage** de la app agrega un volumen con ruta de contenedor:
  `/var/www/html/storage`

El `php artisan storage:link` ya se ejecutó en el build, así que `public/storage` apunta al volumen.

## 6. Instalación automática (headless)

El script `docker/entrypoint.d/99-spentz-install.sh` se ejecuta en cada arranque del contenedor y:

1. Si existe `storage/installed`, no hace nada (la app ya está instalada).
2. Si no existe, ejecuta `php artisan app:install --no-interaction`, que lee del entorno `DB_*`, `APP_*`/`TIMEZONE` y `ADMIN_*`: escribe un `.env` listo para producción (`APP_ENV=production`, `APP_DEBUG=false`), reutiliza la `APP_KEY` de `storage/app.key`, ejecuta las migraciones, crea el administrador y marca `storage/installed`.
3. Si la base de datos aún no responde (primera vez), reintenta hasta 30 veces con 5 s de espera (`INSTALL_DB_RETRIES` / `INSTALL_DB_RETRY_DELAY` para ajustar).
4. Al terminar ejecuta `php artisan optimize`. Si falla definitivamente, el contenedor no arranca (se ve en los logs).

**No hace falta correr comandos manuales.** El instalador web (`/install`) queda bloqueado con `APP_INSTALL_MODE=headless` (404).

### En despliegues posteriores

Un redeploy **no** re-ejecuta la instalación (persiste `storage/installed`), pero tampoco corre migraciones: tras cada deploy con cambios de BD ejecuta `php artisan migrate --force` (comandos *After deploy* de Dokploy o desde el Terminal).

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

Cada push a la rama hace build + redeploy (o usa **Deploy** manual). El volumen de `storage` y la BD (recurso Database) se conservan. No olvides que un redeploy **no** ejecuta migraciones: corre `php artisan migrate --force` tras cada deploy con cambios de BD.

## 9. Verificación final

- [ ] `https://spent.tudominio.com` carga con SSL.
- [ ] `php artisan migrate --force` sin errores y login con un usuario creado.
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
| Migraciones olvidadas tras un deploy | Comandos *After deploy* de Dokploy o checklist manual |
| `.env` con credenciales en el repo | Dokploy inyecta variables por entorno; `.dockerignore` excluye `.env` |
| Worker inexistente con `QUEUE_CONNECTION=database` | Usar `sync` (recomendado hoy) o servicio worker aparte |
| Backups | El panel admin genera backup JSON (`/admin/system`); adicionalmente, snapshot periódico de la BD en Dokploy |