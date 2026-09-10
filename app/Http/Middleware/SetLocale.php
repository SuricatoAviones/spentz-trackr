<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve the request locale: user preference, then session, then the app
     * default (Spanish-first product), with the browser language as a
     * defensive fallback.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $availableLocales = config('app.available_locales');

        $locale = $request->user()->locale
            ?? $request->session()->get('locale')
            ?? config('app.locale')
            ?? $request->getPreferredLanguage($availableLocales);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
