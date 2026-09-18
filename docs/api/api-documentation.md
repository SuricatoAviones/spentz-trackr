# API REST - Spentz Trackr

> **Interactiva (OpenAPI/Swagger):** la documentación generada con Scramble está disponible en `/api/v1` (UI interactiva con "Try it") y su spec en `/api/v1.json`. Este documento es la referencia REST detallada, mantenida a mano.

## Base URL
`https://your-domain.com/api/v1`

## Authentication
All API endpoints require authentication via **Personal Access Token** sent in the `Authorization` header:

```
Authorization: Bearer {token}
```

### Obtaining a Token
1. **Register**: `POST /api/v1/auth/register`
2. **Login**: `POST /api/v1/auth/login`
3. **Token is returned** in the login response

### Revoking a Token
`DELETE /api/v1/auth/logout` - Revokes the current user's token

### User Permissions
- Users can only access their own data
- Suspended users are rejected with `403` (both at login and on every request)

---

## Versioning
The API is versioned at `/api/v1`. Future versions will be at `/api/v2`, `/api/v3`, etc.

---

## Error Responses
All error responses follow this format:

```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field": ["Error message"]
    }
}
```

### HTTP Status Codes
| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 204 | No Content |
| 400 | Bad Request - Validation errors |
| 401 | Unauthorized - Invalid/missing token |
| 403 | Forbidden - Insufficient permissions |
| 422 | Validation Unprocessable Entity |
| 429 | Too Many Requests |
| 500 | Internal Server Error |
| 503 | Service Unavailable |

---

## Endpoints

### Authenticación

#### Register
`POST /api/v1/auth/register`

**Request:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "securepassword",
    "password_confirmation": "securepassword"
}
```

**Response (201):**
```json
{
    "success": true,
    "data": {
        "token": "tkn_abc123",
        "token_id": 1,
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "is_admin": false,
            "locale": "es",
            "tracking_type": "expenses",
            "default_display_currency": "usd"
        }
    },
    "message": "Usuario registrado correctamente"
}
```

#### Login
`POST /api/v1/auth/login`

**Request:**
```json
{
    "email": "john@example.com",
    "password": "securepassword"
}
```

Opcional: `"device_name"` para etiquetar el token.

**Response:** Same as Register (message: `"Inicio de sesión exitoso"`)

#### Logout
`DELETE /api/v1/auth/logout`

**Response:**
```json
{
    "success": true,
    "message": "Sesión cerrada correctamente"
}
```

#### Get Current User
`GET /api/v1/auth/me`

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "is_admin": false,
        "locale": "es",
        "tracking_type": "expenses",
        "default_display_currency": "usd",
        "created_at": "2024-01-01"
    }
}
```

---

### Gastos (Expenses)

#### List Expenses
`GET /api/v1/expenses`

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Buscar en descripción o nota |
| `currency` | string | Filtrar por USD, VES, o USDT |
| `category_id` | integer | Filtrar por categoría |
| `payment_source_id` | integer | Filtrar por origen de pago |
| `from` | date | Fecha inicial (formato YYYY-MM-DD) |
| `to` | date | Fecha final (formato YYYY-MM-DD) |

