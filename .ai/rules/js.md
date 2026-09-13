---
paths:
  - 'resources/js/**'
---

# Js

## Importar rutas Wayfinder desde módulos agrupados
Wayfinder con tree-shaking genera `resources/js/routes/<grupo>/index.ts` (ej: `@/routes/expenses`, `@/routes/reports`, `@/routes/exchange-rate`). Importar siempre desde el módulo agrupado (`{ index as expensesIndex }`), nunca desde `@/routes` raíz, que solo exporta rutas sin grupo (login, logout, home, dashboard, ajustes). `reports/export` se genera como `exportMethod` (export es palabra reservada). El build de Vite falla con "Missing export" si se usa el import incorrecto.

## Rutas admin con subgrupo generan módulos anidados en Wayfinder
Una ruta cuyo nombre tiene más segmentos que el grupo (ej. admin.expenses.receipts.show) se genera en un módulo anidado propio: `@/routes/admin/expenses/receipts` (export `show`). No buscarla en `@/routes/admin/expenses`. Verificar con la plantilla generada tras `php artisan wayfinder:generate` antes de importar.

## Nada de colores crudos en el shell: el tema vive en los tokens de `app.css`
El modo claro se rompía porque la barra lateral, el drawer `side="right"` y la barra
inferior traían el color del diseño oscuro incrustado (`bg-[#0d1526]`, `bg-[#0b1220]/80`)
y todos los separadores eran `border-white/5`. Con `.dark` fuera, el menú seguía negro y
los bordes desaparecían sobre fondo claro. Usar siempre los tokens: `bg-sidebar` /
`border-sidebar-border` / `hover:bg-sidebar-accent` en la navegación, `bg-background`,
`bg-card`, `bg-surface-low|high`, `border-border`, `divide-border` y `bg-muted` en el
resto. `white/x` solo se justifica encima de algo que es oscuro en ambos temas (el
lightbox de recibos, las fichas de color). Los acentos fijos también necesitan par:
`text-emerald-600 dark:text-emerald-400`, porque emerald-400 no contrasta en claro.

## Toda página pública necesita su rama en el resolvedor de layouts de `app.tsx`
El `switch` de `createInertiaApp({ layout })` manda al `default` —`TrackerLayout`— todo lo
que no tenga rama propia, y TrackerLayout lee `auth.user.tracking_type`. En una página
alcanzable sin sesión `auth.user` es `null`, así que lanza un TypeError y React no pinta
nada: **pantalla en blanco con respuesta 200 y ni un error en los logs del servidor**. Pasó
al añadir `/terminos` y `/privacidad`.

Al crear una página fuera del grupo `auth`, añade su `case` (exacto o por prefijo) y
regístrala en `tests/Feature/PublicPagesLayoutTest.php`, que compara las rutas GET sin
middleware `auth` contra las ramas presentes en `app.tsx`. Es lo más cerca que se puede
estar de detectarlo desde PHP: ninguna prueba renderiza React.
