# 06 — Despliegue en cPanel

> **La vía recomendada es el instalador web** (`/install`, `APP_INSTALL_MODE=wizard`): sube
> los archivos, apunta el dominio a `public/`, abre `https://tudominio.com/install` y sigue
> el wizard de 4 pasos (requisitos → base de datos → aplicación → completado). Este
> documento cubre el proceso completo, incluido el modo manual. Ver también
> `11-instalador.md`.

## 1. Requisitos del hosting

| Requisito | Valor |
|---|---|
| PHP | **8.3+** (8.5 recomendado) — *MultiPHP Manager* / *Select PHP Version* |
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

## 6. Instalación con el wizard (recomendado)

**No necesitas crear ni editar un `.env`.** Abre `https://app.tudominio.com` — al no estar
instalada, redirige a `/install`. Sigue los 4 pasos (requisitos → base de datos → aplicación
→ completado). El wizard escribe un `.env` completo y de producción (`APP_ENV=production`,
`APP_DEBUG=false`, `APP_KEY`, `DB_*`, `SESSION_SECURE_COOKIE` según la URL), aplica las
migraciones y crea el administrador. Al terminar, `/install` queda bloqueado.

Requisitos: la **raíz de la app debe ser escribible** (para el `.env`) además de `storage/`
y `bootstrap/cache/` — el paso de Requisitos lo verifica.

> El correo (`MAIL_*` para reset de contraseña) no lo pide el wizard: si lo necesitas,
> añade esas líneas al `.env` después de instalar y ejecuta `php artisan config:clear`.

## 7. Instalación manual (sin wizard)

Si prefieres la CLI, o el hosting no permite escribir el `.env` por web:

```bash
cd ~/spentz-trackr
php artisan app:install     # interactivo: pregunta BD + admin, escribe el .env
php artisan storage:link
php artisan optimize
```

**Sin Terminal:** encadena `php artisan app:install --no-interaction && php artisan storage:link`
en un Cron Job (con las variables `DB_*` y `ADMIN_*` en *Environment* de cPanel) y bórralo
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

`storage/` (y subcarpetas), `bootstrap/cache/` **y la raíz de la app** (para que el wizard
escriba el `.env`) con escritura para el usuario del proceso PHP (775, o 777 en hosts muy
restrictivos). `public/` en 755. Tras instalar puedes volver la raíz a 755.

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
| Error 500 al abrir | `APP_KEY` vacío o permisos de `storage/` | `php artisan key:generate`; 775 a `storage/` y `bootstrap/cache` |
| "Unable to locate file in Vite manifest" | Falta `public/build/` | `npm run build` local y volver a subir |
| Comprobantes no cargan | Falta `storage:link` | `php artisan storage:link` |
| Login en bucle | `SESSION_SECURE_COOKIE=true` sin HTTPS | Activar SSL; o `false` solo para pruebas |
| 404 tras actualizar | Cache de rutas vieja | `php artisan route:clear && php artisan route:cache` |
| `/install` da 404 | `APP_INSTALL_MODE=headless` o ya instalado (`storage/installed`) | Cambiar a `wizard` / borrar el archivo solo si de verdad quieres reinstalar |
