<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la API REST cuando el administrador la apaga desde el panel.
 *
 * Devuelve 503 y no 404 a propósito: un 404 haría que quien está integrando
 * creyera que se equivocó de ruta y perdiera la tarde buscando el error. Un 503
 * con mensaje dice la verdad —el servicio existe y está deshabilitado— y es
 * además el código correcto para algo apagado de forma reversible.
 *
 * No toca los tokens: al volver a encender la API siguen valiendo.
 */
class EnsureApiEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Features::apiEnabled()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.api_disabled'),
            ], 503);
        }

        return $next($request);
    }
}
