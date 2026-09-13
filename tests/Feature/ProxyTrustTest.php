<?php

/*
 * Regresión del hallazgo de seguridad "TRUSTED_PROXIES por defecto en *".
 *
 * Con `*`, todo cliente era un proxy de confianza: bastaba una cabecera para
 * (a) reescribir el host de los enlaces que se mandan por correo — el de
 * recuperación de contraseña incluido — y (b) falsificar la IP con la que se
 * indexan los limitadores de login.
 */

test('forwarded host headers cannot move the host of generated urls', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->withHeaders([
            'X-Forwarded-Host' => 'evil.test',
            'X-Forwarded-Proto' => 'https',
        ])
        ->get('/login')
        ->assertOk();

    expect(url('/'))->not->toContain('evil.test')
        ->and(route('password.request'))->not->toContain('evil.test');
});

test('forwarded for headers cannot forge the client ip used by rate limiters', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.77'])
        ->get('/login')
        ->assertOk();

    expect(request()->ip())->toBe('10.0.0.9');
});

test('no proxy is trusted unless TRUSTED_PROXIES names one', function () {
    $this->get('/login')->assertOk();

    expect(request()::getTrustedProxies())->toBe([]);
});
