# 12 — Tarjetas de crédito

Módulo para llevar las tarjetas: banco, límite, ciclo de corte y pago, tasa de interés y
saldo. Entregadas las **fases 1 y 2** (tarjetas + ciclo de cortes y abonos); cuotas y
estimación de intereses quedan para la fase 3.

## Las dos decisiones que dan forma a todo

### 1. La tarjeta **posee** un origen de pago

Al crear una tarjeta se crea también un `PaymentSource` con su nombre, y `credit_cards`
guarda un `payment_source_id` único. Consecuencias, todas buenas:

- Los consumos de la tarjeta son **gastos normales** registrados contra ese origen. No hay
  tabla de movimientos paralela ni doble registro.
- El formulario de gastos no cambió ni una línea: la tarjeta aparece sola en el desplegable.
- Cero migración de datos: lo que ya estuviera registrado contra "Visa Banesco" pasa a contar
  para la tarjeta en cuanto se enlaza.

Esto **revierte** una decisión que estaba en `docs/01-vision-y-alcance.md` ("Saldos por
cuenta: No — el origen es una etiqueta"). El origen sigue siendo una etiqueta para todo lo
demás; solo las tarjetas tienen estado. Ver ADR-012 en `docs/04-arquitectura.md`.

Al borrar una tarjeta, su origen se elimina **solo si nunca se usó**. Si tiene gastos se
conserva: son movimientos reales de un historial ya cerrado.

### 2. Pagar la tarjeta NO es un gasto

La trampa clásica de estas apps. Si las compras se registran como gastos **y** el pago
mensual también, cada consumo se cuenta dos veces y todos los informes mienten.

Por eso los abonos viven en `credit_card_payments`, tabla propia, y **nada de ahí entra en
los totales de gasto**. Un abono solo baja la deuda. Hay un test dedicado
(`a card payment never counts as an expense`) que compara los totales de la pantalla de
gastos antes y después de registrar un abono.

## De dónde sale el saldo: híbrido

Ni derivado ni manual, las dos opciones malas:

- **Derivado** (sumar los gastos) miente en cuanto olvidas registrar un consumo, y no sabe
  de intereses ni de compras a cuotas.
- **Manual** es exacto al teclearlo y viejo al día siguiente.

El módulo usa las dos mitades por separado:

| Cifra | Qué es | Fuente |
|---|---|---|
| `closing` | Saldo del último corte | **El banco.** Lo teclea el usuario desde su estado de cuenta |
| `charges_since_cut` | Consumos posteriores al corte | Gastos contra el origen de la tarjeta |
| `payments_since_cut` | Abonos posteriores al corte | `credit_card_payments` |
| `projected_used` | `closing + charges − payments` | Proyección |
| `available` | `credit_limit − projected_used` | Proyección |

`is_estimate` viaja en el payload y vale `false` cuando no hubo movimientos tras el corte
—entonces la cifra **es** la del banco—. La UI solo muestra el aviso "estimación, tu banco
confirma el saldo real" cuando vale `true`. **Nunca se presenta una proyección como si fuera
el saldo del banco.**

### Movimientos en otra moneda

Una tarjeta opera en una sola moneda: su límite, sus cortes y sus abonos van en ella. Un
consumo registrado en otra moneda **no se suma** al saldo, porque convertirlo con la tasa de
hoy falsearía una cifra que el banco lleva en su propia moneda (y contradiría el congelado
del ADR-001). Se cuentan en `foreign_movements` y la ficha avisa.

## Modelo de datos

```
credit_cards
  user_id, payment_source_id (único)
  bank, name, last_four, brand, currency
  credit_limit, cut_day, due_day
  annual_interest_rate, minimum_payment_rate
  active, icon, color, note

credit_card_statements                      credit_card_payments
  credit_card_id                              credit_card_id
  cut_date, due_date  (únicos por tarjeta)    credit_card_statement_id (opcional)
  closing_balance, minimum_payment            amount, paid_at
  currency, exchange_rate,                    currency, exchange_rate,
  usd_amount, usdt_amount   (congelado)       usd_amount, usdt_amount  (congelado)
  paid_at, note                               note
```

Solo se guardan los **últimos cuatro dígitos**: almacenar el número completo de una tarjeta
sería guardar un dato de pago sin ninguna necesidad.

Un corte por fecha y tarjeta (índice único + validación). Dos cortes el mismo día harían
ambiguo cuál es "el último" y la proyección partiría de un ancla equivocada.

## Aritmética del ciclo

`App\Services\CreditCardCycleService`. Es donde viven los bugs de este módulo, así que tiene
su propio test unitario sin base de datos (`tests/Unit/CreditCardCycleTest.php`).

- **Días que no existen.** `cut_day = 31` no existe en febrero. `onDay()` recorta al último
  día real del mes (28, 29 o 30 según toque). Es el bug que aparece en producción tres meses
  después de entregar.
- **El siguiente corte es estrictamente posterior a hoy.** El propio día del corte, el
  siguiente ya es el del mes que viene.
- **El vencimiento cae en el mes siguiente si el día de pago es anterior o igual al de
  corte.** Una tarjeta que corta el 15 y se paga el 5, vence el 5 del mes siguiente. Con día
  de pago igual al de corte también rueda al siguiente: nadie paga el mismo día que cierra.

## Ficha de la tarjeta

`/credit-cards` lista las tarjetas con disponible, barra de uso y próximas fechas.
`/credit-cards/{id}` añade: registrar corte, registrar abono, historial de ambos y los
consumos del origen. Un corte se marca pagado solo cuando los abonos imputados **cubren** su
saldo; los parciales lo dejan abierto, como hace el banco.

## Lo que NO hace, a propósito

- **No replica el cálculo de intereses del banco.** La tasa se guarda (`annual_interest_rate`)
  pero no se simula el interés: hacerlo con fidelidad implica tasas topadas por el BCV,
  devengo diario, mora y financiamiento parcial. Prometer exactitud ahí sería prometer algo
  que no se puede cumplir. La fase 3 mostraría una estimación claramente etiquetada.
- **No maneja compras a cuotas.** Son muy comunes en Venezuela y merecen su propio diseño
  (una compra que genera N cargos futuros), no un parche.
- **No se conecta al banco.** Como el resto de la app: todo lo introduce el usuario.

## En la API REST

El módulo vive también en `/api/v1/credit-cards` (listado, ficha, alta, edición, borrado,
cortes y abonos), sobre **las mismas Actions, Form Requests y `CreditCardPresenter`** que la
web: las dos invariantes de arriba no se pueden romper desde un cliente externo porque no
hay una segunda implementación donde romperlas. Detalle de cada endpoint en
`docs/api/api-documentation.md`.

## Fase 3, pendiente

- Compras a cuotas.
- Estimación del costo de no pagar completo.
- Avisos de corte y vencimiento próximos en el dashboard.
