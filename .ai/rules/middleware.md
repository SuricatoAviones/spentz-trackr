---
paths:
  - app/Http/Middleware/EnsureUserNotSuspended.php
---

# Middleware

## Suspensión: middleware en grupo web y sin auto-suspensión
EnsureUserNotSuspended se registra en el grupo web (append), por lo que corre antes que los middleware de ruta (incluido 'admin'). Un admin suspendido queda bloqueado en toda la app. Cierra sesión (Auth::logout + session invalidate) y abort(403). El admin no puede suspenderse a sí mismo (403 en suspend, igual que destroy).
