---
paths:
  - routes/api.php
---

# Routes

## API autenticada con throttle y tokens con expiración
El grupo auth:sanctum de routes/api.php lleva throttle:api además de auth:sanctum. Los personal access tokens se crean con expiración (now()->addDays(90)). CORS se restringe via config/cors.php a CORS_ALLOWED_ORIGINS (env), con supports_credentials=false.

## El parámetro de ruta de la API tiene que llamarse igual que en la web
`StoreCreditCardStatementRequest` y `StoreCreditCardPaymentRequest` resuelven la tarjeta con
`$this->route('credit_card')` para exigir `exchange_rate` cuando la tarjeta es en Bs y para
comprobar que el corte al que se imputa un abono es de **esa** tarjeta. Si la ruta de la API
nombrara el parámetro de otra forma (`{card}`, `{creditCard}`), el Form Request recibiría
`null`, se saltaría en silencio las dos comprobaciones y se podría imputar un abono al corte
de otra tarjeta. Por eso `routes/api.php` usa `{credit_card}`, `{savings_goal}` y
`{recurring_payment}`: los mismos nombres que genera `Route::resource` en `routes/web.php`.

Regla general: al exponer por API un módulo que ya existe en la web, **reutiliza el Form
Request** y respeta los nombres de parámetro que ese request espera; si algo se valida en
`withValidator()` leyendo la ruta, cambiar el nombre no rompe ningún test, solo desactiva la
validación.

## Los módulos nuevos de la API comparten Action y Presenter con la web
Metas de ahorro, pagos recurrentes y tarjetas siguen la misma regla que gastos e ingresos
(ver `.ai/rules/controllers.md`): la lógica vive en `App\Actions\{SavingsGoals,
RecurringPayments,CreditCards}\*` y la forma JSON en `App\Support\Presenters\*`. Los
controladores `Api/V1` y los web solo llaman. Si el cálculo cambia, se toca el Action una
vez; duplicarlo en el controlador de la API es cómo el congelado de la conversión (ADR-001)
acabaría divergiendo entre la web y un cliente externo.
