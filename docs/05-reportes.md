# 05 — Reportes

Todos los totales se calculan sobre los equivalentes congelados (`usd_amount`, `usdt_amount`), nunca sobre la tasa actual.

## Dashboard (UC-11)

- **Totales del mes en curso:**
  - Total gastado en **USD** (suma `usd_amount`).
  - Total gastado en **USDT** (suma `usdt_amount`).
  - Desglose por moneda original: cuánto se pagó en USD, Bs y USDT (en su moneda y en su equivalente USD).
- **Tasa del día** (Bs/USD) con fuente (`dolarapi` BCV/paralelo o manual) y fecha.
- **Gasto por categoría:** gráfica de dona con porcentajes (solo categorías con gasto en el período).
- **Gasto por origen:** barras horizontales (Binance, Bancos, Wallets, Efectivo, …).
- **Evolución:** línea con total USD de los últimos 6 meses.
- **Últimos gastos:** tabla compacta de los últimos 10 registros con acceso rápido a detalle/edición.

## Comparativo mensual (UC-12)

- Tabla de los últimos 12 meses: por mes, total USD, total USDT, variación % vs mes anterior, y gastos por moneda original.
- Gráfica de barras agrupadas (USD vs USDT por mes).
- Selector de año.

## Exportación CSV (UC-13)

- Respeta los filtros activos del listado.
- Columnas: `fecha, descripcion, categoria, origen, moneda, monto, tasa_bs_usd, equivalente_usd, equivalente_usdt, nota`.
- Codificación UTF-8 con BOM para que Excel/Venezuela (locale es-ES) abra bien los acentos.
- Se genera en streaming (memory-safe) con `League\Csv` o export desde el query builder; nombre de archivo: `gastos_YYYY-MM.csv`.

## Filtros compartidos (listado y exportación)

| Filtro | Tipo |
|---|---|
| Período | Desde / Hasta (por `spent_at`) |
| Moneda | `usd` \| `ves` \| `usdt` \| todas |
| Categoría | Select |
| Origen | Select |
| Texto | LIKE sobre descripción y nota |
| Monto | Rango mínimo/máximo en USD |

## Reglas de visualización

- Locale es-VE: separador decimal `,` y miles `.` (p. ej. `1.234,56`).
- Mostrar siempre sufijo de moneda (`USD`, `Bs`, `USDT`).
- Montos grandes en USD se redondean a 2 decimales; tasas a 4.
- Gráficas: librería ligera (Recharts) en React; datos agregados vienen del backend (consultas `GROUP BY`), nunca se agregan en el cliente.