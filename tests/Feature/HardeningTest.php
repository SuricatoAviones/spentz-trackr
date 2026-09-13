<?php

/*
 * Regresiones de los hallazgos de severidad baja de la auditoría.
 */

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('the rate sync endpoint is throttled', function () {
    Http::fake(['ve.dolarapi.com/*' => Http::response([
        ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
    ])]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    // El límite es 6/min: la séptima debe rebotar.
    foreach (range(1, 6) as $ignored) {
        $this->post(route('exchange-rate.sync'))->assertRedirect();
    }

    $this->post(route('exchange-rate.sync'))->assertStatus(429);
});

test('the api login takes the same path whether the email exists or not', function () {
    // No se mide el reloj (frágil en CI); se comprueba que el mensaje y el
    // código no distinguen entre "no existe" y "contraseña incorrecta".
    $user = User::factory()->create(['password' => 'Clave-Segura-2026!']);

    $missing = $this->postJson(route('api.v1.auth.login'), [
        'email' => 'no-existe@example.com',
        'password' => 'Clave-Segura-2026!',
    ]);

    $wrongPassword = $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'Otra-Clave-Distinta-9!',
    ]);

    $missing->assertStatus(422);
    $wrongPassword->assertStatus(422);

    expect($missing->json('errors.email'))->toBe($wrongPassword->json('errors.email'));
});

test('inertia shares an explicit user shape, never the whole model', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'min_commission' => 14,
        'commission_rate' => 0.3,
        'monthly_budget' => 500,
    ]);

    $props = $this->actingAs($user)->get(route('dashboard'))->viewData('page')['props'];

    expect(array_keys($props['auth']['user']))->toEqualCanonicalizing([
        'id', 'name', 'email', 'is_admin', 'email_verified_at',
        'two_factor_enabled', 'tracking_type', 'created_at', 'updated_at',
    ]);
});

test('the admin backup carries every table a restore needs', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

    $payload = json_decode(
        $this->actingAs($admin)->post(route('admin.system.backup'))->streamedContent(),
        associative: true,
    );

    expect(array_keys($payload))->toContain(
        'users', 'categories', 'payment_sources', 'exchange_rates',
        'expenses', 'expense_items', 'receipts',
        'incomes', 'income_receipts',
        'savings_goals', 'savings_contributions', 'recurring_payments',
    );
});

test('the admin backup never leaks password hashes or 2fa secrets', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

    $payload = json_decode(
        $this->actingAs($admin)->post(route('admin.system.backup'))->streamedContent(),
        associative: true,
    );

    foreach ($payload['users'] as $row) {
        expect($row)->not->toHaveKeys([
            'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token',
        ]);
    }
});
