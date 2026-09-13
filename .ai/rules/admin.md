---
paths:
  - 'app/Http/Controllers/Admin/**'
---

# Admin

## Panel admin: rutas en routes/admin.php protegidas por middleware admin
El panel admin vive bajo el prefijo /admin (routes/admin.php) con los middleware auth+verified+admin (alias EnsureUserIsAdmin, 403 para no admins). Sus controladores cruzan usuarios a propósito (sin scoping forUser) pero nunca deben filtrar datos de negocio fuera del grupo admin. El admin no puede eliminarse a sí mismo (403 en destroy).

## Backup y export CSV sin datos sensibles ni fórmulas
SystemController::backup excluye password, two_factor_secret, two_factor_recovery_codes y remember_token de los usuarios. Los exports CSV usan App\Support\CsvExporter::cell() para prefijar con ' las celdas que empiezan por = + - @ \t \r (CSV injection).

## Las tres puertas del rol admin comparten guarda
`update` (quitar `is_admin`), `destroy` y `suspend` pueden dejar la instancia **sin ningún
administrador activo**, y sin instalador que la rescate (ADR-008) eso es irreversible desde
la UI. Las tres pasan por `guardLastAdmin()`, y `update` además impide que un admin se quite
el rol a sí mismo (`guardAdminRoleChange()`). Si añades otra vía que pueda revocar o
inhabilitar a un admin —un borrado en lote, un import— pásala por la misma guarda.
Ver `tests/Feature/Admin/AdminRoleGuardsTest.php`.

## El backup del panel debe cubrir TODAS las tablas
`SystemController::backup()` se llama "backup" pero es un `json_encode` a mano: cada tabla
nueva hay que añadirla ahí explícitamente. Omitió durante un tiempo ingresos, recibos de
ingresos, items de gasto, metas, aportes y pagos recurrentes — un restore desde ese fichero
perdía datos en silencio. Nunca incluir `password`, `two_factor_secret`,
`two_factor_recovery_codes` ni `remember_token`. Hay dos tests en
`tests/Feature/HardeningTest.php` que vigilan ambas cosas.
