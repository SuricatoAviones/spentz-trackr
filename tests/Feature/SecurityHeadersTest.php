<?php

/*
 * Regresión del hallazgo "la app no manda ninguna cabecera de seguridad".
 */

use App\Models\User;

test('web responses carry the hardening headers', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'");
});

test('authenticated pages carry them too', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY');
});

test('hsts is only sent over https', function () {
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
});

/*
 * La CSP tiene que dejar pasar el dev server de Vite mientras está caliente.
 * El origen se lee de `public/hot`, que es donde Vite escribe la URL exacta.
 *
 * Y si esa URL es un literal IPv6 (`http://[::1]:5173`, lo que hacía Vite por
 * defecto en Windows) no se manda política: la gramática de CSP no admite
 * corchetes en un host-source, así que ese origen no se puede permitir y la
 * cabecera solo serviría para bloquear los scripts del propio dev server y
 * dejar la app en blanco. `vite.config.ts` fija 127.0.0.1 para evitarlo.
 *
 * Los tests apuntan `public_path()` a un directorio temporal para no tocar el
 * fichero real de quien tenga `composer run dev` levantado.
 */

function withPublicPath(string $path, callable $callback): mixed
{
    $original = app()->publicPath();

    app()->usePublicPath($path);

    try {
        return $callback();
    } finally {
        app()->usePublicPath($original);
    }
}

test('the csp allows the vite dev server origin while it is hot', function () {
    $temp = sys_get_temp_dir().'/spentz-hot-'.uniqid();
    mkdir($temp, recursive: true);
    file_put_contents($temp.'/hot', "http://127.0.0.1:5173\n");

    $csp = withPublicPath($temp, fn () => $this->get('/login')
        ->headers->get('Content-Security-Policy'));

    $directive = fn (string $name) => collect(explode('; ', $csp))
        ->first(fn (string $part) => str_starts_with($part, $name.' '));

    // Scripts, estilos y FUENTES: las tres se sirven desde ese origen en dev.
    foreach (['script-src', 'style-src', 'font-src', 'connect-src'] as $name) {
        expect($directive($name))->toContain('http://127.0.0.1:5173');
    }

    // El websocket del HMR solo hace falta en connect-src.
    expect($directive('connect-src'))->toContain('ws://127.0.0.1:5173')
        ->and($directive('font-src'))->not->toContain('ws://');

    unlink($temp.'/hot');
    rmdir($temp);
});

test('no csp is sent when vite serves from an ipv6 literal', function () {
    $temp = sys_get_temp_dir().'/spentz-ipv6-'.uniqid();
    mkdir($temp, recursive: true);
    file_put_contents($temp.'/hot', "http://[::1]:5173\n");

    $response = withPublicPath($temp, fn () => $this->get('/login'));

    // Una CSP que no puede nombrar ese origen solo rompe la app en desarrollo.
    $response->assertOk()->assertHeaderMissing('Content-Security-Policy');

    // El resto del endurecimiento sigue en su sitio.
    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    unlink($temp.'/hot');
    rmdir($temp);
});

test('the csp carries no dev origin when vite is not hot', function () {
    $temp = sys_get_temp_dir().'/spentz-nohot-'.uniqid();
    mkdir($temp, recursive: true);

    $csp = withPublicPath($temp, fn () => $this->get('/login')
        ->headers->get('Content-Security-Policy'));

    expect($csp)->toContain("font-src 'self' data:;")
        ->and($csp)->not->toContain('http://')
        ->and($csp)->not->toContain('ws://');

    rmdir($temp);
});
