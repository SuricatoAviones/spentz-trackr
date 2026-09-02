# 01 — Visión y Alcance

## Visión

Spent Trackr es una aplicación personal de control de gastos diseñada para el contexto venezolano, donde conviven múltiples monedas (dólar, bolívar y USDT) y tasas de cambio volátiles. El objetivo central es responder con un vistazo: **¿cuánto he gastado en USD y USDT este mes?**

## Objetivos

1. Registrar gastos de forma rápida en cualquier moneda (USD, Bs, USDT).
2. Mostrar siempre los totales en **USD y USDT** (conversión automática de Bs usando la tasa del día o la de la transacción).
3. Clasificar cada gasto por **categoría** (tipo de gasto) y por **origen** (dónde se pagó: Binance, bancos, wallets, efectivo).
4. Automatizar la tasa Bs/USD vía API, con opción de ajuste manual por transacción.
5. Reportes completos: gráficas, comparativos y exportación.
6. Multi-usuario: cada cuenta ve solo sus datos.
7. Futuro: PWA instalable y desplegada en cPanel con MySQL.

## Decisiones de producto (acordadas con el usuario)

| Decisión | Elección | Impacto |
|---|---|---|
| Usuarios | **Multi-usuario con login** | Fortify ya instalado; cada usuario tiene datos aislados |
| Registro | **Solo gastos** (sin ingresos) | No hay balance neto ni módulo de ingresos |
| Tasas de cambio | **API automática + override manual** | Sincronización cada 5 minutos (BCV y paralela); cada gasto puede usar tasa manual |
| Saldos por cuenta | **No** — solo movimientos | El origen es una etiqueta, no se calculan saldos |
| Presupuestos | **No por ahora** | Fuera de alcance v1, posible v2 |
| Reportes | **Completos** | Dashboard, gráficas, comparativos, CSV |
| Idioma | **ES/EN con selector persistente** | Español por defecto (mercado principal), inglés completo vía i18next + `lang/`; preferencia por usuario, sesión y navegador |

## Monedas soportadas

| Código | Nombre | Rol en la app |
|---|---|---|
| `usd` | Dólar estadounidense | Moneda base de reportes |
| `ves` | Bolívar (Bs) | Conversión a USD con tasa del día o de la transacción |
| `usdt` | Tether (USDT) | Tratado como moneda propia; referencia 1:1 con USD |

## Alcance funcional (resumen)

- Autenticación y registro de usuarios.
- CRUD de gastos con: monto, moneda, tasa (auto o manual), categoría, origen, nota, comprobante (imagen), fecha.
- CRUD de categorías (tipo de gasto) por usuario.
- CRUD de orígenes (Binance, Bancos, Wallets, Efectivo, personalizados) por usuario.
- Dashboard: totales del mes en USD y USDT, gasto por categoría, evolución, comparativo mensual.
- Listado de gastos con búsqueda y filtros avanzados.
- Exportación a CSV.
- Sincronización automática diaria de la tasa Bs/USD (API `dolarapi.com` con fallback manual).
- PWA (fase 3): instalable, offline básico, íconos y manifest.

## Fuera de alcance (v1)

- Ingresos y balance neto.
- Presupuestos y alertas.
- Saldos por cuenta/banco/wallet.
- Multi-divisa en una misma transacción (un gasto = una moneda).
- Pagos recurrentes automáticos.
- API pública para terceros.