---
paths:
  - 'app/Models/*.php'
---

# Models

## Conversión de moneda congelada por transacción
Cada Expense guarda exchange_rate (tasa usada al registrar), usd_amount y usdt_amount calculados al persistir. Los reportes jamás recalculan con la tasa actual. USDT se trata 1:1 con USD. El plan completo está en docs/.
