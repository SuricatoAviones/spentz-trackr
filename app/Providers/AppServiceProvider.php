<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Features;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            // Pin every generated URL to APP_URL. Belt to TrustHosts' braces:
            // password-reset and email-verification links are built with
            // `route()`, so they must never inherit a host taken from a
            // request header.
            if (is_string($appUrl = config('app.url')) && $appUrl !== '') {
                URL::useOrigin($appUrl);
            }
        }

        $this->configureDefaults();
        $this->configureRateLimiters();
        $this->configureApiDocsAccess();
    }

    /**
     * Expose the API docs (Scramble) everywhere except production.
     */
    protected function configureApiDocsAccess(): void
    {
        // Con la API apagada tampoco se navega su documentación: enseñar los
        // endpoints de un servicio que devuelve 503 solo confunde.
        Gate::define('viewApiDocs', fn (?User $user): bool => ! app()->isProduction() && Features::apiEnabled());

        Scramble::configure()
            ->expose(
                ui: '/api/v1',
                document: '/api/v1.json',
            );
    }

    /**
     * Configure rate limiters used by the API.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(100)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('api.auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // El botón "sincronizar" dispara una petición saliente a ve.dolarapi.com.
        // Sin techo, un usuario autenticado puede usar la instancia como
        // amplificador contra ese servicio (y cargarse la suya de paso). Las
        // tasas se publican cada pocos minutos: 6/min por usuario sobra.
        RateLimiter::for('rates.sync', fn (Request $request) => Limit::perMinute(6)->by($request->user()?->id ?: $request->ip()));

        // Cada petición a la IA gasta la cuota (y el dinero) del propio usuario
        // en su proveedor y ocupa un worker hasta un minuto. El tope
        // protege a ambos de un bucle en un cliente o de un abuso.
        RateLimiter::for('ai', function (Request $request): array {
            $key = 'ai:'.($request->user()?->id ?: $request->ip());
            $tooMany = fn () => response()->json([
                'success' => false,
                'message' => __('messages.ai_rate_limited_local'),
                'reason' => 'local_rate_limited',
            ], 429);

            return [
                Limit::perMinute(max(1, (int) config('ai.rate_limit.per_minute', 10)))->by($key)->response($tooMany),
                Limit::perDay(max(1, (int) config('ai.rate_limit.per_day', 300)))->by($key.':day')->response($tooMany),
            ];
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // La política aplica SIEMPRE. Antes solo en producción, y como esta app
        // se despliega con un .env escrito a mano, un APP_ENV=local olvidado
        // dejaba pasar contraseñas de un carácter en una instancia pública.
        // Fuera de producción se relaja lo justo para no estorbar en desarrollo:
        // se mantiene la longitud y se omite la consulta a HaveIBeenPwned
        // (que necesita red y ralentiza los tests).
        Password::defaults(function (): Password {
            $rules = Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();

            return app()->isProduction() ? $rules->uncompromised() : $rules;
        });
    }
}
