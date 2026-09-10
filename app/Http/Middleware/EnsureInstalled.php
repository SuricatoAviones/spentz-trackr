<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the whole app on installation state and make the wizard self-sufficient:
 * the app must boot and serve `/install` with **no `.env` file and no manual
 * setup**. The wizard writes everything on the "Aplicación" step.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        // Safe in every environment: a no-op when an APP_KEY is already set.
        $this->ensureApplicationKey();

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

    /**
     * Guarantee an APP_KEY so sessions, CSRF and cookie encryption work before
     * (and after) installation. The key is persisted to `storage/app.key`
     * (which lives in the persistent volume on Docker and survives redeploys on
     * shared hosting), so it does not depend on a writable `.env`.
     */
    private function ensureApplicationKey(): void
    {
        if (! empty(config('app.key'))) {
            return;
        }

        $keyFile = storage_path('app.key');

        $key = is_file($keyFile)
            ? trim((string) file_get_contents($keyFile))
            : null;

        if ($key === null || $key === '') {
            $key = 'base64:'.base64_encode(random_bytes(32));
            @file_put_contents($keyFile, $key);
        }

        config()->set('app.key', $key);

        // Rebind the encrypter so EncryptCookies / the session pick up the key
        // even though it was resolved (or is about to be) from stale config.
        $bytes = Str::startsWith($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
        app()->forgetInstance('encrypter');
        app()->singleton('encrypter', fn (): Encrypter => new Encrypter($bytes, config('app.cipher', 'AES-256-CBC')));
    }

    private function forceInstallSessionConfig(): void
    {
        config()->set('session.driver', 'file');
        config()->set('session.secure', false);
        config()->set('session.encrypt', false);
        config()->set('cache.default', 'file');
    }

    private function isInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }
}
