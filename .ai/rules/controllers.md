---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Binding de rutas resource y SQL portable
En `Route::resource('sources', ...)` el parámetro de ruta es `{source}`; el parámetro del método del controlador debe llamarse `$source` (no `$paymentSource`) o el binding implícito falla y el modelo llega sin id (políticas devuelven 403). Evitar SQL específico de motor (ej. `YEAR()` no existe en SQLite): usar `substr(spent_at, 1, 4)` para extraer el año, portable MySQL/SQLite.

## Tasa automática vía ensureFreshRate en page loads
Nunca quitar `ensureFreshRate($user)` de Dashboard/Ajustes/Expense create|edit: es el único mecanismo de auto-sincronización de tasas en dev (el scheduler de 08:00 no corre con `composer run dev`). Respeta la tasa manual del día y no sobrescribe nada.
