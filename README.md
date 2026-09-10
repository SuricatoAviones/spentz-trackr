<div align="center">

# Spentz Trackr

**Control de gastos e ingresos multi-moneda (USD · Bs · USDT), _open source_ y auto-hospedable.**

Pensado para Venezuela, donde conviven tres monedas y la tasa de cambio se mueve todos los días.

</div>

---

## ¿Qué es?

Spentz Trackr es una aplicación web que respondes de un vistazo:
**¿cuánto gasté y cuánto gané este mes, en USD y en USDT?**

Es **software libre** (licencia MIT): clonas el repositorio, lo instalas en tu propio
servidor y tus datos financieros nunca salen de ahí. No hay SaaS, no hay cuenta central, no
hay telemetría.

## ¿Cómo funciona?

El problema que resuelve: si registras un gasto en bolívares hoy y consultas el reporte
dentro de tres meses, la tasa ya cambió y el "equivalente en dólares" que ves sería falso.

Spentz Trackr **congela la conversión en el momento de la transacción**:

```
Gasto: 280 Bs  ·  tasa del día: 28,00 Bs/USD  ·  comisión pago móvil: 14 Bs
        └── se guarda para siempre: exchange_rate = 28.0000
                                    usd_amount   = (280 + 14) / 28 = 10.50
                                    usdt_amount  = 10.50   (USDT ≈ 1:1 con USD)
```

A partir de ahí, **los reportes nunca recalculan** con la tasa actual. Un gasto de agosto
vale lo que valía en agosto.

Alrededor de esa idea:

- **Tres monedas** por transacción: USD, Bs y USDT. Un gasto puede incluso ser **mixto**
  (parte en Bs, parte en USDT) y cada porción congela su propia tasa.
- **Tasa Bs/USD automática** desde [ve.dolarapi.com](https://ve.dolarapi.com) (BCV y
  paralelo), sincronizada cada 5 minutos, con override manual por día.
- **Comisiones de pago móvil / transferencia**: `max(mínimo, monto × %)`, configurable.
- **Gastos, ingresos, metas de ahorro y pagos recurrentes**, con presupuesto mensual
  global y por categoría.
- **Reportes** con exportación CSV, **API REST** (tokens Sanctum + OpenAPI) y
  **panel de administración** multiusuario.
- **PWA** instalable, interfaz en **español e inglés**, 2FA y passkeys.

## Instalación

> **No tienes que crear ni editar un `.env` a mano.** El instalador escribe toda la
> configuración (incluida la `APP_KEY`) y deja la app lista para producción.

### 1 · Instalador web — cPanel / VPS con LAMP

Sube los archivos, apunta el dominio a `public/` y abre `https://tu-dominio.com`.
Como todavía no está instalada, te redirige al **wizard de 4 pasos**:

1. **Requisitos** — versión de PHP, extensiones y permisos de escritura.
2. **Base de datos** — SQLite, MySQL o PostgreSQL (host, usuario, contraseña).
3. **Aplicación** — nombre, URL, idioma, zona horaria y la cuenta de administrador.
4. **Listo** — se escribe el `.env`, se genera la clave, se migran las tablas y se crea el
   admin. `/install` queda bloqueado.

Detalle paso a paso en [`docs/06-despliegue-cpanel.md`](docs/06-despliegue-cpanel.md).

### 2 · CLI — servidores sin navegador

```bash
php artisan app:install     # interactivo, o con flags --db-* / --admin-* para scripts
```

### 3 · Docker Compose — llave en mano

```bash
git clone https://github.com/SuricatoAviones/spentz-trackr.git
cd spentz-trackr
cp .env.docker .env         # ajusta DB_PASSWORD y ADMIN_*
docker compose up -d --build
```

El contenedor se **instala solo** en el primer arranque (modo *headless*) y genera y
persiste la `APP_KEY` en el volumen. App en `http://localhost:8080`.
Variantes PostgreSQL y SQLite en [`docs/11-instalador.md`](docs/11-instalador.md);
despliegue con Dokploy/Traefik en [`docs/09-despliegue-dokploy.md`](docs/09-despliegue-dokploy.md).

### Actualizar

```bash
php artisan app:update      # git pull + dependencias + migraciones + limpieza de cachés
```

## Desarrollo

```bash
git clone https://github.com/SuricatoAviones/spentz-trackr.git
cd spentz-trackr
composer install && npm install
php artisan app:install     # o configura el .env a mano para dev
composer run dev            # serve + queue:listen + vite
```

```bash
php artisan test --compact                 # Pest
composer run ci:check                      # eslint + prettier + tsc + phpstan + pint + tests
vendor/bin/pint --dirty                    # formateo PHP
```

> En local puede hacer falta subir `memory_limit` en `php.ini` para correr toda la suite y
> PHPStan; en CI es ilimitado.

## Stack

Laravel 13 (PHP 8.3+) · Inertia v3 · React 19 · Tailwind 4 · shadcn/ui · Wayfinder ·
Sanctum · Scramble · Pest / Larastan / Pint. Base de datos SQLite, MySQL 8 o PostgreSQL 12+.

## Documentación

Índice en [`docs/README.md`](docs/README.md). Para trabajar en el código, empieza por
[`docs/04-arquitectura.md`](docs/04-arquitectura.md) (contiene los ADRs) y las reglas de
`.ai/rules/`.

## Contribuir

Es un proyecto abierto: issues y pull requests son bienvenidos. Antes de un PR, corre
`composer run ci:check` (debe quedar en verde). Las decisiones de arquitectura se
documentan como ADRs en `docs/04`.

## Licencia

[MIT](LICENSE).
