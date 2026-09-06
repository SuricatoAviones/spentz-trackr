---
paths:
  - 'app/Http/Controllers/Admin/**'
---

# Admin

## Panel admin: rutas en routes/admin.php protegidas por middleware admin
El panel admin vive bajo el prefijo /admin (routes/admin.php) con los middleware auth+verified+admin (alias EnsureUserIsAdmin, 403 para no admins). Sus controladores cruzan usuarios a propósito (sin scoping forUser) pero nunca deben filtrar datos de negocio fuera del grupo admin. El admin no puede eliminarse a sí mismo (403 en destroy).

## Backup y export CSV sin datos sensibles ni fórmulas
SystemController::backup excluye password, two_factor_secret, two_factor_recovery_codes y remember_token de los usuarios. Los exports CSV usan App\Support\CsvExporter::cell() para prefijar con ' las celdas que empiezan por = + - @ \t \r (CSV injection).
