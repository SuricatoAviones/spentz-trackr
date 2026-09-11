---
paths:
  - '**'
  - 'Dockerfile, vite.config.ts'
---

# General

## Planificación del proyecto en docs/
La planificación, casos de uso, modelo de datos, arquitectura y despliegue cPanel viven en docs/ (README.md es el índice). Leer docs/04-arquitectura.md antes de implementar nuevas features. Multi-usuario, solo gastos, sin saldos ni presupuestos en v1.

## Wayfinder in Docker: generate types in PHP stage, skip with SKIP_WAYFINDER
Wayfinder's Vite plugin always runs `php artisan wayfinder:generate` in buildStart and has no skip flag. The Docker build has no PHP in the node:22-alpine frontend stage, so: (1) a `wayfinder` stage (same serversideup/php image) runs `php artisan wayfinder:generate --with-form`; (2) generated `resources/js/{actions,routes,wayfinder}` are copied into the frontend stage; (3) `vite.config.ts` maps the plugin `command` to `node -e 0 --` when `SKIP_WAYFINDER=1` (set only in the frontend Docker stage). Don't try to remove the plugin or the command from the config because local `npm run dev` needs auto-regeneration.

## El piso de PHP es 8.4.1 y se declara en tres sitios a la vez
`laravel/framework` 13 arrastra symfony 8.1 (`php >=8.4.1`) y pest 5 pide `^8.4`, así que el
lock **no** se puede instalar en 8.3 aunque `composer.json` dijera `^8.3`. Como composer
resuelve contra el PHP de la máquina (aquí 8.5), un `composer update` local generaba un lock
que reventaba el CI con *"Your lock file does not contain a compatible set of packages"*. Por
eso: `require.php` = `^8.4`, `config.platform.php` = `8.4.1` (fija la resolución al piso, no al
PHP local) y `php-version` del workflow = `8.4`. Si mueves el piso, mueve los tres juntos y
corre `composer update --lock`.

## El build de Docker compila extensiones: serial y en su propia etapa
`serversideup/php` solo incluye `opcache pcntl pdo_mysql pdo_pgsql redis zip`; `bcmath`, `gd` e
`intl` se compilan con `install-php-extensions`, que usa `make -j$(nproc)`. En un VPS de 2 GB
(Dokploy + Traefik + MySQL encima) eso muere por OOM: `Killed` / `exit code: 137`. Se arregla
con `IPE_PROCESSOR_COUNT=1` (compilación en serie) y poniendo las extensiones en la etapa
`php-base` de la que dependen `wayfinder` y el runtime, para que BuildKit no solape ese paso
con `npm run build`. No devuelvas el `install-php-extensions` a la etapa final.
