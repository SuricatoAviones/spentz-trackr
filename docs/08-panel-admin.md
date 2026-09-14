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
- **Backup** (`POST /admin/backup`): descarga un JSON con todas las tablas de datos de
  usuario. Qué tablas y en qué orden lo decide `AppSupportBackupSchema`, **compartido con
  la restauración**: cuando eran dos listas separadas se desincronizaban y el fichero llegó a
  omitir ingresos, metas y tarjetas sin que nada avisara. Queda registrado en auditoría.

  **Al añadir una tabla con datos de usuario, añádela a `BackupSchema`.** Una copia que
  pierde datos en silencio es peor que no tener ninguna.

- **Restauración** (`php artisan backup:restore fichero.json`): la otra mitad del backup.

  | Opción | Qué hace |
  |---|---|
  | *(ninguna)* | Se niega a correr si ya hay datos: los ids chocarían |
  | `--fresh` | Vacía las tablas primero, en orden inverso al de claves foráneas |
  | `--force` | No preguntar en producción |

  Todo va en una transacción: si algo falla a mitad, la base queda como estaba. También
  rechaza ficheros que no son copias de Spentz Trackr y los de un formato más nuevo que el
  que entiende esta versión, antes de tocar nada.

  **Dos límites, avisados en voz alta y no escondidos:**

  1. La copia **no lleva hashes de contraseña** (ni secretos 2FA), porque un fichero que se
     descarga y se manda por correo no debe ser también un volcado de credenciales. Al
     restaurar, cada usuario recibe una clave aleatoria que nadie conoce y debe recuperar la
     suya. Para el administrador está `php artisan admin:create`.
  2. Tampoco lleva **los ficheros** de los comprobantes, solo sus filas. Cópialos aparte
     desde `storage/app/private`.

  Para una recuperación byte a byte —incluidas las contraseñas— usa un volcado de la base de
  datos (`mysqldump`, `pg_dump`) más una copia de `storage/`. Este backup es una exportación
  de datos con la que reconstruir la instancia, no un sustituto de aquello.

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
