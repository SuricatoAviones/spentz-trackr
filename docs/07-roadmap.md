# 07 — Roadmap

## Fase 1 — Base y autenticación ✅
- [x] Migraciones, modelos con relaciones, casts y scopes por usuario.
- [x] Seeders: categorías y orígenes por defecto.
- [x] Políticas de autorización (`ExpensePolicy`, `CategoryPolicy`, …).
- [x] Tests: registro, login, scoping entre usuarios.

## Fase 2 — Núcleo de gastos ✅
- [x] CRUD de gastos con conversión USD/USDT y tasa congelada.
- [x] Formulario con tasa precargada (API o manual) y subida de comprobante.
- [x] CRUD de categorías y orígenes.
- [x] Servicio de tasas (`ExchangeRateService` + `SyncExchangeRates` job + scheduler + caché).
- [x] Dashboard con totales USD/USDT y gráficas (componentes SVG propios, sin librería).
- [x] Listado con filtros y exportación CSV.
- [x] Comparativo mensual (12 meses).

## Fase 3 — PWA ✅
- [x] `manifest.webmanifest` + íconos (instalable Android/iOS/desktop).
- [x] Service Worker (`public/sw.js`) con precache del shell.
- [x] Estrategia offline: cache-first para assets, network-first para datos.

## Fase 4 — API REST ✅
- [x] Auth con tokens Sanctum (register/login/logout/me), expiración 90 días.
- [x] CRUD de gastos, ingresos, categorías y orígenes bajo `/api/v1`.
- [x] Gastos mixtos (`items`) soportados también por la API.
- [x] Reportes (`/reports`, `/reports/monthly-summary`) y tasas (`/rates`, `/rates/sync`).
- [x] Rate limiting (`api` 100/min, `api.auth` 5/min) y CORS por `CORS_ALLOWED_ORIGINS`.
- [x] Documentación OpenAPI con Scramble: UI en `/api/v1`, spec en `/api/v1.json`
      (gate `viewApiDocs`, cerrada en producción). Referencia: `docs/api/api-documentation.md`.

## Fase 5 — Panel de administración ✅
- [x] Dashboard global: métricas, tendencia 12 meses, top categorías y usuarios.
- [x] Usuarios: editar, verificar email, reset de contraseña, suspender/reactivar, eliminar.
- [x] Gastos globales con filtros, exportación CSV y comprobantes inline.
- [x] Tasas del día: ajuste manual y sincronización.
- [x] Gestión global de categorías y orígenes.
- [x] Auditoría de acciones (`admin_actions`) y backup JSON de todas las tablas.

## Fase 6 — Multilenguaje (ES/EN) ✅
Plan en `10-multilenguaje.md`.
- [x] Middleware `SetLocale`, `lang/{es,en}`, ruta `/language`, shared props de Inertia.
- [x] i18next en frontend; `Intl` para fechas/montos/meses.
- [x] Panel admin traducido; selector de idioma en sidebar y Ajustes.
- [x] `tests/Unit/I18nDictionaryTest.php` obliga paridad de keys `es.json` ↔ `en.json`.

## Fase 7 — Open-source y auto-hospedaje ✅
Plan en `11-instalador.md`.
- [x] Instalador web (`/install`), CLI (`app:install`) y Docker Compose.
- [x] Soporte SQLite / MySQL / PostgreSQL.
- [x] **Instalación sin `.env`**: la app arranca y sirve el wizard sin ningún archivo de
      configuración; `APP_KEY` se genera y persiste en `storage/app.key`. El wizard escribe
      un `.env` completo de producción (`APP_ENV`, `APP_DEBUG=false`, `APP_KEY`, `DB_*`,
      `SESSION_SECURE_COOKIE`) y respalda el anterior en `.env.backup`.
- [x] Docker: `APP_KEY` persiste entre recreaciones de contenedor (volumen).
- [x] `app:update`: git pull + dependencias + migraciones + cachés.
- [x] `LICENSE` MIT en la raíz.
- [x] Presupuesto mensual global y por categoría; metas de ahorro; pagos recurrentes.
- [x] Modo claro/oscuro configurable (Ajustes → Apariencia).

## Pendiente
- [ ] Prueba E2E de una instalación limpia (web + CLI + Docker) en staging.
- [ ] Política de versionado y tags de release.
- [ ] Scheduler en el contenedor Docker (hoy la tasa solo se sincroniza al cargar página).
- [ ] Presupuesto por categoría **con alertas/notificaciones**.
- [ ] Paso de correo (`MAIL_*`) en el wizard.

## Ideas v2 (candidatas)
- Pagos recurrentes que generen automáticamente el gasto al vencer.
- Conciliación / import desde extractos bancarios.
- Multi-tenant (organizaciones con varios usuarios).
- Apps nativas sobre la API REST.
