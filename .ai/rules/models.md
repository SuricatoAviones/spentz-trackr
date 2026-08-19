---
paths:
  - 'app/Models/*.php'
  - app/Models/User.php
---

# Models

## Conversión de moneda congelada por transacción
Cada Expense guarda exchange_rate (tasa usada al registrar), usd_amount y usdt_amount calculados al persistir. Los reportes jamás recalculan con la tasa actual. USDT se trata 1:1 con USD. El plan completo está en docs/.

## is_admin con cast boolean y agregados como strings
User.is_admin requiere el cast 'boolean' (si no, viene 1/0 de la BD y fallan asserts toBeTrue y el JSON del shared prop). Los agregados Eloquent conSum/withMax (expenses_sum_usd_amount, expenses_max_spent_at) devuelven strings crudos, no Carbon: parsear con Carbon::parse() antes de toDateString().
