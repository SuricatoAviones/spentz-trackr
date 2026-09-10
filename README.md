# Spentz Trackr

**Control de gastos e ingresos multi-moneda (USD · Bs · USDT), auto-hospedable y pensado
para Venezuela.**

La pregunta que responde de un vistazo: *¿cuánto gasté (y gané) este mes en USD y en
USDT?* Cada movimiento congela su tasa de cambio al registrarse, así que los reportes
históricos nunca se distorsionan cuando la tasa vuelve a moverse.

> Español primero (mercado principal), con interfaz completa en inglés.

---

## Características

- **Gastos e ingresos** en USD, Bs o USDT, con conversión automática y **tasa congelada por
  transacción** (`exchange_rate`, `usd_amount`, `usdt_amount` se guardan al escribir).
- **Tasa Bs/USD automática** desde [ve.dolarapi.com](https://ve.dolarapi.com) (BCV y
  paralelo) cada 5 minutos, con override manual por día.
- **Comisiones de pago móvil / transferencia** en Bs: `max(mínimo, monto × %)`,
  configurable en Ajustes.
- **Gastos mixtos**: una transacción con varias monedas (líneas `ExpenseItem`).
- **Metas de ahorro** con aportes, y **pagos recurrentes** con vencimientos.
- **Reportes**: dashboard mensual, tendencia 12 meses, desglose por categoría/origen,
  presupuesto mensual global y por categoría, exportación **CSV** (UTF-8 con BOM).
- **API REST** (`/api/v1`, tokens Sanctum) documentada con OpenAPI/Scramble.
- **Panel de administración** multi-usuario: usuarios, gastos globales, tasas, auditoría y
  backup JSON.
- **PWA** instalable (offline básico del shell).
- **Multiusuario** con aislamiento por `user_id`, verificación de correo, 2FA y passkeys
  (Laravel Fortify).

## Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel 13 · PHP 8.3+ (8.5 recomendado) |
| Frontend | Inertia v3 · React 19 · Tailwind 4 · shadcn/ui |
| Rutas tipadas | Laravel Wayfinder |
| Base de datos | SQLite · MySQL 8 · PostgreSQL 12+ |
| API | Sanctum · Scramble (OpenAPI) |
| Tests | Pest · Larastan (nivel 7) · Pint |

## Instalación

Hay **tres vías**, documentadas en [`docs/11-instalador.md`](docs/11-instalador.md):

1. **Instalador web** (`/install`) — wizard de 4 pasos para cPanel / VPS con LAMP.
2. **CLI** — `php artisan app:install` con flags no interactivos (VPS, CI).
3. **Docker Compose** — entorno llave en mano (app + MySQL/PostgreSQL/SQLite).

### Desarrollo

```bash
git clone https://github.com/SuricatoAviones/spentz-trackr.git
cd spentz-trackr
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
composer run dev            # serve + queue:listen + vite (loop principal)
```

Crear un administrador: `php artisan admin:create` (lee `ADMIN_NAME`/`ADMIN_EMAIL`/
`ADMIN_PASSWORD` del `.env`).

## Comandos útiles

```bash
php artisan test --compact                 # Pest
composer run ci:check                      # eslint + prettier + tsc + phpstan + pint + tests
vendor/bin/pint --dirty                    # formateo PHP
npm run build                              # assets (necesario antes de tests de páginas Inertia)
php artisan wayfinder:generate --with-form # regenerar helpers TS de rutas
```

> En local puede hacer falta subir `memory_limit` en `php.ini` para correr toda la suite
> y PHPStan; en CI (`setup-php`) es ilimitado.

## Documentación

El índice está en [`docs/README.md`](docs/README.md). Empezar por
[`docs/04-arquitectura.md`](docs/04-arquitectura.md) (contiene los ADRs) antes de
implementar features.

## Licencia

MIT (declarada en `composer.json`).
