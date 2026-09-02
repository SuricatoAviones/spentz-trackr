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

## Fase 4 — Pulido y despliegue
- [ ] Despliegue en cPanel + MySQL siguiendo `06-despliegue-cpanel.md`.
- [ ] Cron de tasas en producción.
- [ ] Pruebas de rendimiento (índices, caché, paginación).
- [ ] Copias de seguridad de la BD (cron de mysqldump en cPanel).

## Fase 7 — API REST ✅ completada
- [x] Endpoints de autenticación con tokens Sanctum (register/login/logout/me).
- [x] CRUD completo de gastos, ingresos, categorías y fuentes bajo `/api/v1`.
- [x] Conversión de monedas en tiempo real (USD, VES, USDT) con tasa congelada por transacción.
- [x] Reportes anuales y tendencia mensual (`/reports`, `/reports/monthly-summary`).
- [x] Tasas de cambio manuales y sincronización API (`/rates`, `/rates/sync`).
- [x] Rate limiting (`api` 100/min por usuario o IP; `api.auth` 5/min por IP) y seguridad por token.
- [x] CORS configurado para aplicaciones móviles (`config/cors.php`, `sanctum/csrf-cookie`).
- [ ] Documentación completa OpenAPI/Swagger (referencia REST en `docs/api/api-documentation.md`; spec OpenAPI pendiente).

## Ideas v2 (candidatas)
- ~~Ingresos y balance neto~~ ✅ implementado (módulo de ingresos + neto en reportes).
- Presupuestos mensuales por categoría con alertas (presupuesto por categoría ya existe).
- Gastos mixtos (una transacción con dos monedas).
- Metas de ahorro.
- Recordatorios de pagos recurrentes.
- Modo oscuro (idioma inglés ya implementado en Fase 6).

## Fase 5 — Panel de administración (completada)
- [x] Dashboard global: métricas, tendencia 12 meses, top categorías y usuarios.
- [x] Gestión de usuarios: edición, verificación manual de email, reset de contraseña, suspensión/reactivación y eliminación.
- [x] Gastos globales con filtros, exportación CSV y comprobantes.
- [x] Tasas del día: ajuste manual y sincronización con la API.
- [x] Gestión global de categorías y orígenes.
- [x] Auditoría de acciones de administradores (`admin_actions`).
- [x] Sistema: estado del entorno y backup JSON de todas las tablas.

## Fase 6 — Multilenguaje (ES/EN)
Plan detallado en `10-multilenguaje.md`.
- [x] Fase A — Infraestructura backend: middleware `SetLocale`, `lang/es|en`, ruta `/language`, shared props.
- [x] Fase B — i18next en frontend: diccionarios, páginas de usuario, `Intl` en fechas/montos/meses.
- [x] Fase C — Panel admin traducido.
- [x] Fase D — Selector de idioma en sidebar y ajustes.
- [x] Fase E — QA: consistencia de keys, revisión ES/EN, docs y suite verde.