# 10 — Planificación: Multilenguaje (ES / EN)

Convertir Spentz Trackr en una app **español-inglés** con selector de idioma persistente. Español sigue siendo el idioma por defecto (mercado principal), Inglés como idioma completo de la interfaz.

## 1. Decisiones clave

| Decisión | Opción elegida | Alternativa descartada |
|---|---|---|
| i18n en React | **react-i18next** (interpolación, plurales, estándar) | Helper custom `t()` a mano (menos features, cero deps) |
| Carga de traducciones | **Shared props de Inertia** (`translations` JSON del locale actual en `HandleInertiaRequests`) | i18next-http-backend (petición extra por página) |
| Idioma del backend | `lang/es/` + `lang/en/` de Laravel (`__()`), `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=en` | Sin lang files (strings hardcodeadas) |
| Persistencia | Columna `locale` en `users` (nullable) + fallback sesión + default ES | Solo sesión (se pierde entre dispositivos) |
| Orden de resolución | Usuario → sesión → **default ES** → navegador (fallback defensivo) | Navegador antes que default (rompía tests y hacía la app inglesa por defecto) |
| Meses en gráficas | Devolver índice ISO del mes y formatear en el cliente con `Intl.DateTimeFormat` | `strftime('%b')` (depende del locale del servidor, no traducible) |
| Monedas/números | `Intl.NumberFormat(locale)` en `lib/format` | `number_format` de PHP en el cliente |

## 2. Alcance de strings a traducir

### Backend (PHP → `lang/`)
- Mensajes flash (`success`/`error`) de todos los controladores (user + admin).
- Mensajes de validación (Form Requests + Fortify: login, registro, reset, verificación).
- Páginas 403/404 y títulos (menos crítico).
- Admin: etiquetas de auditoría (`ACTION_LABELS` hoy hardcodeadas en TSX), flash del panel.

### Frontend (React → diccionarios i18next)
- Páginas de auth (login, registro, forgot/reset password).
- App shell: sidebar, header, menú usuario, botones globales.
- Dashboard usuario: totales, gráficas, chips, listas recientes.
- CRUD de gastos: formulario, filtros, diálogos, exportación, comprobantes.
- Categorías y orígenes: listados, formularios, presupuesto.
- Tasas: panel de tasas, ajuste manual, sincronización.
- Ajustes: perfil, contraseña, apariencia, preferencia de idioma.
- Panel admin completo (9 páginas + nav).
- `Head` (títulos de página) con `usePage().props.locale`.

### Datos (NO se traducen)
- Nombres de categorías/orígenes del usuario (son datos, no UI).
- Comprobantes, notas, descripciones de gastos.
- Opcional (fase 2): seed de categorías/orígenes por defecto en el idioma de preferencia al registrarse.

## 3. Fases de implementación

### Fase A — Infraestructura backend
1. Migración: `locale` (string, nullable) en `users`.
2. Middleware `SetLocale` (grupo web, append): locale del usuario autenticado → sesión → default `APP_LOCALE` (es); `App::setLocale()` + `Carbon::setLocale()`.
3. `lang/es/` y `lang/en/`: `messages.php` (flash), `validation.php`, `auth.php` (Fortify), `admin.php` (acciones y panel).
4. Ruta `POST /language` (middleware auth): guarda preferencia en BD o sesión; responde 204 o flash.
5. `HandleInertiaRequests`: compartir `locale`, `fallbackLocale` y `translations` (JSON de `lang/{locale}/messages.php` + diccionario frontend).
6. `.env` / `.env.example`: `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=en`.
7. Tests: middleware por `Accept-Language` y por usuario, endpoint `/language`, mensajes de validación en `es`.

### Fase B — i18n en el frontend (usuario)
1. Instalar `react-i18next` + `i18next`; init en `resources/js/app.tsx` con recursos desde shared props (solo el locale actual, fallback EN).
2. Diccionario base `resources/js/i18n/es.json` + `en.json`.
3. Extraer strings de las páginas de usuario (auth, dashboard, gastos, categorías, orígenes, tasas, ajustes, shell). Keys por dominio: `expenses.create.title`, `common.save`, etc.
4. Reemplazar fechas y montos: `Intl.DateTimeFormat`/`Intl.NumberFormat` con el locale activo (`lib/format.ts`).
5. Meses de gráficas: servidor devuelve `month: '2026-01'` (o índice), cliente formatea con `Intl` — quitar `strftime` de `DashboardController` y `ReportController`.
6. Tests: build + `tsc`, y verificar que no queden strings en español hardcodeadas en páginas de usuario (revisión manual/grep).

### Fase C — Panel admin
1. Traducir las 9 páginas admin + `ACTION_LABELS` (mover a diccionario i18n).
2. Flash y validación del admin ya cubiertos en Fase A (`lang/admin.php`).
3. Tests: smoke de páginas admin en locale `es` y `en`.

### Fase D — Selector de idioma
1. Dropdown EN/ES en el sidebar (junto a apariencia) y en Ajustes → Preferencia de idioma.
2. `POST /language` con Wayfinder; cambio inmediato sin recargar (recargar props de Inertia).
3. Tests de la UI (Pest browser o al menos feature del endpoint + smoke).

### Fase E — QA y cierre
1. Test de consistencia de keys: script/Pest que compara `es.json` vs `en.json` (mismas keys).
2. Revisión visual ES/EN de todas las pantallas (login → admin).
3. Documentación: actualizar `docs/04-arquitectura.md` (ADR multilenguaje), `docs/01-vision-y-alcance.md` (tabla de decisiones), `docs/07-roadmap.md` (Fase 6), `.env.example`.
4. Suite completa en verde (test, Pint, tsc, build).

## 4. Riesgos y trampas

| Riesgo | Mitigación |
|---|---|
| Keys desincronizadas ES/EN | Test de consistencia de keys en Fase E (y CI si existe) |
| `strftime('%b')` devuelve mes en inglés del servidor | Eliminarlo en Fase B; formatear con `Intl` en cliente |
| Strings interpoladas en flash ("Gasto X eliminado") | Usar `__('messages.expense_deleted', ['name' => ...])` con `:name` |
| `formatAmount` con moneda USDT/VES (sin símbolo estándar) | `Intl.NumberFormat` para separadores + símbolo fijo por moneda (USD `$`, USDT `₮`, VES `Bs`) |
| Categorías por defecto en español para usuarios EN | Fase 2 opcional: seed con locale del usuario en registro |
| SSR/SEO | No hay SSR (Inertia client-side); `Head` usa locale compartido |
| Mantener `.env` de prod | `APP_LOCALE` cambia el default; usuario ya puede elegir EN sin tocar server |

## 5. Entregables por fase

| Fase | Entregables |
|---|---|
| A | migración + middleware + lang files + ruta `/language` + shared props + tests |
| B | i18next init + diccionarios + páginas usuario traducidas + Intl en fechas/montos/meses |
| C | páginas admin traducidas + ACTION_LABELS en diccionario |
| D | selector EN/ES en sidebar y ajustes + persistencia |
| E | test de keys + revisión ES/EN + docs actualizadas + suite verde |