---
paths:
  - '**'
  - 'Dockerfile, vite.config.ts'
---

# General

## Planificación del proyecto en docs/
La planificación, casos de uso, modelo de datos, arquitectura y despliegue cPanel viven en docs/ (README.md es el índice). Leer docs/04-arquitectura.md antes de implementar nuevas features. Multi-usuario, solo gastos, sin saldos ni presupuestos en v1.

## Wayfinder in Docker: generate types in PHP stage, skip with SKIP_WAYFINDER
Wayfinder's Vite plugin always runs `php artisan wayfinder:generate` in buildStart and has no skip flag. The Docker build has no PHP in the node:22-alpine frontend stage, so: (1) a `wayfinder` stage (same serversideup/php image) runs `php artisan wayfinder:generate --with-form`; (2) generated `resources/js/{actions,routes,wayfinder}` are copied into the frontend stage; (3) `vite.config.ts` maps the plugin `command` to `node -e 0 --` when `SKIP_WAYFINDER=1` (set only in the frontend Docker stage). Don't try to remove the plugin or the command from the config because local `npm run dev` needs auto-regeneration.
