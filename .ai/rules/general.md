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

## Seguridad: cuatro cosas que no se deben deshacer
Salieron de una auditoría; cada una tapa un agujero explotable, no una preferencia.

**`TRUSTED_PROXIES` nunca vale `*`.** Con `*` todo cliente es un proxy de confianza:
`X-Forwarded-Host` reescribía el host de `url()`/`route()` — y con él el enlace de
recuperación de contraseña que se manda por correo, o sea, toma de cuenta — y
`X-Forwarded-For` falsificaba `$request->ip()`, con lo que los limitadores de
`login` (5/min por email|IP) y `api.auth` se saltaban rotando la cabecera. El
default en `bootstrap/app.php` es "ningún proxy"; quien tenga uno delante pone su
red. Encima hay `trustHosts` fijado al host de `APP_URL` y `URL::useOrigin()` en
producción. Ver `tests/Feature/ProxyTrustTest.php`.

**Los comprobantes van al disco `receipts`, nunca a `public`.** `public` se sirve
directo por el symlink `public/storage`, fuera del middleware de Laravel: cualquiera
con la URL veía la factura de cualquier usuario. Todo pasa por
`App\Support\ReceiptStorage` y solo sale por `{expenses,incomes}.receipts.show`, que
comprueban la política. No añadir `url` ni `serve` al disco `receipts` ni volver a
publicar `Storage::url()` en un presenter. Ver `tests/Feature/ReceiptAccessTest.php`.

**Cambiar una contraseña revoca el acceso vivo.** `App\Support\AccountAccess::revoke()`
borra los tokens Sanctum y las filas de sesión del usuario; se llama tanto en el
cambio propio (`Settings\SecurityController`) como en el reset del admin. Sin eso,
cambiar la clave tras una intrusión no echaba al intruso.

**La política de contraseñas aplica siempre, no solo en producción.** Antes
`Password::defaults()` devolvía `null` fuera de producción y un `APP_ENV=local`
olvidado —muy fácil con un `.env` escrito a mano— aceptaba claves de un carácter.
Fuera de producción solo se omite `uncompromised()` (necesita red).

## CSP + Vite: el dev server tiene que publicar en IPv4
`vite.config.ts` fija `server.host = "127.0.0.1"` **a propósito**. Con el `localhost` por
defecto, Node (>=17) resuelve antes `::1` y Vite publica en `http://[::1]:5173`; y la
gramática de CSP **no admite literales IPv6** (un host-source solo acepta letras, dígitos y
guiones), así que ese origen no se puede permitir ni escribiéndolo tal cual en la directiva:
el navegador bloquea `@vite/client`, `app.tsx`, `app.css` y las fuentes de `@fonts`, y la app
arranca **en blanco** con decenas de "violates the following Content Security Policy
directive" — incluso con el origen presente en la política. No quites ese `server.host`.

`App\Http\Middleware\SecurityHeaders` lee el origen de `public/hot` (la URL exacta que usará
el navegador; no la adivines) y el `ws://` del HMR solo va en `connect-src`. Si el origen
llegara con corchetes, el middleware **no manda cabecera CSP**: una política que no puede
nombrar ese origen no protege de nada y solo rompe el desarrollo. En producción no hay
fichero `hot` y la política vuelve a ser estricta. Si añades un recurso externo (una fuente,
un CDN de imágenes), añádelo a su directiva concreta: `default-src 'self'` no lo hereda.
Ver `tests/Feature/SecurityHeadersTest.php`.
