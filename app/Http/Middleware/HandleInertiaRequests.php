<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'fallbackLocale' => config('app.fallback_locale'),
            'translations' => [
                'messages' => Lang::get('messages'),
                'admin' => Lang::get('admin'),
            ],
            'auth' => [
                // Lista explícita, no el modelo entero. Antes se compartía
                // `$request->user()` tal cual, así que cualquier columna nueva
                // (un token, una nota interna) se habría publicado al cliente en
                // cada página sin que nadie lo decidiera.
                'user' => $request->user() === null ? null : [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'is_admin' => $request->user()->isAdmin(),
                    'email_verified_at' => $request->user()->email_verified_at?->toIso8601String(),
                    'two_factor_enabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
                    'tracking_type' => $request->user()->tracking_type,
                    'created_at' => $request->user()->created_at?->toIso8601String(),
                    'updated_at' => $request->user()->updated_at?->toIso8601String(),
                ],
            ],
            // Para que el login no enlace a un registro cerrado (un 404).
            'features' => Features::all(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
