# 01 — Visión y Alcance

## Visión

Spentz Trackr es una aplicación personal de control de finanzas diseñada para el contexto
venezolano, donde conviven múltiples monedas (dólar, bolívar y USDT) y tasas de cambio
volátiles. El objetivo central es responder de un vistazo: **¿cuánto gasté y cuánto gané
este mes en USD y en USDT?**

Es **open-source y auto-hospedable**: cada quien corre su propia instancia (cPanel, VPS o
Docker) con su base de datos.

## Objetivos

1. Registrar **gastos e ingresos** rápido en cualquier moneda (USD, Bs, USDT).
2. Mostrar siempre los totales en **USD y USDT**, con conversión automática de Bs usando la
   tasa del día o la congelada en la transacción.
3. Clasificar cada movimiento por **categoría** y (los gastos) por **origen de pago**.
4. Automatizar la tasa Bs/USD vía API, con ajuste manual por día o por transacción.
5. Reportes completos: dashboard, tendencias, presupuestos, comparativos y exportación CSV.
6. Multiusuario con datos aislados, más un **panel de administración**.
7. **API REST** para clientes externos.
8. PWA instalable.

## Decisiones de producto

| Decisión | Elección | Impacto |
|---|---|---|
| Usuarios | Multiusuario con login (Fortify) | Datos aislados por `user_id`; verificación de correo, 2FA y passkeys |
| Alcance de registro | Gastos **e ingresos** | El usuario elige en Ajustes: solo gastos, solo ingresos, o ambos (`tracking_type`) |
| Tasas de cambio | API automática + override manual | Sync cada 5 min (BCV y paralelo); cada transacción puede fijar su tasa |
| Comisiones en Bs | `max(mínimo, monto × %)` para pago móvil / transferencia | Piso y porcentaje configurables en Ajustes; `amount` guarda la base, `commission` va aparte |
| Multi-moneda por transacción | **Sí** (gastos mixtos) | Líneas `ExpenseItem` con su propia moneda y tasa; el equivalente USD/USDT se agrega |
| Saldos por cuenta | No | El origen es una etiqueta; no se calculan saldos |
| Presupuestos | Global mensual **y** por categoría | Barras de progreso en el dashboard; sin alertas todavía |
| Idioma | ES/EN con selector persistente | Español por defecto; preferencia por usuario, sesión y navegador |
| Despliegue | cPanel, VPS o Docker | Configuración por `.env` + Compose |

## Monedas soportadas

| Código | Nombre | Rol |
|---|---|---|
| `usd` | Dólar estadounidense | Moneda base de reportes |
| `ves` | Bolívar (Bs) | Se convierte a USD con la tasa del día o de la transacción |
| `usdt` | Tether (USDT) | Moneda propia; referencia 1:1 con USD |

## Alcance funcional

- Autenticación (registro, login, verificación de correo, 2FA, passkeys, reset).
- CRUD de gastos (monto, moneda, tasa, comisión Bs, categoría, origen, ítems mixtos, nota,
  comprobante, fecha).
- CRUD de ingresos (análogo, sin origen ni comisión).
- CRUD de categorías (tipo gasto/ingreso) y orígenes de pago.
- Metas de ahorro con aportes; pagos recurrentes con vencimientos.
- Dashboard, comparativo mensual, presupuestos, exportación CSV.
- Sincronización automática de la tasa Bs/USD (`dolarapi.com`) con fallback manual.
- API REST `/api/v1` con tokens Sanctum y documentación OpenAPI.
- Panel de administración (`/admin`): usuarios, gastos globales, tasas, auditoría, backup.
- PWA: manifest, service worker, offline básico.
- i18n ES/EN.

## Fuera de alcance (hoy)

- Generación automática del gasto cuando vence un pago recurrente (hoy solo avanza la fecha).
- Alertas / notificaciones de presupuesto.
- Import desde extractos bancarios.
- Multi-tenant (organizaciones con varios usuarios).
- Aplicaciones nativas (la API REST las habilita, pero no se desarrollan aquí).
