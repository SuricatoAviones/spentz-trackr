<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad para todas las respuestas del grupo `web`.
 *
 * La app no mandaba ninguna: se podía embeber en un iframe ajeno (clickjacking
 * sobre formularios que mueven dinero) y nada acotaba de dónde puede cargar
 * scripts el navegador.
 *
 * Sobre la CSP: `script-src` incluye 'unsafe-inline' porque Inertia inyecta el
 * payload de la página en un atributo y Vite sirve módulos en dev; endurecerlo
 * requiere nonces en la plantilla Blade y es un cambio aparte. Aun así, acotar
 * `frame-ancestors`, `object-src` y `base-uri` ya cierra el clickjacking y la
 * inyección de <base>.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
        ];

        // Puede no haber política que mandar: ver contentSecurityPolicy().
        if (($csp = $this->contentSecurityPolicy()) !== null) {
            $headers['Content-Security-Policy'] = $csp;
        }

        // HSTS solo sobre HTTPS: mandarla en claro no sirve de nada y en un
        // despliegue local por HTTP dejaría el dominio clavado a https.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Devuelve `null` cuando no se puede escribir una política correcta, y en
     * ese caso no se manda cabecera ninguna.
     *
     * El único caso es el dev server de Vite publicando en un literal IPv6
     * (`http://[::1]:5173`): la gramática de CSP no admite corchetes ni dos
     * puntos en un host-source, así que ese origen **no se puede permitir**.
     * Mandar la política igualmente no la hace más segura — solo bloquea los
     * scripts y las fuentes del propio dev server y deja la app en blanco.
     * `vite.config.ts` fija `server.host` a 127.0.0.1 para que no pase; esto es
     * la red por si alguien lo cambia o arranca Vite a mano con `--host ::`.
     */
    private function contentSecurityPolicy(): ?string
    {
        [$dev, $devSocket] = $this->viteDevOrigins();

        if (str_contains($dev, '[')) {
            return null;
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'".$dev,
            "style-src 'self' 'unsafe-inline'".$dev,
            'img-src \'self\' data: blob:'.$dev,
            // El directivo @fonts sirve las fuentes desde el dev server de Vite
            // mientras está caliente; en producción salen del build, o sea 'self'.
            "font-src 'self' data:".$dev,
            // El websocket del HMR solo hace falta aquí.
            "connect-src 'self'".$dev.$devSocket,
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * Orígenes que hay que permitir mientras Vite corre en caliente.
     *
     * Se leen de `public/hot`, que es la única fuente fiable: Vite escribe ahí
     * la URL exacta que va a usar el navegador. Adivinarla rompía el arranque —
     * Vite escucha en `http://[::1]:5173` (loopback IPv6) y una lista fija de
     * localhost/127.0.0.1 no lo cubre, así que la CSP bloqueaba los scripts,
     * los estilos y las fuentes, y la app se quedaba en blanco.
     *
     * @return array{0: string, 1: string} El origen http y el del websocket, ya
     *                                     prefijados con un espacio, o dos
     *                                     cadenas vacías en producción.
     */
    private function viteDevOrigins(): array
    {
        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return ['', ''];
        }

        $url = trim((string) file_get_contents($hotFile));

        if ($url === '') {
            return ['', ''];
        }

        // Mismo origen para el websocket del HMR: http → ws, https → wss.
        $socket = str_starts_with($url, 'https://')
            ? 'wss://'.substr($url, 8)
            : 'ws://'.preg_replace('#^https?://#', '', $url);

        return [' '.$url, ' '.$socket];
    }
}
