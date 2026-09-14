<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra el registro de usuarios cuando el administrador lo apaga.
 *
 * Va en el grupo `web` y filtra por nombre de ruta en vez de colgarse de las
 * rutas de Fortify: el array `fortify.features` se construye al cargar la
 * configuración —sin base de datos y horneado por `config:cache`—, así que un
 * interruptor en caliente no puede vivir ahí.
 *
 * Devuelve 404 y no 503: una instancia privada no debería anunciar que tiene
 * una puerta de registro cerrada. (La API sí usa 503 para el interruptor
 * general, porque ahí quien integra necesita distinguir "apagado" de "ruta mal
 * escrita".)
 */
class EnsureRegistrationEnabled
{
    /** Rutas que publica Fortify para el alta de usuarios. */
    private const ROUTES = ['register', 'register.store'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();

        if (in_array($name, self::ROUTES, true) && ! Features::registrationEnabled()) {
            abort(404);
        }

        return $next($request);
    }
}
