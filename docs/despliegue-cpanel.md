# Manual de despliegue en cPanel — Spent Trackr

Guía paso a paso para subir Spent Trackr (Laravel 13 + Inertia React + MySQL) a un hosting cPanel.

---

## 1. Requisitos

Antes de empezar, verifica que tu hosting cPanel cumpla esto:

| Requisito | Valor |
|---|---|
| PHP | **8.3 o superior** (Select PHP Version → MultiPHP) |
| Extensión PHP | `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `zip`, `bcmath` (activas por defecto en casi todos los hosting) |
| Base de datos | MySQL 5.7+ o MariaDB 10.3+ |
| Espacio | ~120 MB (app + vendor + build + storage) |
| Acceso | File Manager + **Terminal** (o al menos Cron Jobs) |
| Dominio | Con SSL activado (Let's Encrypt) |

> La app exige PHP ^8.3. Si tu cPanel solo ofrece 8.2 o inferior, no podrás desplegarla ahí.

---

## 2. Preparar el proyecto en tu PC (Windows)

En la carpeta del proyecto, desde PowerShell:

```powershell
# 1. Dependencias de producción (sin herramientas de desarrollo)
composer install --no-dev --optimize-autoloader

# 2. Dependencias JS y compilación de assets
npm ci
npm run build
```

Esto genera la carpeta `public/build/` con el manifest de Vite. **Es obligatorio subirla.**

> La app ya está pensada para producción: la tasa de cambio se sincroniza automáticamente
> al cargar el Dashboard (no depende del cron para funcionar), así que la app funciona
> incluso si el cron no está configurado todavía.

---

## 3. Crear la base de datos en cPanel

1. Entra a cPanel → **MySQL® Databases**.
2. Crea una base: `usuario_spenttrackr` (guarda el nombre exacto).
3. Crea un usuario: `usuario_spentusr` con contraseña segura.
4. Añade el usuario a la base con **TODOS los privilegios** (ALL PRIVILEGES).
5. Anota el **host** de la BD. Normalmente es `localhost`, pero algunos hosting usan
   `127.0.0.1` o un host tipo `mysql.tudominio.com` (lo verás en "Remote MySQL" o en los
   datos de conexión de tu hosting).

---

## 4. Subir los archivos al servidor

1. En tu PC, crea un ZIP de la carpeta del proyecto **excluyendo**:
   - `node_modules/`
   - `.git/`
   - `.env` (¡nunca subas el .env local!)
   - `storage/app/public/*` (solo el contenido, no la estructura)
   - `tests/` (opcional, solo es desarrollo)

   En PowerShell, desde la carpeta del proyecto:

   ```powershell
   # Crea el zip sin node_modules y sin .git
   Compress-Archive -Path .\* -DestinationPath ..\spent_trackr.zip -Exclude "*.git*"
   ```

   > Si `Compress-Archive` no respeta las exclusiones bien, usa el explorador de Windows:
   > selecciona todo menos `node_modules` y `.git`, clic derecho → Enviar a → Carpeta comprimida.

2. En cPanel abre **File Manager** y entra a tu carpeta raíz (por ejemplo `home/usuario/`).
3. Crea una carpeta para la app, por ejemplo `spent_trackr/`.
4. Sube `spent_trackr.zip` dentro de `spent_trackr/` y haz clic derecho → **Extract**.
5. Borra el ZIP una vez extraído.

La estructura final debe verse así:

```
home/usuario/
├── public_html/          <- raíz web del dominio
└── spent_trackr/         <- la app (dentro: app/, public/, vendor/, etc.)
```

---

## 5. Configurar el .env en el servidor

1. En el File Manager, entra a `spent_trackr/` y renombra `.env.example` a `.env`.
2. Edítalo con el editor de texto de cPanel y ajusta:

```env
APP_NAME="Spent Trackr"
APP_ENV=production
APP_KEY=                # se genera en el paso 7 (php artisan key:generate)
APP_DEBUG=false
APP_URL=https://tudominio.com

# Base de datos (los datos del paso 3)
DB_CONNECTION=mysql
DB_HOST=localhost       # usa el host que te indicó tu hosting
DB_PORT=3306
DB_DATABASE=usuario_spenttrackr
DB_USERNAME=usuario_spentusr
DB_PASSWORD=TuContraseñaSegura

# Colas: la app no necesita worker de colas para funcionar,
# pero si se agregan tareas en segundo plano usa:
QUEUE_CONNECTION=database

# Sesión segura bajo HTTPS
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true

# Correo (activa el reenvío de contraseña / verificación de email)
MAIL_MAILER=smtp
MAIL_HOST=smtp.tudominio.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@tudominio.com
MAIL_PASSWORD=TuClaveSMTP
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=no-reply@tudominio.com
MAIL_FROM_NAME="Spent Trackr"
```

> Guarda y cierra. No subas nunca tu `.env` de desarrollo a GitHub ni al servidor.

---

## 6. Apuntar el dominio a la carpeta public

Laravel solo expone la carpeta `public/`; todo lo demás no debe ser accesible por web.
Tienes dos opciones:

### Opción A — Subdominio (recomendada)

1. cPanel → **Subdomains** → crea `app.tudominio.com` con la **carpeta raíz**
   apuntando a `spent_trackr/public` (ej. `home/usuario/spent_trackr/public`).
2. Configura SSL para el subdominio en **SSL/TLS Status**.
3. En el `.env` pon `APP_URL=https://app.tudominio.com`.

### Opción B — Dominio principal (public_html)

Si la app va en `tudominio.com`, crea un `.htaccess` en `public_html` que delegue en la
carpeta `public` de la app:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Redirige a HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    RewriteRule ^(.*)$ /spent_trackr/public/$1 [L]
</IfModule>
```

> Reemplaza `spent_trackr` por el nombre real de tu carpeta.

---

## 7. Ejecutar los comandos de instalación

### Si tu cPanel tiene Terminal (recomendado)

1. cPanel → **Terminal**.
2. Navega a la app y ejecuta:

```bash
cd ~/spent_trackr

# Genera la llave de la app
php artisan key:generate

# Crea el enlace de storage (para los comprobantes de los gastos)
php artisan storage:link

# Crea las tablas en MySQL
php artisan migrate --force

# Cachea la configuración para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Si no tienes Terminal

Puedes ejecutar los mismos comandos **una sola vez** a través de un cron job:

1. cPanel → **Cron Jobs**.
2. Comando (ejecuta cada minuto; al minuto siguiente ya se habrá ejecutado):

```bash
cd /home/usuario/spent_trackr && php artisan key:generate --force && php artisan migrate --force && php artisan storage:link && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

3. Bórralo después de que termine (o déjalo: los comandos son idempotentes y no rompen nada).

---

## 8. Configurar los cron jobs (tasa de cambio)

La app sincroniza las tasas BCV/Paralela con dolarapi.com cada 5 minutos mediante
el scheduler de Laravel. En cPanel el scheduler se activa con un cron que corre cada minuto:

1. cPanel → **Cron Jobs**.
2. Crea un cron job:

```
Minuto: *    Hora: *    Día: *    Mes: *    Día semana: *
```

3. Comando:

```bash
cd /home/usuario/spent_trackr && php artisan schedule:run >> /dev/null 2>&1
```

> Aunque el cron falle, el Dashboard sincroniza la tasa automáticamente al cargarse,
> así que la app nunca se queda sin tasa.

**Worker de colas (opcional):** si algún día agregas jobs (`QUEUE_CONNECTION=database`),
añade un segundo cron:

```bash
cd /home/usuario/spent_trackr && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

---

## 9. Permisos de carpetas

En el File Manager, clic derecho sobre cada carpeta → **Change Permissions**:

| Ruta | Permisos |
|---|---|
| `spent_trackr/storage` | 775 (o 777 si el hosting es restrictivo) |
| `spent_trackr/storage/framework/*` | 775 |
| `spent_trackr/storage/logs` | 775 |
| `spent_trackr/bootstrap/cache` | 775 |
| `spent_trackr/public` | 755 |

El usuario del servidor debe poder **escribir** en `storage/` (recibos, sesiones, logs).

---

## 10. Verificación final

- [ ] Abre `https://tudominio.com` → debe verse el landing de Spent Trackr.
- [ ] Regístrate o inicia sesión → Dashboard sin errores.
- [ ] Crea un gasto en Bs → el selector BCV/Paralelo/Personalizada muestra tasas reales.
- [ ] Sube un comprobante y ábrelo en el detalle del gasto → la imagen se ve (storage:link ok).
- [ ] Revisa `storage/logs/laravel.log` si algo falla.

---

## 11. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| Error 500 al abrir la app | `APP_KEY` vacío o permisos de `storage/` | Ejecuta `php artisan key:generate` y da 775 a `storage/` y `bootstrap/cache` |
| "Unable to locate file in Vite manifest" | Subiste sin `public/build/` | Ejecuta `npm run build` local y vuelve a subir la carpeta |
| Los comprobantes no cargan | Falta `storage:link` | Ejecuta `php artisan storage:link` (paso 7) |
| No aparece la tasa al crear gasto | La API no respondió en ese momento | Entra al Dashboard (sincroniza) o toca "Sincronizar" en Ajustes |
| Imagen muy pesada no sube | Límite de `upload_max_filesize` del hosting | Sube a 8M en PHP Configuration |
| Login no guarda sesión / redirige en bucle | `SESSION_SECURE_COOKIE=true` sin HTTPS | Activa SSL del dominio, o pon `false` solo para pruebas |
| Correo no llega | SMTP mal configurado | Verifica MAIL_* en el `.env`; prueba con el SMTP de tu hosting |
| La tasa no se actualiza a las 08:00 | Cron no configurado o borrado | Revisa el cron del paso 8; el Dashboard la sincroniza igual |
| 404 en rutas con cache de rutas vieja | `route:cache` con rutas antiguas | Ejecuta `php artisan route:clear` y luego `php artisan route:cache` |

---

## 12. Actualizar la app a una versión nueva

1. En tu PC: `git pull`, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
2. Sube al servidor solo los archivos cambiados (o el zip completo, pasos 4–5).
3. En el Terminal:

```bash
cd ~/spent_trackr
php artisan migrate --force
php artisan config:clear && php artisan config:cache
php artisan route:clear && php artisan route:cache
php artisan view:clear && php artisan view:cache
```

4. Verifica el checklist del paso 10.

---

## Notas importantes

- **Nunca** subas tu `.env` de desarrollo ni tu `.git` al servidor.
- La app está en español (es-VE), con formato de montos venezolano (coma decimal).
- Las tasas manuales (Ajustes) tienen prioridad sobre las automáticas del día.
- Los presupuestos por categoría son en USD equivalentes al mes en curso.