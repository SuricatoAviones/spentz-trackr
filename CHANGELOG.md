# Changelog

Todos los cambios reseñables de Spentz Trackr se anotan aquí.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el
versionado es [SemVer](https://semver.org/lang/es/).

Para una instancia auto-hospedada, **la versión es lo que te permite saber qué estás
corriendo y qué cambia al actualizar**. `php artisan app:update` hace `git pull`: si sigues
`main` vas al día pero sin red; si te sitúas en un tag, sabes exactamente qué tienes.

## Qué significa cada número

- **MAYOR** — hay que hacer algo a mano para actualizar (una variable nueva obligatoria en
  el `.env`, un cambio que rompe la API, una migración que no se puede deshacer).
- **MENOR** — funcionalidad nueva; actualizar es `app:update` y nada más.
- **PARCHE** — correcciones y seguridad, sin cambios de comportamiento.

Cualquier entrada que exija una acción del operador va marcada con **⚠ Acción requerida**.

## [No publicado]

Nada todavía.

## [1.2.0] — 2026-11-06

Llega el asistente de IA: cada usuario conecta **su propio proveedor y su propia clave** para
leer facturas, importar de una vez los movimientos de un estado de cuenta bancario y preguntar
por sus finanzas; y un estado de cuenta nuevo reúne los movimientos de cualquier periodo, con
o sin IA. Las cifras las sigue calculando la app y nada se guarda sin que el usuario lo
confirme. Actualizar es `php artisan app:update`: aplica una migración nueva (`ai_settings`) y
no exige ninguna variable de entorno — todas las `AI_*` son opcionales.

### Añadido

- **Asistente de IA con la clave de cada usuario.** En **Ajustes → Inteligencia artificial**
  cada usuario elige su proveedor (OpenAI, Anthropic, Google Gemini, Mistral, Groq,
  DeepSeek, OpenRouter, OpenCode —planes pago por uso y Go— y, si el operador lo permite,
  Ollama o un endpoint OpenAI-compatible),
  el modelo y pega su clave, que se guarda cifrada y nunca vuelve al navegador ni a los
  backups. La instancia no paga el consumo.
- **Leer facturas con IA.** En "Nuevo gasto", una foto o PDF de la factura, ticket o
  comprobante de pago móvil rellena monto, moneda, fecha, categoría, origen, tasa impresa y
  nota (comercio, RIF, número de factura y líneas). Nada se guarda hasta que el usuario
  revisa y pulsa Guardar.
- **Asistente financiero** (`/asistente`): preguntas en lenguaje natural sobre gastos,
  ingresos, presupuesto, metas, recurrentes y tarjetas; resúmenes, comparativas, estados de
  cuenta e ideas para ahorrar. Puede proponer un gasto que el usuario confirma con un botón.
  Las conversaciones no se guardan en el servidor.
- **Importar estado de cuenta** (`/ia/importar`): el PDF del banco o hasta 5 capturas de la
  app se convierten en una lista de movimientos para revisar (gasto o ingreso, categoría,
  monto, fecha y tasa por fila). Cada movimiento en Bs congela la tasa **de su día**, no la
  de hoy; lo que ya estaba registrado sale marcado como duplicado y desmarcado. Se importa
  todo o nada, por los mismos Actions que un gasto o ingreso manual.
- **Estado de cuenta** (`/reports/estado-de-cuenta`): movimientos de un periodo en orden
  cronológico con neto acumulado, totales, desgloses, comparación con el periodo anterior,
  presupuesto, export CSV, versión imprimible y análisis con IA.
- **API:** `GET /api/v1/reports/statement` y `/api/v1/ai/*` (ajustes, prueba de conexión,
  lectura de facturas, chat, análisis del estado de cuenta y
  `POST /ai/statements/{scan,import}` para importar movimientos).
- **Panel admin:** interruptor del asistente de IA en **Sistema** (no borra claves).

### Variables nuevas (todas opcionales)

`AI_ENABLED` (true), `AI_ALLOW_CUSTOM_ENDPOINTS` (false — activarlo permite que los usuarios
hagan que el servidor conecte a cualquier URL), `AI_TIMEOUT` (60), `AI_IMPORT_TIMEOUT` (120),
`AI_CONNECT_TIMEOUT` (10),
`AI_RATE_LIMIT_PER_MINUTE` (10), `AI_RATE_LIMIT_PER_DAY` (300). Cada factura o captura que
se sube a la IA puede pesar hasta 7 MB.
Ver `.env.example`.

### Cambiado

- La **política de datos** explica ahora el envío a proveedores de IA cuando el usuario los
  conecta; la fecha de vigencia por defecto pasa a 2026-11-06.

## [1.1.0] — 2026-09-18

La API REST se pone al día con la aplicación: todo lo que se podía hacer desde la web y no
desde un token ya se puede hacer desde un token. Actualizar es `php artisan app:update` y
nada más.

### Añadido

- **Metas de ahorro en la API.** `GET|POST /api/v1/savings-goals`, ficha, edición, borrado y
  aportes (`POST|DELETE .../{id}/contributions`). El listado admite `?achieved=1|0` y trae
  lo ahorrado y el porcentaje ya calculados. La meta se marca y se desmarca cumplida sola,
  según la suma de los aportes.
- **Pagos recurrentes en la API.** CRUD completo más `POST /api/v1/recurring-payments/{id}/pay`,
  que adelanta el vencimiento un periodo. Filtros `?active=1|0` y `?due=1`, con los vencidos
  primero. Marcar uno como pagado **no crea un gasto**: el recurrente es el recordatorio, no
  el movimiento.
- **Tarjetas de crédito en la API.** Listado con ciclo y saldo proyectado, ficha con cortes,
  abonos y consumos recientes, alta, edición, borrado, y cortes y abonos
  (`.../{id}/statements`, `.../{id}/payments`). Igual que en la web, un abono no cuenta como
  gasto y `balance.projected_used` viaja marcado con `is_estimate` cuando es una proyección.
- **Pausar y reanudar un pago recurrente** enviando `active` al actualizarlo (la web ya
  mostraba el estado, pero no había forma de cambiarlo).

### Cambiado

- La lógica de metas de ahorro y pagos recurrentes sale de los controladores web a Actions
  (`App\Actions\SavingsGoals\*`, `App\Actions\RecurringPayments\*`) y su forma JSON a
  `SavingsGoalPresenter` / `RecurringPaymentPresenter`, compartidos por web y API. No hay una
  segunda implementación donde el congelado de la conversión pueda divergir.
- La documentación OpenAPI (`/api/v1`) y la referencia REST (`docs/api/api-documentation.md`)
  cubren los tres módulos nuevos, con los campos obligatorios de cada uno y cuándo hace falta
  `exchange_rate`.

## [1.0.0] — 2026-09-13

Primera versión etiquetada. La aplicación llevaba tiempo funcionando; este tag fija un punto
conocido al que volver.

### Añadido

- **Tarjetas de crédito.** Banco, límite, moneda, ciclo de corte y pago, tasa de interés,
  cortes y abonos. El saldo es híbrido: el corte que teclea el usuario es la verdad y entre
  cortes se proyecta, siempre marcado como estimación. Los abonos no cuentan como gasto.
- **Términos y condiciones** (`/terminos`) y **política de datos** (`/privacidad`), públicas
  y en español e inglés. **⚠ Acción requerida:** rellena `LEGAL_OPERATOR`,
  `LEGAL_CONTACT_EMAIL` y `LEGAL_JURISDICTION` antes de abrir el registro al público.
- **Interruptores de instancia** en el panel de administración: encender o apagar la API
  REST y abrir o cerrar el registro de usuarios, sin tocar el servidor.
- **Restauración de copias**: `php artisan backup:restore fichero.json`. Las contraseñas no
  viajan en la copia, así que los usuarios restaurados deben recuperar la suya.
- Tests de render del front-end con Vitest, que atrapan las pantallas en blanco que las
  pruebas de PHP no pueden ver.

### Corregido

- Las páginas de detalle y edición de gastos e ingresos devolvían el modelo crudo en lugar
  del presenter, y quedaban **en blanco** sin error en el servidor.
- Tema claro: acentos sin contraste y dos textos blancos invisibles sobre fondo blanco.
- El favicon no se veía (el SVG referenciaba una imagen externa, que los navegadores no
  cargan) y los iconos `maskable` del PWA recortaban el nombre de la app.
- La copia de seguridad omitía ingresos, metas, aportes, pagos recurrentes y tarjetas.

### Seguridad

- **⚠ Acción requerida:** `TRUSTED_PROXIES` ya no vale `*` por defecto. Si despliegas detrás
  de un proxy inverso, indica su red; si no, `$request->ip()` será la del proxy y todos los
  usuarios compartirán el mismo cupo de intentos de acceso. Con `*`, cualquiera podía
  falsificar su IP y el host de los enlaces de recuperación de contraseña.
- **Los comprobantes dejan de ser públicos.** Estaban en un disco servido directamente por
  el servidor web: cualquiera con la URL veía la factura de cualquier usuario. Ahora salen
  por una ruta que comprueba el propietario. La migración mueve los ficheros existentes.
- Cambiar la contraseña revoca ahora las demás sesiones y los tokens de API.
- Cabeceras de seguridad (CSP, X-Frame-Options, HSTS sobre HTTPS) en todas las respuestas.
- La política de contraseñas se aplica siempre, no solo en producción.
- **⚠ Acción requerida:** `.env.docker` ya no trae contraseñas por defecto que funcionaban.
  Pon las tuyas antes de desplegar.
- Un administrador ya no puede quitarse el rol ni dejar la instancia sin ninguno activo.

[No publicado]: https://github.com/SuricatoAviones/spentz-trackr/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/SuricatoAviones/spentz-trackr/releases/tag/v1.2.0
[1.1.0]: https://github.com/SuricatoAviones/spentz-trackr/releases/tag/v1.1.0
[1.0.0]: https://github.com/SuricatoAviones/spentz-trackr/releases/tag/v1.0.0
