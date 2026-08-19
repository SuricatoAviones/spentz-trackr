---
paths:
  - 'resources/js/**'
---

# Js

## Importar rutas Wayfinder desde módulos agrupados
Wayfinder con tree-shaking genera `resources/js/routes/<grupo>/index.ts` (ej: `@/routes/expenses`, `@/routes/reports`, `@/routes/exchange-rate`). Importar siempre desde el módulo agrupado (`{ index as expensesIndex }`), nunca desde `@/routes` raíz, que solo exporta rutas sin grupo (login, logout, home, dashboard, ajustes). `reports/export` se genera como `exportMethod` (export es palabra reservada). El build de Vite falla con "Missing export" si se usa el import incorrecto.

## Rutas admin con subgrupo generan módulos anidados en Wayfinder
Una ruta cuyo nombre tiene más segmentos que el grupo (ej. admin.expenses.receipts.show) se genera en un módulo anidado propio: `@/routes/admin/expenses/receipts` (export `show`). No buscarla en `@/routes/admin/expenses`. Verificar con la plantilla generada tras `php artisan wayfinder:generate` antes de importar.
