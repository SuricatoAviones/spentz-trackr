# 03 — Modelo de Datos

Motor: **SQLite** (dev/instalable), **MySQL 8** o **PostgreSQL 12+** (prod). InnoDB /
utf8mb4 en MySQL. Montos en `DECIMAL(14,2)`, tasas en `DECIMAL(14,4)` — nunca `FLOAT`.

## Diagrama Entidad-Relación

```mermaid
erDiagram
    users ||--o{ expenses : registra
    users ||--o{ incomes : registra
    users ||--o{ categories : posee
    users ||--o{ payment_sources : posee
    users ||--o{ savings_goals : posee
    users ||--o{ recurring_payments : posee
    users ||--o{ exchange_rates : "ajusta (manual)"
    users ||--o{ admin_actions : "ejecuta (admin)"

    categories ||--o{ expenses : clasifica
    categories ||--o{ incomes : clasifica
    categories ||--o{ recurring_payments : clasifica
    payment_sources ||--o{ expenses : origina

    expenses ||--o{ expense_items : "tiene (mixto)"
    expenses ||--o{ expense_receipts : adjunta
    incomes ||--o{ income_receipts : adjunta

    savings_goals ||--o{ savings_contributions : recibe
    incomes ||--o{ savings_contributions : "financia (opcional)"

    admin_actions }o--o| users : "target (morph)"

    users {
        bigint id PK
        string name
        string email UK
        boolean is_admin "default false"
        timestamp suspended_at "nullable"
        timestamp email_verified_at "nullable"
        string default_display_currency "usd | usdt"
        string tracking_type "expenses | income | both"
        decimal monthly_budget "nullable"
        decimal min_commission "default 14.00"
        decimal commission_rate "default 0.30 (porcentaje)"
        string locale "es | en, nullable"
        text two_factor_secret "nullable"
        text two_factor_recovery_codes "nullable"
        timestamp two_factor_confirmed_at "nullable"
        timestamps
    }

    categories {
        bigint id PK
        bigint user_id FK "cascade"
        string name
        string icon
        string color
        string type "expense | income, default expense"
        decimal budget "nullable (presupuesto mensual de la categoría)"
        boolean is_system "default false"
        timestamps
    }

    payment_sources {
        bigint id PK
        bigint user_id FK "cascade"
        string name
        string icon
        string color
        boolean is_system
        timestamps
    }

    expenses {
        bigint id PK
        bigint user_id FK "cascade"
        bigint category_id FK "restrict"
        bigint payment_source_id FK "restrict"
        enum currency "usd | ves | usdt"
        enum payment_method "pago_movil | transferencia, nullable (solo ves)"
        decimal commission "nullable (solo ves; monto base va en amount)"
        decimal amount "base, sin comisión"
        decimal exchange_rate "nullable, solo ves"
        string rate_provider "bcv | paralelo | user | custom, nullable"
        decimal usd_amount "congelado = (amount+commission)/rate + items"
        decimal usdt_amount "congelado"
        string description
        text note "nullable"
        date spent_at
        timestamps
    }

    expense_items {
        bigint id PK
        bigint expense_id FK "cascade"
        enum currency "usd | ves | usdt"
        decimal amount
        decimal exchange_rate "nullable, solo ves"
        decimal usd_amount "congelado"
        decimal usdt_amount "congelado"
        timestamps
    }

    incomes {
        bigint id PK
        bigint user_id FK "cascade"
        bigint category_id FK "restrict"
        enum currency "usd | ves | usdt"
        decimal amount
        decimal exchange_rate "nullable, solo ves"
        string rate_provider "nullable"
        decimal usd_amount "congelado"
        decimal usdt_amount "congelado"
        string description
        text note "nullable"
        date received_at
        timestamps
    }

    expense_receipts {
        bigint id PK
        bigint expense_id FK "cascade"
        string path
        string original_name
        timestamps
    }
    income_receipts {
        bigint id PK
        bigint income_id FK "cascade"
        string path
        string original_name
        timestamps
    }

    exchange_rates {
        bigint id PK
        bigint user_id FK "nullable; null = tasa global de la API"
        enum source "api | manual | seed"
        string provider "bcv | paralelo | user (default dolarapi)"
        decimal rate
        date rate_date
        timestamps
    }

    savings_goals {
        bigint id PK
        bigint user_id FK "cascade"
        string name
        decimal target_amount
        enum currency "usd | ves | usdt"
        decimal exchange_rate "nullable"
        decimal target_usd_amount "congelado"
        string icon
        string color
        date deadline "nullable"
        timestamp achieved_at "nullable"
        text note "nullable"
        timestamps
    }

    savings_contributions {
        bigint id PK
        bigint savings_goal_id FK "cascade"
        bigint income_id FK "nullable, nullOnDelete"
        decimal amount
        enum currency "usd | ves | usdt"
        decimal exchange_rate "nullable"
        decimal usd_amount "congelado"
        decimal usdt_amount "congelado"
        date contributed_at
        text note "nullable"
        timestamps
    }

    recurring_payments {
        bigint id PK
        bigint user_id FK "cascade"
        bigint category_id FK "nullable, nullOnDelete"
        string name
        decimal amount
        enum currency "usd | ves | usdt"
        decimal exchange_rate "nullable"
        decimal usd_amount "congelado"
        decimal usdt_amount "congelado"
        enum frequency "daily | weekly | monthly | quarterly | yearly"
        date next_due_date
        date last_paid_at "nullable"
        string icon
        string color
        boolean active "default true"
        text note "nullable"
        timestamps
    }

    admin_actions {
        bigint id PK
        bigint admin_id FK "users, cascade"
        string action "ej. user.suspended, expense.deleted, rate.updated"
        string target_type "nullable, morph"
        bigint target_id "nullable, morph"
        json details "nullable"
        timestamps
    }
```

