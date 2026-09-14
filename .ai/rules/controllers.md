---
paths:
  - 'app/Http/Controllers/**'
  - app/Http/Controllers/ExpenseController.php
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

## Tasa automática vía ensureFreshRate en page loads (con cooldown, no bloquear)
Nunca quitar `ensureFreshRate($user)` de Dashboard/Ajustes/Expense|Income create|edit: es el único mecanismo de auto-sincronización de tasas en dev (el scheduler `SyncExchangeRates` cada 5 min no corre con `composer run dev`). Respeta la tasa manual del día y no sobrescribe nada.

Ese camino es **inline en el render**, así que debe fallar rápido: `connectTimeout`+`timeout` de 3 s y, ante cualquier fallo, un cooldown de 5 min en caché (`exchange-rate:sync-failed`) que hace que `ensureFreshRate` ni lo intente. Sin eso, con dolarapi.com caído cada carga de esas páginas bloqueaba ~10 s (el connect timeout por defecto de Guzzle) y, como el fallo no se persistía ni cacheaba, la siguiente carga volvía a pagarlo: la app parecía colgada. No subir el timeout del camino inline ni quitar el cooldown. Los llamadores de fondo (job y botón "sincronizar") usan 15 s, ignoran el cooldown y lo limpian al tener éxito.

## Comisión en gastos Bs: regla "lo que sea mayor" y monto base aparte
En gastos en Bs la comisión (pago móvil o transferencia) se cobra con la regla max(min_commission, monto × commission_rate%), con piso configurable en Ajustes (default 14 Bs, 0,30%, Gaceta 43.427, punto de quiebre ≈ 4.667 Bs). expenses.amount guarda SIEMPRE la base (sin comisión); commission va en su propia columna y el equivalente USD/USDT se calcula sobre amount + commission. El frontend (expense-form.tsx) precalcula max(min, monto×%) al elegir método y lo deja editable con opción "Sin comisión".

## Sin instalador: la app se configura por .env (ADR-008)
No existe wizard `/install`, ni `InstallController`, ni `App\Services\Installer`, ni el
middleware `EnsureInstalled`, ni el comando `app:install` — se eliminaron a propósito y no
deben reintroducirse. La app **no arranca sin `APP_KEY`**: el despliegue es el flujo
estándar de Laravel (`.env` a mano → `key:generate` → `migrate --force` → `admin:create` →
`storage:link`). En Docker lo cubren `docker/entrypoint.d/98-spentz-key.sh` (genera y
persiste la clave en `storage/app.key`, dentro del volumen) y `99-spentz-migrate.sh`
(migra + `optimize` en cada arranque). `admin:create` NO va en el entrypoint: reescribe la
contraseña con `ADMIN_PASSWORD` en cada ejecución.

## Las páginas Inertia consumen el Presenter, nunca el modelo Eloquent crudo
`ExpenseController@show`/`@edit` e `IncomeController@edit` devolvían `$expense->load(...)`
tal cual. Eloquent serializa las relaciones en snake_case, así que el prop llegaba con
`payment_source` mientras `pages/expenses/{show,edit}.tsx` leen `expense.source`: la página
reventaba con `Cannot read properties of undefined (reading 'color')` y **quedaba en blanco,
sin error en el servidor ni en los logs**. Lo mismo con `spent_at`/`received_at`, que en
crudo llegan como timestamp ISO completo y los `<input type="date">` esperan `Y-m-d`, y con
`has_receipt` / `receipts[].url`, que solo existen en el presenter.

Regla: **todo modelo que viaje como prop a una página React pasa por
`App\Support\Presenters\{Expense,Income}Presenter::present()`** (web y API por igual), con
sus relaciones precargadas — `['category', 'paymentSource', 'receipts', 'items']` para
gastos, `['category', 'receipts']` para ingresos. El resto de controladores (categorías,
orígenes, metas, recurrentes, admin) mapea a mano con `->map()`/`->through()`: si añades un
prop de modelo nuevo, mapéalo igual; nunca lo pases crudo.

`resources/js/types/global.d.ts` (`Expense`, `Income`) es el contrato de esa forma: si
cambias el presenter, cambia el tipo, y al revés.

Ojo con el hueco de test: las pruebas de Inertia solo comprueban props del payload, nunca
renderizan el React, así que una forma equivocada pasa la suite en verde y solo se ve como
página en blanco en el navegador. Al tocar un presenter o una página de detalle/edición,
asegura la forma con `assertInertia(...->where('expense.source.id', ...))` — ver
`tests/Feature/{Expense,Income}CrudTest.php`, test "presenter shape".

## Tarjetas: el abono NO es un gasto, y el saldo proyectado no es el saldo
Dos invariantes del módulo de tarjetas que es fácil romper "mejorándolo":

**Un `CreditCardPayment` nunca debe crear un `Expense`.** Pagar la tarjeta mueve dinero del
bolsillo a la deuda; si además contara como gasto, cada consumo se contaría dos veces —al
comprar y al pagar— y todos los informes mentirían. `tests/Feature/CreditCardTest.php` compara
los totales de la pantalla de gastos antes y después de un abono.

**`projected_used` es una estimación cuando `is_estimate` es true.** El ancla es el último
corte, que teclea el usuario desde su estado de cuenta; lo que se suma encima son los gastos
registrados, que pueden estar incompletos. No presentes esa cifra como el saldo del banco ni
quites el aviso de la ficha. Un movimiento en otra moneda que la de la tarjeta se cuenta en
`foreign_movements` pero **no se suma**: convertirlo con la tasa de hoy rompería el congelado
del ADR-001.
