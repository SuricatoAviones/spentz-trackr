# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Spentz Trackr** — self-hostable, multi-currency personal finance tracker (USD / Bs / USDT) aimed at Venezuela. Laravel 13 (PHP 8.3+) + Inertia v3 + React 19 + Tailwind 4 + shadcn/ui. Ships a web/CLI/Docker installer. Product docs (Spanish) live in `docs/` — `docs/README.md` is the index; **read `docs/04-arquitectura.md` before implementing new features** (contains the ADRs).

## Rules system — read before editing (`.ai/rules/`)

Committed, path-scoped rules encode settled decisions and non-obvious traps. Before planning or editing any file:

1. Open `.ai/rules/index.md` — it maps path globs to rule files.
2. Read every rule file whose globs cover the paths in scope.
3. `grep -rin '<keyword>' .ai/rules` to catch what a path match misses.

`AGENTS.md` holds the Laravel Boost guidelines (also load-bearing). Record new durable rules via the Boost `record-rule` MCP tool, never in a scratch note.

Domain skills live in `.cursor/skills/**` and `.agents/skills/**` — activate the relevant one when working in that domain.

## Commands

```bash
composer run dev          # serve + queue:listen + vite, concurrently (primary dev loop)
npm run build             # build frontend assets (REQUIRED before Inertia page tests if pages changed)
php artisan wayfinder:generate --with-form   # regenerate TS route helpers after route changes

# Tests (Pest)
php artisan test --compact
php artisan test --compact --filter=SomeTest
php artisan make:test --pest SomeFeatureTest        # feature; add --unit for unit

# Lint / static analysis
vendor/bin/pint --dirty --format agent    # PHP formatter — run after editing any .php file
npm run lint / npm run format             # JS/TS eslint + prettier (resources/)
npm run types:check                       # tsc --noEmit
composer run types:check                  # phpstan (larastan) level 7
composer run ci:check                     # full gate: eslint + prettier + tsc + phpstan + pint + phpunit

# Installer / admin (also see docs/11-instalador.md)
php artisan app:install    # headless install (--db-*, --admin-*, --force flags)
php artisan app:update
php artisan admin:create   # idempotent; reads ADMIN_NAME/EMAIL/PASSWORD from .env
```

Tests run on in-memory SQLite with `SESSION_DRIVER=array` / `QUEUE_CONNECTION=sync` (`phpunit.xml`); dev defaults to file SQLite, prod to MySQL/PostgreSQL (driver chosen by `.env`). Two recurring test traps: (1) Symfony injects `Accept-Language: en` into every test request, so browser-locale logic resolves `en` unless you assert against the Spanish-first fallback chain; (2) avoid engine-specific SQL — `YEAR()` / `DATE()` don't exist in SQLite; use `substr(spent_at, 1, 4)` and `whereDate()` for portable MySQL/SQLite queries.

## Architecture

**Everything is wired in `bootstrap/app.php`** (Laravel 11+ style — there is no `app/Http/Kernel.php`, no `app/Console/Kernel.php`, no `ScheduleServiceProvider`). That one file registers all route files (`web`, `api`, `admin`, `install`, `settings`, `console`), the middleware stack and its order, and the scheduler (`SyncExchangeRates` → `everyFiveMinutes`).

**Auth is Laravel Fortify** (not Breeze) + passkeys (`@laravel/passkeys` / `@laravel/multiplex`). Registration/reset logic lives in `app/Actions/Fortify/`; shared validation in `app/Concerns/{PasswordValidationRules,ProfileValidationRules}`. No hand-written auth controllers.

**Currency conversion is frozen per transaction.** Every `Expense`/`Income` persists `exchange_rate`, `usd_amount`, and `usdt_amount` at write time (USDT treated 1:1 with USD). Reports **never** recalculate with the current rate. `ExpenseConversionService` does the math; controllers stay thin (validation in Form Requests).

**Exchange rates** come from `https://ve.dolarapi.com/v1/dolares` via `SyncExchangeRates` job on the scheduler (every 5 min). The expense form only reads the last persisted rate — never makes an inline HTTP call. In dev the scheduler doesn't run, so page-load controllers call `ensureFreshRate($user)` — do not remove it. Users can always override the rate manually.

**Bs commission model:** for pago móvil / transferencia expenses, commission = `max(min_commission, amount × rate%)` (defaults configurable in Ajustes). `expenses.amount` always stores the base; `commission` is a separate column; USD/USDT equivalents are computed on `amount + commission`.

**User scoping:** all business-data queries filter by the authenticated `user_id`, enforced by resource policies (`ExpensePolicy`, `CategoryPolicy`, etc.).

**Admin panel:** `routes/admin.php` under `/admin`, middleware `auth` + `verified` + `admin` (`EnsureUserIsAdmin`, 403 for non-admins). Role is a `users.is_admin` boolean — deliberately **not** in `$fillable` (assigned only via `forceFill` in trusted actions). Admin controllers intentionally cross users; they must never leak business data outside the admin group. Admin cannot delete/suspend itself.

**REST API:** `routes/api.php` under `/api/v1`, `auth:sanctum` + `throttle:api`, tokens expire (90 days), CORS locked to `CORS_ALLOWED_ORIGINS`. Controllers in `app/Http/Controllers/Api/V1/`. Docs generated by Scramble (config `config/scramble.php`, docs assets under `docs/api/`).

**CSV/JSON exports** (reports, backups, receipts) stream via `app/Support/CsvExporter` and `Storage::disk()->response()`, which sends `Content-Disposition: inline` — **not** `attachment`. In tests assert with `$response->streamedContent()` + `assertHeader('content-type', ...)`, never `assertDownload`/`assertSee`.

**Tracking features toggle:** `EnsureTrackingFeature:<feature>` middleware gates optional modules (incomes, expenses, savings goals, recurring payments) per user preference.

**Installer:** `InstallController` (routes `routes/install.php`) aborts 404 in production and 403 once `storage/installed` exists. `APP_INSTALL_MODE=wizard` (cPanel, keeps `/install`) vs `headless` (Docker, installs from env, blocks `/install`).

**Middleware order:** `EnsureInstalled` is prepended globally; `SetLocale` + `HandleInertiaRequests` + `EnsureUserNotSuspended` are appended to the `web` group (so suspension check runs before route middleware, including `admin`).

**i18n (ES/EN):** backend uses Laravel `lang/{es,en}` + `__()`; frontend uses `react-i18next` with `resources/js/i18n/{es,en}.json` shipped via Inertia shared props (`translations`) — no extra request. Locale resolution: user → session → `APP_LOCALE` (es) → browser. `es.json` and `en.json` must stay key-identical — `tests/Unit/I18nDictionaryTest.php` enforces it. App is Spanish-first.

**Frontend:** Inertia pages in `resources/js/pages/`, forms with `useForm`, navigation via Wayfinder. Import route helpers from the **grouped** module (`@/routes/expenses`), never `@/routes` root. No Blade for app screens.