Tablas de infraestructura no dibujadas: `password_reset_tokens`, `sessions`, `cache`,
`jobs`/`job_batches`/`failed_jobs`, `personal_access_tokens` (Sanctum), `passkeys`
(Fortify).

## Reglas por entidad

### `users`
- `email` único. `default_display_currency` ∈ `{usd, usdt}` (moneda principal del dashboard).
- `tracking_type` ∈ `{expenses, income, both}` — elige qué módulos ve el usuario;
  lo aplica el middleware `EnsureTrackingFeature`.
- `is_admin` (bool) — **no** está en `$fillable`; se asigna solo vía `forceFill` en
  acciones de confianza. `suspended_at` bloquea al usuario en toda la app.
- `min_commission` / `commission_rate` — pisos por defecto de la comisión Bs (editable
  en Ajustes). `monthly_budget` — presupuesto global mensual (nullable).
- `two_factor_*` (Fortify), `locale` (nullable → resuelve a `APP_LOCALE`).

### `categories`
- `type` ∈ `{expense, income}` separa categorías de gasto e ingreso.
- `budget` (nullable) — presupuesto mensual de esa categoría, con barra de progreso en el
  dashboard.
- `is_system` marca las creadas por defecto al registrarse.
- Eliminar una categoría con gastos/ingresos asociados se **bloquea** (FK `restrict` +
  chequeo en el controlador).

### `expenses`
- **Conversión congelada al escribir** (`ExpenseConversionService` + Actions
  `app/Actions/Expenses/*`): `usd_amount` / `usdt_amount` nunca se recalculan con la tasa
  actual.
  - `usd` → `amount`; `usdt` → `amount` (1:1); `ves` → `(amount + commission) / exchange_rate`.
  - Más el equivalente USD/USDT de cada `expense_item`.
- `payment_method` y `commission` solo aplican a `currency = ves`. `amount` guarda **la
  base**; la comisión va en su columna. Regla: `commission = max(min_commission, amount × commission_rate%)`
  (precalculada en el formulario, editable).
- `exchange_rate` obligatoria y > 0 cuando `currency = ves` (o se toma la tasa del día).
- `spent_at` es **fecha** (no timestamp); los reportes agrupan por ella. Comparar siempre
  con `whereDate()` (el cast `date` guarda `Y-m-d H:i:s`).

### `expense_items` (gasto mixto)
- Porciones adicionales de un gasto en otra moneda. La porción principal vive en el
  `expense`. Cada ítem congela su propio `usd_amount`/`usdt_amount`.

### `incomes`
- Análogo a `expenses` pero sin origen, comisión ni ítems. `received_at` es la fecha.

### `exchange_rates`
- `user_id` null ⇒ tasa global de la API; `user_id` set ⇒ override **manual** del usuario
  para ese día.
- `source` ∈ `{api, manual, seed}` (el enum permite `seed`; en la práctica se usan
  `api` y `manual`). `provider` en la práctica: `bcv`, `paralelo` (API) o `user` (manual).
- Consulta de "tasa del día" para un usuario: su última `manual` de hoy; si no, la última
  `api` (`ExchangeRateService::rateForUser`, cache 60 s).
- Se conserva el histórico para auditoría y para que los gastos antiguos mantengan su tasa.

### `savings_goals` / `savings_contributions`
- La meta congela `target_usd_amount`. Cada aporte congela su equivalente USD/USDT.
- Un aporte puede referenciar un `income` (nullable); si el ingreso se borra, el aporte
  queda con `income_id = null`.
- `achieved_at` se setea/limpia automáticamente al comparar la suma de aportes con el
  objetivo (`SavingsGoalController::refreshAchievement`).

### `recurring_payments`
- No generan gastos automáticamente: `markPaid` solo actualiza `last_paid_at` y avanza
  `next_due_date` según `frequency` (`App\Enums\Frequency::advance`).
- Un pago está "vencido" si `active` y `next_due_date <= hoy`.

### `admin_actions`
- Registra cada acción de administradores. `target` es un morph sin FK, así que el
  registro persiste aunque se elimine el objetivo.
- Se crea con `AdminAction::record($action, $target?, $details?)`. Lista completa de
  acciones en `docs/08-panel-admin.md`.

## Índices

Cada tabla de negocio indexa `(user_id, <fecha>)`, `(user_id, category_id)` y
`(user_id, currency)` para los listados y reportes. `exchange_rates` tiene un único
`(user_id, rate_date, source, provider)`.
