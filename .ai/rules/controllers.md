---
paths:
  - 'app/Http/Controllers/**'
  - app/Http/Controllers/ExpenseController.php
---

# Controllers

## Binding de rutas resource y SQL portable
En `Route::resource('sources', ...)` el parámetro de ruta es `{source}`; el parámetro del método del controlador debe llamarse `$source` (no `$paymentSource`) o el binding implícito falla y el modelo llega sin id (políticas devuelven 403). Evitar SQL específico de motor (ej. `YEAR()` no existe en SQLite): usar `substr(spent_at, 1, 4)` para extraer el año, portable MySQL/SQLite.

## Tasa automática vía ensureFreshRate en page loads
Nunca quitar `ensureFreshRate($user)` de Dashboard/Ajustes/Expense create|edit: es el único mecanismo de auto-sincronización de tasas en dev (el scheduler de 08:00 no corre con `composer run dev`). Respeta la tasa manual del día y no sobrescribe nada.

## Comisión en gastos Bs: regla "lo que sea mayor" y monto base aparte
En gastos en Bs la comisión (pago móvil o transferencia) se cobra con la regla max(min_commission, monto × commission_rate%), con piso configurable en Ajustes (default 14 Bs, 0,30%, Gaceta 43.427, punto de quiebre ≈ 4.667 Bs). expenses.amount guarda SIEMPRE la base (sin comisión); commission va en su propia columna y el equivalente USD/USDT se calcula sobre amount + commission. El frontend (expense-form.tsx) precalcula max(min, monto×%) al elegir método y lo deja editable con opción "Sin comisión".
