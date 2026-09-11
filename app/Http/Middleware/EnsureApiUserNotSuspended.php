<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUserNotSuspended
{
    /**
     * Reject suspended users with a 403 JSON response, API-flavored
     * counterpart of EnsureUserNotSuspended (which relies on sessions).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum') ?? $request->user();

        if ($user !== null && $user->isSuspended()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.account_suspended'),
            ], 403);
        }

        return $next($request);
    }
}
