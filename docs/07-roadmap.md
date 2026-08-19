# 07 — Roadmap

## Fase 1 — Base y autenticación
- [ ] Configurar MySQL en dev (o mantener SQLite en dev y MySQL en prod).
- [ ] Migraciones: `categories`, `payment_sources`, `expenses`, `expense_receipts`, `exchange_rates`.
- [ ] Modelos con relaciones, casts de montos y scopes por usuario.
- [ ] Seeders: categorías y orígenes por defecto (Binance, Bancos, Wallets, Efectivo).
- [ ] Políticas de autorización.
- [ ] Tests: registro, login, scoping entre usuarios.

## Fase 2 — Núcleo de gastos
- [ ] CRUD de gastos (crear/editar/eliminar/listar) con conversión USD/USDT.
- [ ] Formulario con tasa precargada (API o manual) y subida de comprobante.
- [ ] CRUD de categorías y orígenes (React + Inertia).
- [ ] Servicio de tasas (`ExchangeRateService` + `SyncExchangeRatesJob` + scheduler + caché).
- [ ] Dashboard con totales USD/USDT y gráficas.
- [ ] Listado con filtros y exportación CSV.
- [ ] Comparativo mensual (12 meses).
- [ ] Tests completos (Pest) de todos los flujos.

## Fase 3 — PWA
- [ ] Manifest + íconos (instalable en Android/iOS/desktop).
- [ ] Service Worker con precache del shell (Vite plugin o `workbox`).
- [ ] Estrategia offline: cache-first para assets, network-first para datos.
- [ ] Tema/color y standalone display.
- [ ] Verificación: Lighthouse PWA, instalación real, offline browsing.

## Fase 4 — Pulido y despliegue
- [ ] Despliegue en cPanel + MySQL siguiendo `06-despliegue-cpanel.md`.
- [ ] Cron de tasas en producción.
- [ ] Pruebas de rendimiento (índices, caché, paginación).
- [ ] Copias de seguridad de la BD (cron de mysqldump en cPanel).

## Ideas v2 (candidatas)
- Ingresos y balance neto.
- Presupuestos mensuales por categoría con alertas.
- Gastos mixtos (una transacción con dos monedas).
- Metas de ahorro.
- Recordatorios de pagos recurrentes.
- API pública.
- Modo oscuro y/o idioma inglés.

## Fase 5 — Panel de administración (completada)
- [x] Dashboard global: métricas, tendencia 12 meses, top categorías y usuarios.
- [x] Gestión de usuarios: edición, verificación manual de email, reset de contraseña, suspensión/reactivación y eliminación.
- [x] Gastos globales con filtros, exportación CSV y comprobantes.
- [x] Tasas del día: ajuste manual y sincronización con la API.
- [x] Gestión global de categorías y orígenes.
- [x] Auditoría de acciones de administradores (`admin_actions`).
- [x] Sistema: estado del entorno y backup JSON de todas las tablas.