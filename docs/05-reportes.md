# 05 — Reportes

Todos los totales se calculan sobre los equivalentes **congelados**
(`usd_amount`, `usdt_amount`), nunca sobre la tasa actual. Las agregaciones por mes /
categoría / origen se hacen en SQL (`GROUP BY`, `sum()`), no en el cliente. Las gráficas
son **componentes SVG propios** (`resources/js/components/tracker/donut-chart.tsx`,
`trend-chart.tsx`, y `MonthlyChart` en la página de reportes) — no hay librería de charts.

## Dashboard (`GET /dashboard`)

Lo que se muestra depende de `users.tracking_type` (`expenses` | `income` | `both`):

- **Totales del mes**: gastado / ingresado en **USD** y en **USDT**, más el desglose por
  moneda original (cuánto en USD, Bs y USDT).
- **Neto** (solo `both`): ingresos − gastos.
- **Tasa del día** Bs/USD con fuente (`bcv` / `paralelo` / `manual`) y fecha.
- **Gasto por categoría**: dona con porcentajes (solo categorías con gasto en el período).
- **Gasto por origen**: barras (Binance, Bancos, Wallets, Efectivo, …).
- **Tendencia**: línea con el total USD de los últimos meses.
- **Presupuesto mensual global** y **presupuestos por categoría** (`categories.budget`):
  barra de progreso gastado / límite.
- **Últimos movimientos**: tabla compacta con acceso a detalle/edición.

## Reportes anuales (`GET /reports`)

- Selector de año.
- **Serie mensual** (12 meses): total USD y USDT por mes, desglose por moneda original y
  variación % vs. mes anterior.
- **Desglose por categoría** y **por origen** del año, con % del total.
- Para `tracking_type = both`, gastos e ingresos lado a lado.
- API equivalente: `GET /api/v1/reports` y `GET /api/v1/reports/monthly-summary`.

## Exportación CSV (`GET /reports/export`, y `GET /admin/expenses/export`)

- Respeta los filtros activos del listado.
- Streaming *memory-safe* vía `App\Support\CsvExporter::download()` — genera un
  `StreamedResponse`, escribe BOM UTF-8 (`\xEF\xBB\xBF`) para que Excel abra bien los
  acentos, y protege cada celda contra inyección de fórmulas
  (`CsvExporter::cell()` antepone `'` a valores que empiezan por `= + - @` tab o CR).
- El `Content-Type` es `text/csv; charset=UTF-8` **inline** (no `attachment`); en tests
  usar `streamedContent()`, no `assertDownload`.
- Columnas del export de usuario: `fecha, descripcion, categoria, origen, moneda, monto,
  tasa_bs_usd, tasa_fuente, equivalente_usd, equivalente_usdt, nota`. El export del panel
  admin añade `usuario` y `email`.
- Nombre de archivo: `gastos_YYYY-MM.csv` (usuario) / `gastos_globales_YYYY-MM-DD.csv` (admin).

## Filtros compartidos (listado y export)

| Filtro | Detalle |
|---|---|
| Período | `from` / `to` sobre `spent_at` (`received_at` para ingresos) |
| Moneda | `usd` \| `ves` \| `usdt` \| todas |
| Categoría | id |
| Origen | id (solo gastos) |
| Texto | `LIKE` sobre `description` y `note` |

## Reglas de visualización

- Fechas, meses y montos se formatean en el cliente con `Intl` según el locale
  (`resources/js/lib/format`); el backend devuelve fechas ISO e índices de mes, nunca
  texto localizado.
- Sufijo de moneda siempre visible (`USD`, `Bs`, `USDT`).
- Montos a 2 decimales; tasas a 4.
