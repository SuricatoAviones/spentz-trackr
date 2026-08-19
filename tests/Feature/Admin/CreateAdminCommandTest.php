<?php

use App\Models\User;

beforeEach(function () {
    config([
        'admin.name' => 'Administrador',
        'admin.email' => 'admin@spenttrackr.com',
        'admin.password' => 'password-segura-123',
    ]);
});

test('admin:create creates the admin user with configured credentials', function () {
    $this->artisan('admin:create')->assertSuccessful();

    $admin = User::query()->where('email', 'admin@spenttrackr.com')->firstOrFail();

    expect($admin->name)->toBe('Administrador')
        ->and($admin->isAdmin())->toBeTrue()
        ->and(Hash::check('password-segura-123', $admin->password))->toBeTrue();
});

test('admin:create is idempotent and does not duplicate the admin', function () {
    $this->artisan('admin:create')->assertSuccessful();
    $this->artisan('admin:create')->assertSuccessful();

    expect(User::query()->where('email', 'admin@spenttrackr.com')->count())->toBe(1);
});

test('admin:create promotes an existing user to admin', function () {
    $user = User::factory()->create(['email' => 'admin@spenttrackr.com', 'is_admin' => false]);

    $this->artisan('admin:create')->assertSuccessful();

    expect($user->refresh()->isAdmin())->toBeTrue();
});

test('admin:create generates a random password when ADMIN_PASSWORD is missing', function () {
    config(['admin.password' => null]);

    $this->artisan('admin:create')
        ->expectsOutputToContain('se generó una contraseña aleatoria')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'admin@spenttrackr.com')->firstOrFail();

    expect(Hash::needsRehash($admin->password))->toBeFalse();
});
