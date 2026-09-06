# 07 — Roadmap

## Fase 1 — Base y autenticación ✅ completada
- [x] Configurar MySQL en dev (o mantener SQLite en dev y MySQL en prod).
- [x] Migraciones: `categories`, `payment_sources`, `expenses`, `expense_receipts`, `exchange_rates`.
- [x] Modelos con relaciones, casts de montos y scopes por usuario.
- [x] Seeders: categorías y orígenes por defecto (Binance, Bancos, Wallets, Efectivo).
- [x] Políticas de autorización.
- [x] Tests: registro, login, scoping entre usuarios.

## Fase 2 — Núcleo de gastos ✅ completada
- [x] CRUD de gastos (crear/editar/eliminar/listar) con conversión USD/USDT.
- [x] Formulario con tasa precargada (API o manual) y subida de comprobante.
- [x] CRUD de categorías y orígenes (React + Inertia).
- [x] Servicio de tasas (`ExchangeRateService` + `SyncExchangeRatesJob` + scheduler + caché).
- [x] Dashboard con totales USD/USDT y gráficas.
- [x] Listado con filtros y exportación CSV.
- [x] Comparativo mensual (12 meses).
- [x] Tests completos (Pest) de todos los flujos.

## Fase 3 — PWA ✅ completada
- [x] Manifest + íconos (instalable en Android/iOS/desktop).
- [x] Service Worker con precache del shell (Vite plugin o `workbox`).
- [x] Estrategia offline: cache-first para assets, network-first para datos.
- [x] Tema/color y standalone display.
- [x] Verificación: Lighthouse PWA, instalación real, offline browsing.

## Fase 4 — API REST ✅ completada
- [x] Endpoints de autenticación con tokens Sanctum (register/login/logout/me).
- [x] CRUD completo de gastos, ingresos, categorías y fuentes bajo `/api/v1`.
- [x] Conversión de monedas en tiempo real (USD, VES, USDT) con tasa congelada por transacción.
- [x] Reportes anuales y tendencia mensual (`/reports`, `/reports/monthly-summary`).
- [x] Tasas de cambio manuales y sincronización API (`/rates`, `/rates/sync`).
- [x] Rate limiting (`api` 100/min por usuario o IP; `api.auth` 5/min por IP) y seguridad por token.
- [x] CORS configurado para aplicaciones móviles (`config/cors.php`, `sanctum/csrf-cookie`).
- [x] Documentación OpenAPI/Swagger con Scramble (UI en `/docs/api`, spec en `/docs/api.json`; referencia REST en `docs/api/api-documentation.md`).

## Fase 5 — Panel de administración ✅ completada
- [x] Dashboard global: métricas, tendencia 12 meses, top categorías y usuarios.
- [x] Gestión de usuarios: edición, verificación manual de email, reset de contraseña, suspensión/reactivación y eliminación.
- [x] Gastos globales con filtros, exportación CSV y comprobantes.
- [x] Tasas del día: ajuste manual y sincronización con la API.
- [x] Gestión global de categorías y orígenes.
- [x] Auditoría de acciones de administradores (`admin_actions`).
- [x] Sistema: estado del entorno y backup JSON de todas las tablas.

## Fase 6 — Multilenguaje (ES/EN) ✅ completada
Plan detallado en `10-multilenguaje.md`.
- [x] Fase A — Infraestructura backend: middleware `SetLocale`, `lang/es|en`, ruta `/language`, shared props.
- [x] Fase B — i18next en frontend: diccionarios, páginas de usuario, `Intl` en fechas/montos/meses.
- [x] Fase C — Panel admin traducido.
- [x] Fase D — Selector de idioma en sidebar y ajustes.
- [x] Fase E — QA: consistencia de keys, revisión ES/EN, docs y suite verde.

## Fase 7 — Open-source y auto-hospedaje (en curso)
Plan detallado en `11-instalador.md`.
- [x] Documentación OpenAPI/Swagger accesible en `/api/v1` (UI) y `/api/v1.json` (spec).
- [x] Gate `viewApiDocs` para controlar el acceso a la documentación.
- [x] Instalador web (`/install`): middleware `EnsureInstalled`, wizard de 4 pasos (Requisitos, Base de datos, Aplicación, Completado).
- [x] Soporte de SQLite, MySQL y PostgreSQL en el instalador.
- [x] Servicio compartido `App\Services\Installer`.
- [x] Instalador CLI (`php artisan app:install`) con flags no interactivos y `--force`.
- [x] Comando de actualización (`php artisan app:update`): git pull + dependencias + migraciones + cachés.
- [x] Docker Compose (app + MySQL), override PostgreSQL y SQLite, `.env.docker`.
- [x] Docs `11-instalador.md`.
- [x] Tests del instalador (requisitos, comandos registrados, endpoint de requisitos).
- [x] Presupuesto global mensual (límite total de gasto con barra de progreso en el dashboard).
- [x] Modo oscuro/claro configurable en Ajustes → Apariencia.
- [ ] Prueba E2E real de una instalación limpia (web + CLI + Docker) en entorno de staging.
- [ ] Política de versionado y tags.

## Ideas v2 (candidatas)
- Presupuestos mensuales por categoría con alertas (presupuesto por categoría ya existe).
- Gastos mixtos (una transacción con dos monedas).
- Metas de ahorro.
- Recordatorios de pagos recurrentes.
