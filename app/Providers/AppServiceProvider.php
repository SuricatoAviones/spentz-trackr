<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureDefaults();
        $this->configureRateLimiters();
        $this->configureApiDocsAccess();
    }

    /**
     * Expose the Swagger UI (Scramble) outside local dev, except in production.
     */
    protected function configureApiDocsAccess(): void
    {
        Gate::define('viewApiDocs', fn (?User $user): bool => ! app()->isProduction());

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

        RateLimiter::for('install', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
