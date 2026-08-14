# 03 — Modelo de Datos

## Diagrama Entidad-Relación

```mermaid
erDiagram
    users ||--o{ expenses : "registra"
    users ||--o{ categories : "posee"
    users ||--o{ payment_sources : "posee"
    users ||--o{ exchange_rates : "ajusta"
    users ||--o{ expense_receipts : "adjunta"

    categories ||--o{ expenses : "clasifica"
    payment_sources ||--o{ expenses : "origina"

    expenses ||--o{ expense_receipts : "tiene"

    users {
        bigint id PK
        varchar name
        varchar email UK
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
```

## Entidades y reglas

### `users`
- El email es único. `default_display_currency` define la moneda principal del dashboard (`usd` por defecto).
- Sin roles: cada usuario es autónomo y solo ve sus datos (scoping por `user_id` en todas las consultas).

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
- Una imagen por gasto (v1). Se guarda en `storage/app/public/receipts/...` y se sirve por el disco público.
- Al eliminar el gasto, se elimina el archivo.

### `exchange_rates`
- **Registro por día y por fuente**: un día puede tener tasa `api` (BCV) y una sobrescritura `manual` del usuario.
- `user_id` null ⇒ tasa global de la API; `user_id` set ⇒ ajuste manual del usuario (su "tasa del día").
- La consulta de "tasa del día" para un usuario: última tasa `manual` del usuario; si no existe, última tasa `api`.
- Se conserva histórico para auditoría y para que gastos antiguos conserven su tasa.

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