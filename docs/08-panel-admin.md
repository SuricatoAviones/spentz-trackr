# 08 — Panel de Administración

Panel completo de administración para la plataforma multi-usuario. Vive bajo el prefijo `/admin` (`routes/admin.php`) protegido por los middleware `auth` + `verified` + `admin` (alias `EnsureUserIsAdmin`, responde **403** para no administradores). Los controladores cruzan usuarios a propósito (sin scoping `forUser`).

## Acceso

- Crear un admin: `php artisan admin:create` (usa `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` del `.env`).
- El admin **no puede eliminarse ni suspenderse a sí mismo** (403).
- Un usuario suspendido queda bloqueado en toda la app, incluido el panel (middleware `EnsureUserNotSuspended` en el grupo web).

## Módulos

### Dashboard (`GET /admin`)
- 7 tarjetas de estadísticas globales (usuarios, nuevos del mes, verificados, admins, activos, gastos totales, total USD).
- **Tendencia 12 meses** en USD (año actual, agrupado por mes).
- **Top 5 categorías** y **Top 5 usuarios** por gasto total en USD (con % del total).
- Últimos usuarios registrados.

### Usuarios (`/admin/users`)
- Listado con búsqueda y métricas por usuario (nº de gastos, total USD, última actividad).
- Detalle: editar nombre/email/rol, **verificar email manualmente**, **restablecer contraseña** (reglas de Fortify), **suspender/reactivar** y eliminar.

### Gastos (`/admin/expenses`)
- Listado global con filtros: búsqueda, usuario, categoría, moneda y rango de fechas.
- **Exportación CSV** (`GET /admin/expenses/export`) respetando los filtros; incluye columnas de usuario y email.
- **Comprobantes**: diálogo por gasto que abre cada archivo (`GET /admin/expenses/receipts/{receipt}`, inline).
- Eliminar un gasto borra sus comprobantes.

### Tasas (`/admin/rates`)
- Tasas del día (BCV/paralelo) de la API, sobrescritura manual del día (`PUT /admin/rates`, invalida la caché `exchange-rate:0:{date}`) y sincronización inmediata con dolarapi.com (`POST /admin/rates/sync`).
- Historial de las últimas 50 tasas (API y manuales por usuario).

### Categorías y orígenes (`/admin/categories`, `/admin/sources`)
- Listados globales con usuario propietario, nº de gastos y total USD.
- Editar nombre/icono/color (y presupuesto en categorías).
- Eliminar bloqueado con error flash si el registro tiene gastos asociados (FK `RESTRICT`).

### Auditoría (`/admin/audit`)
- Registro de acciones de administradores (`admin_actions`), paginado, con fecha, admin, acción traducida y objetivo (eager-loading morph sin N+1).
- Acciones registradas: `user.updated`, `user.deleted`, `user.verified`, `user.password_reset`, `user.suspended`, `user.reactivated`, `expense.deleted`, `expenses.exported`, `rate.updated`, `rate.synced`, `category.updated`, `category.deleted`, `source.updated`, `source.deleted`, `backup.generated`.

### Sistema y backup (`/admin/system`)
- Estado: entorno, versiones de PHP/Laravel, driver de BD (con chequeo `SELECT 1`), caché y escritura de `storage`.
- Volumen de datos por tabla.
- **Backup** (`POST /admin/backup`): descarga JSON con todas las tablas (usuarios, categorías, orígenes, tasas, gastos y comprobantes). Los archivos de comprobantes no se incluyen. Queda registrado en auditoría.

## Rutas (resumen)

| Método | Ruta | Nombre |
|---|---|---|
| GET | `/admin` | `admin.dashboard` |
| GET/PATCH/DELETE | `/admin/users` | `admin.users.index/update/destroy` |
| GET | `/admin/users/{user}` | `admin.users.show` |
| POST | `/admin/users/{user}/verify-email` | `admin.users.verify-email` |
| POST | `/admin/users/{user}/reset-password` | `admin.users.reset-password` |
| POST | `/admin/users/{user}/suspend` · `/reactivate` | `admin.users.suspend/reactivate` |
| GET/DELETE | `/admin/expenses` | `admin.expenses.index/destroy` |
| GET | `/admin/expenses/export` | `admin.expenses.export` |
| GET | `/admin/expenses/receipts/{receipt}` | `admin.expenses.receipts.show` |
| GET/PUT/POST | `/admin/rates` | `admin.rates.index/update/sync` |
| GET/PATCH/DELETE | `/admin/categories` · `/admin/sources` | `admin.categories.*` / `admin.sources.*` |
| GET | `/admin/audit` | `admin.audit.index` |
| GET/POST | `/admin/system` · `/admin/backup` | `admin.system.index/backup` |

## Frontend

- Páginas React + Inertia en `resources/js/pages/admin/**` con shadcn/ui (Card, Badge, Dialog, Input…).
- Ítems de navegación en `adminNavItems` de `resources/js/components/app-sidebar.tsx`.
- Enlaces con Wayfinder desde módulos agrupados (`@/routes/admin/…`). Una ruta con nombre de subgrupo (ej. `admin.expenses.receipts.show`) se genera en módulo anidado (`@/routes/admin/expenses/receipts`).
- Tests: `tests/Feature/Admin/` (listados, filtros, acciones, acceso y auditoría).
