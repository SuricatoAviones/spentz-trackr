<?php

use App\Http\Middleware\EnsureApiEnabled;
use App\Http\Middleware\EnsureApiUserNotSuspended;
use App\Http\Middleware\EnsureRegistrationEnabled;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserNotSuspended;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Jobs\SyncExchangeRates;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job(new SyncExchangeRates)->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust NOTHING by default. `*` used to be the default here, which made
        // every client a trusted proxy: anyone could set X-Forwarded-For to
        // forge `$request->ip()` (defeating the login and api.auth throttles)
        // and X-Forwarded-Host to make `url()`/`route()` — and therefore the
        // password-reset link mailed to a victim — point at their own host.
        // Operators behind a real proxy must name it in TRUSTED_PROXIES.
        $trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? '';
        $trustedProxies = is_string($trustedProxies) ? trim($trustedProxies) : '';

        if ($trustedProxies !== '') {
            $middleware->trustProxies(at: $trustedProxies);
        }

        // Second lock on the same door: even a misconfigured proxy list cannot
        // make the app answer on — or generate URLs for — a host we did not
        // publish. The closure is resolved per request (config is not loaded
        // yet here), and Laravel's TrustHosts already stands down in `local`
        // and under tests, so dev over 127.0.0.1/LAN IPs keeps working.
        $middleware->trustHosts(at: static function (): array {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST);

            return is_string($host) && $host !== '' ? [$host] : [];
        }, subdomains: false);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'api.active' => EnsureApiUserNotSuspended::class,
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
            EnsureRegistrationEnabled::class,
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureUserNotSuspended::class,
        ]);

        $middleware->api(prepend: [
            // El primero de todos: si la API está apagada no hay que resolver
            // tokens ni tocar la base de datos para nada más.
            EnsureApiEnabled::class,
            EnsureApiUserNotSuspended::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