**Response (200):**
```json
{
    "success": true,
    "data": {
        "expenses": [
            {
                "id": 1,
                "description": "Mercado semanal",
                "note": "Supermercado",
                "amount": 150.50,
                "currency": "ves",
                "exchange_rate": "28.5000",
                "rate_provider": "bcv",
                "usd_amount": 5.28,
                "usdt_amount": 5.28,
                "spent_at": "2024-01-15",
                "category": {
                    "id": 1,
                    "name": "Alimentación",
                    "icon": "shopping-cart",
                    "color": "#10B981"
                },
                "source": {
                    "id": 1,
                    "name": "Efectivo",
                    "icon": "banknote",
                    "color": "#F59E0B"
                },
                "has_receipt": true,
                "receipts": [
                    {
                        "id": 1,
                        "url": "/storage/receipts/comprobante.pdf",
                        "original_name": "receipt.pdf"
                    }
                ]
            }
        ],
        "filters": {
            "search": "",
            "currency": "",
            "category_id": null,
            "payment_source_id": null,
            "from": null,
            "to": null
        },
        "totals": {
            "usd": 1250.50,
            "usdt": 1250.50,
            "byCurrency": {
                "usd": 1250.50,
                "ves": 0.00,
                "usdt": 1250.50
            }
        },
        "categories": [
            {"id": 1, "name": "Alimentación", "icon": "shopping-cart", "color": "#10B981"},
            {"id": 2, "name": "Transporte", "icon": "car", "color": "#3B82F6"}
        ],
        "sources": [
            {"id": 1, "name": "Efectivo", "icon": "banknote", "color": "#F59E0B"},
            {"id": 2, "name": "Binance", "icon": "bitcoin", "color": "#EC4899"}
        ]
    }
}
```

#### Create Expense
`POST /api/v1/expenses`

**Request:**
```json
{
    "category_id": 1,
    "payment_source_id": 1,
    "currency": "usd",
    "amount": 25.50,
    "description": "Mercado semanal",
    "spent_at": "2024-01-15"
}
```

**For VES currency (requires exchange rate):**
```json
{
    "category_id": 1,
    "payment_source_id": 1,
    "currency": "ves",
    "amount": 140.00,
    "description": "Pasaje",
    "exchange_rate": 28.5,
    "rate_provider": "bcv"
}
```

**Response (201):**
```json
{
    "success": true,
    "data": {
        "id": 2,
        "message": "Gasto creado correctamente"
    }
}
```

#### Get Expense
`GET /api/v1/expenses/{id}`

**Response:** Expense object as shown in list

#### Update Expense
`PUT /api/v1/expenses/{id}`

**Request:**
```json
{
    "amount": 30.00,
    "exchange_rate": 29.5,
    "rate_provider": "paralelo"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 2,
        "message": "Gasto actualizado correctamente"
    }
}
```

