---
paths:
  - routes/api.php
---

# Routes

## API autenticada con throttle y tokens con expiración
El grupo auth:sanctum de routes/api.php lleva throttle:api además de auth:sanctum. Los personal access tokens se crean con expiración (now()->addDays(90)). CORS se restringe via config/cors.php a CORS_ALLOWED_ORIGINS (env), con supports_credentials=false.
