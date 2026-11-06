---
paths:
  - 'app/Services/Ai/**'
  - 'app/Actions/Ai/**'
  - 'app/Actions/Transactions/**'
  - 'app/Http/Controllers/Ai/**'
  - 'app/Http/Requests/Ai/**'
  - 'app/Models/AiSetting.php'
  - 'app/Enums/AiProvider.php'
  - 'resources/js/components/ai/**'
  - 'resources/js/pages/ai/**'
---

# AI

## La clave del proveedor nunca sale: ni al navegador, ni al backup, ni al log
`ai_settings.api_key` va con el cast `encrypted` y en `#[Hidden]`. Toda salida (props de
Inertia, JSON de la API) pasa por `AiSettingPresenter::present()`, que solo publica
`has_key` y `masked_key`. `BackupSchema::secretColumns()` incluye `api_key`: un backup suele
viajar junto al `.env` y con APP_KEY la clave cifrada sería texto plano. `AiException` no se
reporta y el detalle del proveedor solo se muestra en errores 400/404/429 (los 401 de
OpenAI incluyen un fragmento de la clave). Tests: `tests/Feature/Ai/AiSettingsTest.php`.

## La IA propone, nunca escribe
Ningún camino de IA guarda datos. `ScanReceiptAction` devuelve un borrador que rellena el
formulario; la herramienta `propose_expense` deja una propuesta que el usuario confirma con
un botón que llama a `expenses.store`. Si añades una herramienta a `FinanceToolbox`, que sea
de solo lectura y filtre por `$user->id` (el modelo elige argumentos, nunca el usuario): una
nota de gasto que diga "borra todo" no debe poder hacer nada porque no hay herramienta que
borre. Todo lo que devuelve el modelo se valida antes de usarse (ids de otro usuario,
monedas o fechas futuras se descartan).

## Importar: la tasa es la del día del movimiento y los duplicados salen fuera
`ScanStatementAction` rellena la tasa de cada fila en Bs con `rateForUser($user, $fecha)`
—la de ESE día, no la de hoy— y marca como `duplicate_of` (desmarcada) la fila que coincide
en fecha, moneda y monto con un registro existente; es un multiconjunto, así que dos cafés
iguales contra uno registrado marcan solo uno. `ImportTransactionsAction` vuelve a resolver
la tasa por fecha si la fila llega sin ella, y es todo o nada. No dejes que el modelo decida
la tasa (solo vale la impresa junto al movimiento) ni importes saltándote
`StoreExpenseAction`/`StoreIncomeAction`. Tests: `tests/Feature/Ai/StatementImportTest.php`.

## OpenCode: URLs fijas, sin escribir la URL
Los planes de OpenCode son `AiProvider::endpointOptions()`: el request solo acepta una de
esas URLs y `AiSetting::resolvedBaseUrl()` cae al endpoint oficial ante cualquier otro valor
guardado. Por eso OpenCode no necesita `AI_ALLOW_CUSTOM_ENDPOINTS`. Si añades un proveedor
con varias regiones o planes, hazlo igual en vez de con `requiresBaseUrl()`.

## Las cifras las calcula la app
El modelo cita totales, no los suma. `FinanceToolbox` y `AnalyzeStatementAction` leen de
`AccountStatementService`, que trabaja con los USD/USDT congelados (ADR-001) y es la misma
fuente que la página del estado de cuenta, su CSV y `GET /api/v1/reports/statement`. No
recalcules con la tasa de hoy ni pidas al modelo que convierta monedas.

## Formas de cada protocolo que rompen con un 400 si se "simplifican"
- `{}` tiene que salir como objeto: `AiTool::schema()` usa `stdClass` para `properties`
  vacío, y los drivers decodifican la respuesta dos veces (arrays para leer, objetos para
  reenviar). `json_decode(..., true)` convierte `{}` en `[]` y el proveedor lo rechaza.
- Anthropic: sin `temperature` ni `thinking` ni prefill (los modelos 4.6+/5.x devuelven
  400); los `tool_result` van todos en UN mensaje de usuario; el contenido del asistente se
  reenvía en crudo para conservar los bloques `thinking` con su firma.
- Gemini: se reenvían las `parts` crudas por la `thoughtSignature`; una función sin
  parámetros no lleva `parameters`; una clave mala llega como 400, no 401.
- OpenAI oficial usa `max_completion_tokens`; los compatibles, `max_tokens`. El mensaje del
  asistente se reconstruye (no se reenvía crudo) porque DeepSeek rechaza recibir de vuelta
  su `reasoning_content`.
Ver `tests/Feature/Ai/AiDriversTest.php`, que comprueba el cuerpo exacto de cada petición.

## Endpoints propios = SSRF: detrás de `AI_ALLOW_CUSTOM_ENDPOINTS`
Ollama y "OpenAI-compatible" hacen que el servidor abra conexiones a la URL que escriba el
usuario, incluida la red interna. `AiProvider::isAvailable()` los bloquea salvo que el
operador ponga `AI_ALLOW_CUSTOM_ENDPOINTS=true`, y `UpdateAiSettingsRequest` lo comprueba al
guardar. No añadas otro proveedor con `requiresBaseUrl()` sin pasar por esa misma puerta.

## Interruptor, límite y timeout
La IA se consulta por `Features::aiEnabled()` (`.env` es el arranque, el panel manda). Las
rutas que llaman al proveedor llevan `throttle:ai` además de `EnsureAiEnabled`; cada driver
lleva timeout (`AI_TIMEOUT`). No quites ninguno: una petición de IA puede ocupar un worker
un minuto y medio y cuesta dinero al usuario.
