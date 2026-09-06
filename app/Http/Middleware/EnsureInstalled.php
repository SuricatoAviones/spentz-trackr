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

        if ($request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        return redirect()->route('install.welcome');
    }

    private function isInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }
}
