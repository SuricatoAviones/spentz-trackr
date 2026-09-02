<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrackingFeature
{
    /**
     * Abort with 404 when the user's tracking type hides the given feature.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $trackingType = $request->user()->tracking_type;

        $hidden = match ($feature) {
            'expenses' => $trackingType === 'income',
            'incomes' => $trackingType === 'expenses',
            default => false,
        };

        abort_if($hidden, 404);

        return $next($request);
    }
}
