# API REST - Spent Trackr

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