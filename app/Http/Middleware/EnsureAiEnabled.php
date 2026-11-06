<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra el asistente de IA cuando el administrador lo apaga.
 *
 * Igual que la API: a un cliente JSON se le responde 503 con mensaje (el
 * servicio existe y está apagado, no se equivocó de ruta). Una página web, en
 * cambio, da 404: el menú ya no la enseña y no hay nada que anunciar.
 *
 * No borra las claves guardadas: al volver a encenderlo todo sigue igual.
 */
class EnsureAiEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Features::aiEnabled()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => __('messages.ai_disabled'),
                'reason' => 'disabled',
            ], 503);
        }

        abort(404);
    }
}