#### Delete Expense
`DELETE /api/v1/expenses/{id}`

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 2,
        "message": "Gasto eliminado correctamente"
    }
}
```

---

### Ingresos (Incomes)

Mismo comportamiento que gastos, pero con categorías/sources de tipo `income` y respuesta en llave `incomes`.

#### List Incomes
`GET /api/v1/incomes`

Iguales query parameters que gastos. **Response:** `data.incomes` + `filters` + `totals` + `categories` (tipo income) + `sources`.

#### Create Income
`POST /api/v1/incomes`

Mismo body que `POST /api/v1/expenses`. **Response (201):** `{ "success": true, "data": { "id": 2 }, "message": "Ingreso creado correctamente" }`

#### Get Income
`GET /api/v1/incomes/{id}`

#### Update Income
`PUT /api/v1/incomes/{id}`

#### Delete Income
`DELETE /api/v1/incomes/{id}`

---

### Categorías

#### List Categories
`GET /api/v1/categories`

**Response:**
```json
{
    "success": true,
    "data": {
        "categories": [
            {
                "id": 1,
                "name": "Alimentación",
                "icon": "shopping-cart",
                "color": "#10B981",
                "type": "expense",
                "budget": null,
                "is_system": true,
                "expenses_count": 5,
                "incomes_count": 0,
                "total_usd": 125.50,
                "income_total_usd": 0.00,
                "monthly_spent": 25.00
            }
        ],
        "monthlyCount": 5,
        "monthlyIncomeCount": 0
    }
}
```

#### Create Category
`POST /api/v1/categories`

**Request (gasto):**
```json
{
    "name": "Ocio",
    "icon": "gamepad-2",
    "color": "#8B5CF6",
    "type": "expense",
    "budget": 100
}
```

`type` acepta `expense` | `income`. El presupuesto solo se conserva en categorías de gasto.

**Response (201):**
```json
{
    "success": true,
    "data": {
        "id": 3
    },
    "message": "Categoría creada correctamente"
}
```

#### Update Category
`PUT /api/v1/categories/{id}`

**Request:**
```json
{
    "name": "Entretenimiento",
    "color": "#A855F7"
}
```

#### Delete Category
`DELETE /api/v1/categories/{id}`

**Error if has expenses:**
```json
{
    "success": false,
    "message": "No se puede eliminar la categoría porque tiene gastos asociados"
}
```

---

### Orígenes de Pago (Payment Sources)

#### List Sources
`GET /api/v1/sources`

**Response:** Array of source objects

#### Create Source
`POST /api/v1/sources`

**Request:**
```json
{
    "name": "Zelle",
    "icon": "zap",
    "color": "#8B5CF6"
}
```

#### Update Source
`PUT /api/v1/sources/{id}`

#### Delete Source
`DELETE /api/v1/sources/{id}`

**Error if has expenses:**
```json
{
    "success": false,
    "message": "No se puede eliminar el origen porque tiene gastos asociados"
}
```

---

### Metas de Ahorro (Savings Goals)

El objetivo se **congela en USD** al guardarlo: una meta en Bs guarda la tasa con la que se
creó y no se recalcula con la de hoy. Lo mismo con cada aporte.

La meta se marca (y se desmarca) como cumplida sola, según la suma de los aportes: no hay
endpoint para "lograrla" a mano.

#### List Savings Goals
`GET /api/v1/savings-goals`

**Query params:** `achieved` (`1` solo las cumplidas, `0` solo las pendientes).

**Response (200):**
```json
{
    "success": true,
    "data": {
        "goals": [
            {
                "id": 1,
                "name": "Viaje",
                "target_amount": "1000.00",
                "currency": "usd",
                "exchange_rate": null,
                "target_usd_amount": "1000.00",
                "icon": "piggy-bank",
                "color": "#10B981",
                "deadline": "2026-12-31",
                "note": null,
                "achieved_at": null,
                "saved": 250,
                "percent": 25,
                "contributions": [
                    {
                        "id": 4,
                        "amount": "250.00",
                        "currency": "usd",
                        "usd_amount": "250.00",
                        "income_id": null,
                        "contributed_at": "2026-09-01",
                        "note": null
                    }
                ]
            }
        ]
    }
}
```

#### Create Savings Goal
`POST /api/v1/savings-goals`

| Campo | Tipo | Obligatorio | Notas |
|-------|------|-------------|-------|
| `name` | string(100) | sí | |
| `target_amount` | numeric > 0 | sí | En la moneda de `currency` |
| `currency` | `usd` \| `ves` \| `usdt` | sí | |
| `exchange_rate` | numeric > 0 | solo si `currency=ves` | Con ella se congela el objetivo en USD |
| `icon` | string(50) | no | |
| `color` | string(20) | no | |
| `deadline` | date | no | Hoy o posterior |
| `note` | string(2000) | no | |

**Response (201):** `{ "success": true, "data": { "id": 7 }, "message": "Meta de ahorro creada." }`

#### Get Savings Goal
`GET /api/v1/savings-goals/{id}` — la meta con sus aportes, bajo `data.goal`.

#### Update Savings Goal
`PUT /api/v1/savings-goals/{id}` — mismos campos que al crear. Subir el objetivo puede
"desconseguir" una meta ya cumplida: la respuesta trae `achieved_at` ya recalculado.

#### Delete Savings Goal
`DELETE /api/v1/savings-goals/{id}` — borra también sus aportes.

#### Add Contribution
`POST /api/v1/savings-goals/{id}/contributions`

| Campo | Tipo | Obligatorio | Notas |
|-------|------|-------------|-------|
| `amount` | numeric > 0 | sí | |
| `currency` | `usd` \| `ves` \| `usdt` | sí | |
| `exchange_rate` | numeric > 0 | solo si `currency=ves` | |
| `contributed_at` | date | sí | Hoy o anterior |
| `income_id` | integer | no | Ingreso propio del que sale el aporte |
| `note` | string(2000) | no | |

**Response (201):** `{ "success": true, "data": { "id": 12 }, "message": "Aporte registrado." }`

#### Delete Contribution
`DELETE /api/v1/savings-goals/{id}/contributions/{contribution}`

Devuelve `404` si el aporte no es de esa meta.

---

### Pagos Recurrentes (Recurring Payments)

Un recurrente es **un recordatorio de lo que toca pagar, no el movimiento**: marcarlo como
pagado no crea ningún gasto. Quien quiera registrarlo crea el gasto aparte.

#### List Recurring Payments
`GET /api/v1/recurring-payments`

**Query params:** `active` (`1`/`0`), `due` (`1` solo los vencidos y activos).
Los vencidos van primero; después, por fecha de vencimiento ascendente.

**Response (200):**
```json
{
    "success": true,
    "data": {
        "payments": [
            {
                "id": 3,
                "name": "Internet",
                "amount": "30.00",
                "currency": "usd",
                "usd_amount": "30.00",
                "frequency": "monthly",
                "next_due_date": "2026-09-17",
                "last_paid_at": "2026-08-17",
                "category_id": 2,
                "icon": "wifi",
                "color": "#10B981",
                "active": true,
                "note": null,
                "due": true
            }
        ]
    }
}
```

#### Create Recurring Payment
`POST /api/v1/recurring-payments`

| Campo | Tipo | Obligatorio | Notas |
|-------|------|-------------|-------|
| `name` | string(100) | sí | |
| `amount` | numeric > 0 | sí | |
| `currency` | `usd` \| `ves` \| `usdt` | sí | |
| `exchange_rate` | numeric > 0 | solo si `currency=ves` | Congela el equivalente en USD |
| `frequency` | `daily` \| `weekly` \| `monthly` \| `quarterly` \| `yearly` | sí | |
| `next_due_date` | date | sí | |
| `category_id` | integer | no | Categoría propia |
| `icon`, `color`, `note` | string | no | |
| `active` | boolean | no | Por defecto activo |

#### Get / Update / Delete
`GET|PUT|DELETE /api/v1/recurring-payments/{id}`. En `PUT`, enviar `active: false` lo pausa
y `active: true` lo reanuda. La respuesta del `PUT` trae el recurrente ya presentado.

#### Mark as Paid
`POST /api/v1/recurring-payments/{id}/pay`

Pone `last_paid_at` a hoy y adelanta `next_due_date` un periodo según `frequency`.
**No crea ningún gasto.**

**Response (200):**
```json
{
    "success": true,
    "data": { "payment": { "id": 3, "next_due_date": "2026-10-18", "last_paid_at": "2026-09-18", "due": false } },
    "message": "Pago registrado como pagado."
}
```

---

### Tarjetas de Crédito (Credit Cards)

Cada tarjeta crea (y mantiene) el **origen de pago** que la representa, así que sus consumos
son gastos normales: se registran con `POST /api/v1/expenses` indicando el
`payment_source_id` de la tarjeta. No hay tabla de movimientos paralela.

Dos cosas que conviene entender antes de pintar una pantalla con esto:

- **`balance.projected_used` es una estimación cuando `balance.is_estimate` es `true`.** El
  ancla es el último corte (lo que dice el banco); encima se suman los gastos registrados y
  se restan los abonos, y esos gastos pueden estar incompletos. No la presentes como el
  saldo del banco.
- **Un abono no es un gasto.** Pagar la tarjeta mueve dinero del bolsillo a la deuda; si
  además contara como gasto, cada consumo se contaría dos veces.

Los movimientos en una moneda distinta a la de la tarjeta se cuentan en
`balance.foreign_movements` pero **no se suman**: convertirlos con la tasa de hoy rompería
el congelado.

#### List Credit Cards
`GET /api/v1/credit-cards`

**Query params:** `active` (`1`/`0`).

**Response (200):**
```json
{
    "success": true,
    "data": {
        "cards": [
            {
                "id": 1,
                "bank": "Banesco",
                "name": "Visa Clásica",
                "last_four": "4321",
                "brand": "visa",
                "currency": "ves",
                "credit_limit": "50000.00",
                "cut_day": 15,
                "due_day": 5,
                "annual_interest_rate": "60.00",
                "minimum_payment_rate": "5.00",
                "icon": "credit-card",
                "color": "#8B5CF6",
                "active": true,
                "note": null,
                "payment_source_id": 9,
                "cycle": {
                    "last_cut_date": "2026-09-15",
                    "next_cut_date": "2026-10-15",
                    "next_due_date": "2026-11-05",
                    "days_to_cut": 27
                },
                "balance": {
                    "closing": 3000,
                    "charges_since_cut": 450,
                    "payments_since_cut": 0,
                    "projected_used": 3450,
                    "available": 46550,
                    "usage_percent": 7,
                    "is_estimate": true,
                    "foreign_movements": 0
                },
                "usd": { "projected_used": 115 }
            }
        ]
    }
}
```

#### Create Credit Card
`POST /api/v1/credit-cards`

| Campo | Tipo | Obligatorio | Notas |
|-------|------|-------------|-------|
| `bank` | string(100) | sí | |
| `name` | string(100) | sí | |
| `last_four` | 4 dígitos | no | Solo los últimos cuatro; el número completo no se guarda |
| `brand` | `visa` \| `mastercard` \| `amex` \| `other` | no | |
| `currency` | `usd` \| `ves` \| `usdt` | sí | Moneda de la línea de crédito |
| `credit_limit` | numeric > 0 | sí | |
| `cut_day` | 1-31 | sí | Se recorta al último día real del mes |
| `due_day` | 1-31 | sí | |
| `annual_interest_rate` | numeric | no | |
| `minimum_payment_rate` | numeric 0-100 | no | |
| `icon`, `color`, `note` | string | no | |
| `active` | boolean | no | |

**Response (201):** `{ "success": true, "data": { "id": 1 }, "message": "Tarjeta creada." }`
con cabecera `Location`.

#### Get Credit Card
`GET /api/v1/credit-cards/{id}`

Ficha completa: `data.card` con lo del listado más `statements` y `payments`, y
`data.recent_charges` con los 25 últimos consumos cargados contra su origen de pago.

#### Update / Delete Credit Card
`PUT|DELETE /api/v1/credit-cards/{id}`

Al actualizar, el origen de pago se renombra con la tarjeta. Al borrarla se van sus cortes y
abonos, pero **los gastos hechos con ella se conservan**: son movimientos reales del
historial. El origen solo desaparece si nunca se usó.

#### Create Statement (corte)
`POST /api/v1/credit-cards/{id}/statements`

El corte es el saldo que dice el banco en la fecha de cierre — el ancla de todo el cálculo.

| Campo | Tipo | Obligatorio | Notas |
|-------|------|-------------|-------|
| `cut_date` | date | sí | Hoy o anterior |
| `closing_balance` | numeric >= 0 | sí | En la moneda de la tarjeta |
| `due_date` | date | no | Si falta, se deduce del `due_day` de la tarjeta |
| `minimum_payment` | numeric >= 0 | no | |
| `exchange_rate` | numeric > 0 | solo si la tarjeta es en Bs | Congela el corte en USD |
| `note` | string(2000) | no | |

#### Delete Statement
`DELETE /api/v1/credit-cards/{id}/statements/{statement}` — `404` si el corte no es de esa
tarjeta.

#### Create Payment (abono)
`POST /api/v1/credit-cards/{id}/payments`

| Campo | Tipo | Obligatorio | Notas |
|-------|------|-------------|-------|
| `amount` | numeric > 0 | sí | En la moneda de la tarjeta |
| `paid_at` | date | sí | Hoy o anterior |
| `credit_card_statement_id` | integer | no | Debe ser un corte de **esa** tarjeta |
| `exchange_rate` | numeric > 0 | solo si la tarjeta es en Bs | |
| `note` | string(2000) | no | |

Cuando los abonos que señalan un corte cubren su saldo, el corte se marca pagado. Un pago
parcial lo deja abierto, como hace el banco.

#### Delete Payment
`DELETE /api/v1/credit-cards/{id}/payments/{payment}` — `404` si el abono no es de esa
tarjeta.

---

### Tasas de Cambio (Exchange Rates)

#### Get Rates
`GET /api/v1/rates`

**Response:**
```json
{
    "success": true,
    "data": {
        "rates": {
            "bcv": "30.5000",
            "paralelo": "32.1000",
            "manual": "29.7500"
        }
    }
}
```

#### Save Manual Rate
`PUT /api/v1/rates`

**Request:**
```json
{
    "rate": 29.75
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "rate": "29.7500"
    },
    "message": "Tasa actualizada correctamente"
}
```

#### Sync Rates
`POST /api/v1/rates/sync`

**Response:**
```json
{
    "success": true,
    "data": null,
    "message": "Tasas sincronizadas correctamente con la API"
}
```

Devuelve `503` con `"success": false` si la API externa no responde correctamente.

---

### Dashboard

#### Get Dashboard
`GET /api/v1/dashboard`

Depende del `tracking_type` del usuario (`expenses`, `income` o `both`); la respuesta incluye las llaves `expenses` y/o `incomes` según corresponda.

**Response (`expenses`):**
```json
{
    "success": true,
    "data": {
        "mode": "expenses",
        "month": "Enero 2024",
        "expenses": {
            "totals": {
                "usd": 1250.50,
                "usdt": 1250.50,
                "byCurrency": {
                    "usd": 1250.50,
                    "ves": 0.00,
                    "usdt": 1250.50
                }
            },
            "categories": [
                {"id": 1, "name": "Alimentación", "icon": "shopping-cart", "color": "#10B981", "total": 125.50, "count": 5}
            ],
            "sources": [
                {"id": 1, "name": "Efectivo", "icon": "banknote", "color": "#F59E0B", "total": 50.00}
            ],
            "trend": [
                {"month": "2024-01", "total": 1250.50},
                {"month": "2023-12", "total": 1100.25}
            ],
            "budgets": [
                {"id": 1, "name": "Alimentación", "icon": "shopping-cart", "color": "#10B981", "amount": 500.00, "spent": 125.50}
            ],
            "recent": [
                {"id": 1, "description": "Mercado", "amount": 25.50, "currency": "usd", "usd_amount": 25.50, "spent_at": "2024-01-15", "category": {"id": 1, "name": "Alimentación", "icon": "shopping-cart", "color": "#10B981"}}
            ]
        }
    }
}
```

---

### Reportes

#### Annual Report
`GET /api/v1/reports?year=2024`

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `year` | integer | Año a reportar (default: año actual) |
| `tracking` | string | `expenses` \| `income` (default: segun usuario) |

**Response (2024):**
```json
{
    "success": true,
    "data": {
        "period": {
            "year": 2024
        },
        "byCategory": [
            {
                "category_id": 1,
                "category_name": "Alimentación",
                "category_icon": "shopping-cart",
                "category_color": "#10B981",
                "total": 125.50,
                "count": 5
            }
        ],
        "bySource": [
            {
                "source_id": 1,
                "source_name": "Efectivo",
                "total": 50.00,
                "count": 2
            }
        ],
        "totals": {
            "usd": 1250.50,
            "usdt": 1250.50,
            "ves": 3500.00,
            "count": 25
        },
        "months": [
            {
                "month": "January",
                "total": 1250.50,
                "total_ves": 35000.00,
                "count": 10
            }
        ],
        "range": {
            "year": 2024,
            "from": "2024-01-01",
            "to": "2024-12-31"
        },
        "trend": [
            {"month": "2024-01", "total": 1250.50},
            {"month": "2023-12", "total": 1100.25}
        ]
    }
}
```

#### Monthly Summary
`GET /api/v1/reports/monthly-summary?months=12`

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `months` | integer | Número de meses de tendencia (default: 12) |
| `tracking` | string | `expenses` \| `income` |

**Response:**
```json
{
    "success": true,
    "data": {
        "tracking": "expenses",
        "totals": {
            "usd": 1200.00,
            "usdt": 1200.00,
            "ves": 34000.00,
            "count": 240
        },
        "trend": [
            {"month": "2024-01", "total": 1250.50},
            {"month": "2023-12", "total": 1100.25}
        ]
    }
}
```

---

### Configuración del Usuario

#### Update Profile
`PUT /api/v1/user/profile`

**Request:**
```json
{
    "name": "John Updated"
}
```

#### Update Locale
`PUT /api/v1/user/locale`

**Request:**
```json
{
    "locale": "en"
}
```

---

## Rate Limiting

| Endpoint | Limit |
|----------|-------|
| `/api/v1/auth/login` y `/api/v1/auth/register` | 5 requests/minute per IP |
| Other endpoints | 100 requests/minute per user id (o IP si no autenticado) |

Los limiters `api` y `api.auth` están registrados en `AppServiceProvider::boot()` y aplicados desde `routes/api.php` (`throttle:api.auth`, grupo `api`).

---

## Códigos de Error Comunes

| Error | Significado | Solución |
|-------|-------------|----------|
| `401 Unauthorized` | Token inválido o expirado | Obtener nuevo token vía login |
| `403 Forbidden` | Usuario sin permisos | Verificar que es dueño de los datos |
| `422 Validation Error` | Datos inválidos | Revisar campos obligatorios y formatos |
| `429 Too Many Requests` | Límite excedido | Esperar y reintentar |
| `404 Not Found` | Recurso no existe | Verificar ID correcto |
| `503 Service Unavailable` | API externa caída | Intentar de nuevo más tarde |

---

## Ejemplos en cURL

### Login
```bash
curl -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"securepassword"}'
```

### List Expenses
```bash
curl -GET https://your-domain.com/api/v1/expenses \
  -H "Authorization: Bearer tkn_abc123"
