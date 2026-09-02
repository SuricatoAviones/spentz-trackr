# 06 — Despliegue en cPanel + MySQL

## Requisitos del hosting

| Requisito | Valor mínimo | Notas |
|---|---|---|
| PHP | **8.3+** (recomendado 8.4/8.5) | Se elige en *MultiPHP Manager* de cPanel; Laravel 13 requiere 8.3 |
| Extensiones PHP | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `gd` o `imagick` (comprobantes) | Verificar en *Select PHP Version* |
| MySQL | 5.7+ / MariaDB 10.3+ | InnoDB, utf8mb4 |
| Node.js | Solo en desarrollo | El build (`npm run build`) se hace local y se suben los assets |
| HTTPS | Obligatorio | SSL gratis con AutoSSL; requerido para PWA futura |

## Pasos de despliegue

1. **Build local:**
   ```bash
   composer install --optimize-autoloader --no-dev
   npm ci && npm run build
   ```
2. **Subir el proyecto** (zip a File Manager o Git) a `~/spent_trackr` (fuera de `public_html` idealmente).
3. **Public root:** apuntar el dominio/subdominio a `spent_trackr/public` (o mover el contenido de `public/` a `public_html/` y ajustar rutas de framework).
4. **Crear la base de datos MySQL** en cPanel y generar usuario con todos los privilegios.
5. **Configurar `.env`**:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://tudominio.com
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=usuario_spenttrackr
   DB_USERNAME=usuario_spenttrackr
   DB_PASSWORD=*****
   CACHE_STORE=database        # o file
   SESSION_DRIVER=database     # o file
   QUEUE_CONNECTION=database   # ver colas abajo
   FILESYSTEM_DISK=public
   ```
   > `SESSION_DRIVER` y `CACHE_STORE` en `database` evitan permisos de escritura en `storage` en hosts compartidos; si el hosting permite escritura, `file` es más rápido.
6. **Comandos post-despliegue:**
   ```bash
   php artisan key:generate
   php artisan migrate --force
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan optimize
   ```
7. **Cron (tasa de cambio + cola):** en *Cron Jobs* de cPanel:
   ```cron
   * * * * * php /home/usuario/spent_trackr/artisan schedule:run >> /dev/null 2>&1
   ```
   Dentro de `bootstrap/app.php` se registran: `SyncExchangeRatesJob` (cada 5 minutos) y, si se usa cola `database`, `queue:work --once --stop-when-empty` cada minuto (sin supervisor en hosting compartido).
8. **Permisos:** `storage/` y `bootstrap/cache/` con escritura para el usuario del proceso PHP.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Sin supervisor para colas | Cron con `queue:work --once`; o driver `sync` para jobs de tasas |
| API dolarapi.com inaccesible desde el hosting | Fallback: tasa manual por usuario + último valor persistido |
| Subir montos con `float` | Todo `DECIMAL`; cálculo con `bcmath` si es necesario |
| SSL ausente | AutoSSL obligatorio (también para service worker PWA) |
| Imágenes de comprobantes pesadas | Validación (máx. ~4MB) y optimización al subir; disco `public` con symlink |
| `.env` expuesto | `public/.htaccess` por defecto de Laravel bloquea `.env`; nunca subir `vendor` de dev ni `.env` con credenciales reales al repo |

## Nota PWA (fase 3)

El manifest y el service worker se servirán desde `public/`; el build de Vite generará los assets con nombres hash. En cPanel basta con subir el build; no se requiere configuración extra de servidor (Apache). Requiere HTTPS (AutoSSL).