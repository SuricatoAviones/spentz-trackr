# 03 — Modelo de Datos

## Diagrama Entidad-Relación

```mermaid
erDiagram
    users ||--o{ expenses : "registra"
    users ||--o{ categories : "posee"
    users ||--o{ payment_sources : "posee"
    users ||--o{ exchange_rates : "ajusta"
    users ||--o{ expense_receipts : "adjunta"
    users ||--o{ admin_actions : "ejecuta (admin)"

    categories ||--o{ expenses : "clasifica"
    payment_sources ||--o{ expenses : "origina"

    expenses ||--o{ expense_receipts : "tiene"
    admin_actions }o--o| users : "apunta (target morph)"

    users {
        bigint id PK
        varchar name
        varchar email UK
        boolean is_admin "default false"
        timestamp suspended_at "nullable"
        varchar password
        varchar default_display_currency "usd | usdt"
        timestamp email_verified_at
        timestamps
    }

    categories {
        bigint id PK
        bigint user_id FK
        varchar name
        varchar icon
        varchar color
        boolean is_system
        timestamps
    }

    payment_sources {
        bigint id PK
        bigint user_id FK
        varchar name
        varchar icon
        varchar color
        boolean is_system
        timestamps
    }

    expenses {
        bigint id PK
        bigint user_id FK
        bigint category_id FK
        bigint payment_source_id FK
        enum currency "usd | ves | usdt"
        decimal amount "14,2"
        decimal exchange_rate "14,4 nullable, solo ves"
        decimal usd_amount "14,2 calculado"
        decimal usdt_amount "14,2 calculado"
        varchar description
        text note "nullable"
        date spent_at
        timestamps
    }

    expense_receipts {
        bigint id PK
        bigint expense_id FK
        varchar path
        varchar original_name
        timestamps
    }

    exchange_rates {
        bigint id PK
        bigint user_id FK "nullable, manual por usuario"
        enum source "api | manual | seed"
        decimal rate "14,4"
        varchar provider "dolarapi | user"
        date rate_date
        unique index "user_id + rate_date + source"
        timestamps
    }

    admin_actions {
        bigint id PK
        bigint admin_id FK "users, cascade"
        varchar action
        varchar target_type "nullable, morph"
        bigint target_id "nullable, morph"
        json details "nullable"
        timestamps
    }
```

## Entidades y reglas

### `users`
- El email es único. `default_display_currency` define la moneda principal del dashboard (`usd` por defecto).
- `is_admin` (bool, default `false`) marca a los administradores; se crea con `php artisan admin:create` (credenciales en `.env`: `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`).
- `suspended_at` (timestamp, nullable): si está seteado el usuario está suspendido; el middleware `EnsureUserNotSuspended` (grupo web) cierra su sesión y responde 403 en toda la app (incluido el panel admin). El admin no puede suspenderse a sí mismo.
- El resto de usuarios son autónomos y solo ven sus datos (scoping por `user_id` en todas las consultas). Solo los administradores acceden al panel `/admin` (middleware `admin` → 403 para el resto) y pueden ver/editar/eliminar cuentas.

### `categories` (tipo de gasto)
- Pertenece a un usuario. `is_system` marca las creadas por defecto (no impedir editar nombre, pero sí marcar para restauración).
- Al eliminar: si tiene gastos asociados, se impide (o se pide reasignar antes).
- Por defecto al registrarse: Alimentación, Transporte, Servicios, Salud, Ocio, Ropa, Educación, Otros.

### `payment_sources` (origen del gasto)
- Pertenece a un usuario. Valores por defecto: **Binance, Bancos, Wallets, Efectivo**.
- El usuario puede agregar orígenes propios (p. ej., "Zelle", "Pago móvil").

### `expenses`
- **Moneda de la transacción** (`currency`): `usd`, `ves`, `usdt`.
- **`exchange_rate`**: obligatoria y > 0 cuando `currency = ves`. Guarda la tasa usada **al momento de la transacción** (no la del día actual), para que los reportes históricos sean exactos.
- **`usd_amount`**: equivalente en USD calculado al guardar:
  - `usd` → `amount`
  - `usdt` → `amount` (referencia 1:1)
  - `ves` → `amount / exchange_rate`
- **`usdt_amount`**: equivalente en USDT calculado al guardar:
  - `usdt` → `amount`
  - `usd` → `amount` (referencia 1:1)
  - `ves` → `amount / exchange_rate`
- **`spent_at`** (fecha, no timestamp): el usuario decide el día del gasto; los reportes agrupan por esta fecha.
- **Descripción**: corta y obligatoria. **Nota**: texto libre opcional.

### `expense_receipts`
- Uno o varios comprobantes por gasto (hasMany). Se guardan en `storage/app/public/receipts/...` y se sirven por el disco público (usuario) o por `GET /admin/expenses/receipts/{receipt}` (admin, inline).
- Al eliminar el gasto, se eliminan los archivos.

### `exchange_rates`
- **Registro por día y por fuente**: un día puede tener tasa `api` (BCV) y una sobrescritura `manual` del usuario.
- `user_id` null ⇒ tasa global de la API; `user_id` set ⇒ ajuste manual del usuario (su "tasa del día").
- La consulta de "tasa del día" para un usuario: última tasa `manual` del usuario; si no existe, última tasa `api`.
- Se conserva histórico para auditoría y para que gastos antiguos conserven su tasa.

### `admin_actions` (auditoría del panel)
- Registra cada acción de los administradores: `admin_id` (FK a `users`, `cascadeOnDelete`), `action` (ej. `user.suspended`, `expense.deleted`, `rate.updated`), `target` morph (usuario, gasto, categoría, origen, tasa o comprobante) y `details` JSON opcional.
- Se crea con `AdminAction::record(action, target?, details?)`; la lista completa de acciones está en `docs/08-panel-admin.md`.
- El registro persiste aunque se elimine el objetivo (el morph no tiene FK).

## Índices recomendados

| Tabla | Índice | Motivo |
|---|---|---|
| `expenses` | `(user_id, spent_at)` | Listados por usuario y período |
| `expenses` | `(user_id, category_id)` | Filtro/reportes por categoría |
| `expenses` | `(user_id, payment_source_id)` | Filtro/reportes por origen |
| `expenses` | `(user_id, currency)` | Filtro por moneda |
| `exchange_rates` | `(user_id, rate_date, source)` | Tasa del día única |

## Notas sobre MySQL
- Motor **InnoDB**, charset **utf8mb4** (soporta emojis de íconos y acentos).
- `DECIMAL(14,2)` para montos y `DECIMAL(14,4)` para tasas (nunca `FLOAT`).
- Conversión: crear un cast/accessor `Amount` para montos, y formatear con `number_format` según locale.