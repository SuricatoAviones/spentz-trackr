# 06 — Despliegue en cPanel

> La app **no tiene instalador**: se configura con un `.env` como cualquier proyecto
> Laravel. El flujo es subir los archivos, apuntar el dominio a `public/`, crear el `.env`,
> generar la clave, migrar y crear el administrador. Este documento cubre el proceso
> completo.

## 1. Requisitos del hosting

| Requisito | Valor |
|---|---|
| PHP | **8.4.1+** (8.5 recomendado) — *MultiPHP Manager* / *Select PHP Version* |
| Extensiones | `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `curl`, `zip`, `bcmath`, `gd`, `fileinfo` |
| Base de datos | MySQL 8 / MariaDB 10.6+ (o PostgreSQL 12+) |
| Acceso | File Manager + **Terminal** (o al menos Cron Jobs) |
| Dominio | Con SSL (AutoSSL / Let's Encrypt) — obligatorio para la PWA y cookies seguras |
| Espacio | ~150 MB (app + vendor + build + storage) |

## 2. Build local

Node solo se usa para compilar; el build se sube ya hecho.

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # genera public/build/ con el manifest de Vite (obligatorio)
```

## 3. Base de datos

cPanel → **MySQL® Databases**: crea la base y un usuario con **ALL PRIVILEGES**. Anota
nombre, usuario, contraseña y host (normalmente `localhost` o `127.0.0.1`).

## 4. Subir los archivos

Sube el proyecto (ZIP o Git) a `~/spentz-trackr/` — **fuera** de `public_html`. Excluye
`node_modules/`, `.git/`, `.env` y el contenido de `storage/app/public/`.

```
home/usuario/
├── public_html/          <- raíz web del dominio
└── spentz-trackr/        <- la app
```

## 5. Apuntar el dominio a `public/`

Laravel solo expone `public/`.

- **Subdominio (recomendado):** cPanel → *Subdomains* → `app.tudominio.com` con document
  root en `spentz-trackr/public`. Activa SSL en *SSL/TLS Status*.
- **Dominio principal:** en `public_html/.htaccess`, redirige a HTTPS y reescribe a
  `/spentz-trackr/public/$1`.

## 6. Crear el `.env`

Copia `.env.example` a `.env` y ajusta al menos estos valores:

```env
APP_NAME="Spentz Trackr"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.tudominio.com
APP_LOCALE=es
APP_TIMEZONE=America/Caracas

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=usuario_spenttrackr
DB_USERNAME=usuario_spentz
DB_PASSWORD=********

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true      # true solo si el dominio tiene HTTPS
CACHE_STORE=database
QUEUE_CONNECTION=database

# Credenciales del primer administrador (las lee `php artisan admin:create`)
ADMIN_NAME=Administrador
ADMIN_EMAIL=admin@tudominio.com
ADMIN_PASSWORD=CambiaEstaClave123
```

`APP_KEY` se deja vacío: lo rellena el siguiente paso.

> El correo (`MAIL_*`, necesario para el reset de contraseña) no viene configurado. Si lo
> necesitas, añade esas líneas y ejecuta `php artisan config:clear`.

## 7. Generar la clave, migrar y crear el administrador

```bash
cd ~/spentz-trackr
php artisan key:generate        # escribe APP_KEY en el .env
php artisan migrate --force
php artisan admin:create        # crea el admin con las credenciales ADMIN_* del .env
php artisan storage:link
php artisan optimize
```

`admin:create` es idempotente, pero **reescribe la contraseña** con `ADMIN_PASSWORD` cada
vez que se ejecuta: córrelo una sola vez y cambia la contraseña desde la app.

**Sin Terminal:** encadena esos comandos en un Cron Job de una sola ejecución y bórralo
después.

## 8. Cron (tasa de cambio + cola)

cPanel → **Cron Jobs**, cada minuto:

```cron
* * * * * cd /home/usuario/spentz-trackr && php artisan schedule:run >> /dev/null 2>&1
```

Esto dispara `SyncExchangeRates` (cada 5 min). Si usas `QUEUE_CONNECTION=database`, añade
un segundo cron con `php artisan queue:work --stop-when-empty`. Aunque el cron falle, el
Dashboard sincroniza la tasa al cargarse (`ensureFreshRate`), así que la app nunca se queda
sin tasa.

## 9. Permisos

`storage/` (y subcarpetas) y `bootstrap/cache/` con escritura para el usuario del proceso
PHP (775, o 777 en hosts muy restrictivos). `public/` y la raíz de la app en 755 — el `.env`
lo creas tú por File Manager o Terminal, así que la raíz no necesita ser escribible por PHP.

## 10. Verificación

- [ ] `https://app.tudominio.com` muestra el landing.
- [ ] Registro / login → Dashboard sin errores.
- [ ] Gasto en Bs → el selector BCV / Paralelo / Personalizada muestra tasas reales.
- [ ] Subir un comprobante y verlo en el detalle (⇒ `storage:link` ok).
- [ ] `storage/logs/laravel.log` sin errores.

## 11. Actualizar

```bash
cd ~/spentz-trackr
git pull                                  # o subir los archivos cambiados
composer install --no-dev --optimize-autoloader
# npm ci && npm run build   (en local, y subir public/build/)
php artisan app:update                    # migraciones + limpia y recachea todo
```

## 12. Problemas frecuentes

| Síntoma | Causa | Solución |
|---|---|---|
| Error 500 al abrir | Falta el `.env`, `APP_KEY` vacío o permisos de `storage/` | Crear el `.env`; `php artisan key:generate`; 775 a `storage/` y `bootstrap/cache` |
| "Unable to locate file in Vite manifest" | Falta `public/build/` | `npm run build` local y volver a subir |
| Comprobantes no cargan | Falta `storage:link` | `php artisan storage:link` |
| Login en bucle | `SESSION_SECURE_COOKIE=true` sin HTTPS | Activar SSL; o `false` solo para pruebas |
| 404 tras actualizar | Cache de rutas vieja | `php artisan route:clear && php artisan route:cache` |
| Cambios del `.env` sin efecto | Config cacheada | `php artisan config:clear && php artisan optimize` |