```

### Create Expense
```bash
curl -X POST https://your-domain.com/api/v1/expenses \
  -H "Authorization: Bearer tkn_abc123" \
  -H "Content-Type: application/json" \
  -d '{"category_id":1,"payment_source_id":1,"currency":"usd","amount":25.50,"description":"Test","spent_at":"2024-01-15"}'
```

### Save Manual Rate
```bash
curl -X PUT https://your-domain.com/api/v1/rates \
  -H "Authorization: Bearer tkn_abc123" \
  -H "Content-Type: application/json" \
  -d '{"rate":29.75}'
```

### Sync Rates
```bash
curl -X POST https://your-domain.com/api/v1/rates/sync \
  -H "Authorization: Bearer tkn_abc123"
```

### Add a Savings Contribution
```bash
curl -X POST https://your-domain.com/api/v1/savings-goals/1/contributions \
  -H "Authorization: Bearer tkn_abc123" \
  -H "Content-Type: application/json" \
  -d '{"amount":50,"currency":"usd","contributed_at":"2026-09-18"}'
```

### Mark a Recurring Payment as Paid
```bash
curl -X POST https://your-domain.com/api/v1/recurring-payments/3/pay \
  -H "Authorization: Bearer tkn_abc123"
```

### Register a Credit Card Statement
```bash
curl -X POST https://your-domain.com/api/v1/credit-cards/1/statements \
  -H "Authorization: Bearer tkn_abc123" \
  -H "Content-Type: application/json" \
  -d '{"cut_date":"2026-09-15","closing_balance":3000,"exchange_rate":30}'
```

---

## Seguridad

1. **HTTPS obligatorio** - Todos los endpoints deben usar HTTPS
2. **Tokens de larga duración** - Los tokens persisten hasta serem revocados
3. **Rate limiting** - Protege contra ataques de fuerza bruta
4. **Validación de entrada** - Todos los inputs son validados en servidor
5. **CORS** - Configurar headers CORS para tu dominio de frontend

---

## Próximas Versiones planeadas

- **v2**: Soporte para múltiples monedas criptomonedas
- **v3**: Webhooks para notificaciones de gastos
- **v4**: Endpoints de presupuestos y alertas
- **v5**: API GraphQL como alternativa