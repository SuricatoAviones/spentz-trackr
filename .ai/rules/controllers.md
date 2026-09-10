---
paths:
  - 'app/Http/Controllers/**'
  - app/Http/Controllers/ExpenseController.php
  - app/Http/Controllers/InstallController.php
---

# Controllers

## Gastos/Ingresos: la lógica vive en Actions, no en el controller
`ExpenseController` y `Api/V1/ExpenseController` (ídem Income) son delgados: `store`/`update`
llaman a `App\Actions\Expenses\{Store,Update}ExpenseAction->handle($user, $validated, $receipt, $removeReceipt?)`.
El Action (trait `Concerns\PersistsExpense`) hace: resolución de tasa (`ResolvesTransactionRate`),
comisión Bs, items mixtos, congelado USD/USDT y recibos, todo en `DB::transaction`. La
salida JSON va por `App\Support\Presenters\{Expense,Income}Presenter::present()`, compartido
web+API. NO reintroducir esta lógica en un controller ni duplicarla entre web y API — si
cambia el cálculo, se toca el Action/trait una sola vez.

## Binding de rutas resource y SQL portable
En `Route::resource('sources', ...)` el parámetro de ruta es `{source}`; el parámetro del método del controlador debe llamarse `$source` (no `$paymentSource`) o el binding implícito falla y el modelo llega sin id (políticas devuelven 403). Evitar SQL específico de motor (ej. `YEAR()` no existe en SQLite): usar `substr(spent_at, 1, 4)` para extraer el año, portable MySQL/SQLite.

## Tasa automática vía ensureFreshRate en page loads
Nunca quitar `ensureFreshRate($user)` de Dashboard/Ajustes/Expense create|edit: es el único mecanismo de auto-sincronización de tasas en dev (el scheduler `SyncExchangeRates` cada 5 min no corre con `composer run dev`). Respeta la tasa manual del día y no sobrescribe nada.

## Comisión en gastos Bs: regla "lo que sea mayor" y monto base aparte
En gastos en Bs la comisión (pago móvil o transferencia) se cobra con la regla max(min_commission, monto × commission_rate%), con piso configurable en Ajustes (default 14 Bs, 0,30%, Gaceta 43.427, punto de quiebre ≈ 4.667 Bs). expenses.amount guarda SIEMPRE la base (sin comisión); commission va en su propia columna y el equivalente USD/USDT se calcula sobre amount + commission. El frontend (expense-form.tsx) precalcula max(min, monto×%) al elegir método y lo deja editable con opción "Sin comisión".

## Instalador bloqueado en producción y tras instalación
El constructor de InstallController aborta 404 en APP_ENV=production y 403 si ya existe storage/installed. Nunca permitir re-ejecutar install/execute una vez instalado (reescribiría .env y crearía un admin). El identificador de instalación se marca con storage/installed (gitignored).
