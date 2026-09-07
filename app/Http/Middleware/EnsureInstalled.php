<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        if ($this->isInstalled()) {
            return $next($request);
        }

        $this->forceInstallSessionConfig();

        if ($request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        return redirect()->route('install.welcome');
    }

    private function forceInstallSessionConfig(): void
    {
        config()->set('session.driver', 'file');
        config()->set('session.secure', false);
        config()->set('cache.default', 'file');
    }

    private function isInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }
}
