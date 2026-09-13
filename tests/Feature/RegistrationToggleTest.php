<?php

/*
 * Regresión del hallazgo "el registro está siempre abierto y no hay forma de
 * cerrarlo". Con REGISTRATION_ENABLED=false ninguna de las dos puertas —web y
 * API— debe dejar crear cuentas.
 */

use App\Models\User;
use Laravel\Fortify\Features;

test('the api register endpoint is gone when registration is disabled', function () {
    config(['fortify.features' => array_values(array_filter(
        config('fortify.features'),
        fn ($feature) => $feature !== Features::registration(),
    ))]);

    $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Intruso',
        'email' => 'intruso@example.com',
        'password' => 'Clave-Segura-2026!',
        'password_confirmation' => 'Clave-Segura-2026!',
    ])->assertNotFound();

    expect(User::query()->where('email', 'intruso@example.com')->exists())->toBeFalse();
});

test('the api register endpoint still works when registration is enabled', function () {
    $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Nuevo',
        'email' => 'nuevo-toggle@example.com',
        'password' => 'Clave-Segura-2026!',
        'password_confirmation' => 'Clave-Segura-2026!',
    ])->assertCreated();

    expect(User::query()->where('email', 'nuevo-toggle@example.com')->exists())->toBeTrue();
});
