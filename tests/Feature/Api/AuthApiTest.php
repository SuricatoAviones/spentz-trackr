<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('a guest can register via the API and receives a token', function () {
    $response = $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Nuevo Usuario',
        'email' => 'nuevo@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => ['token', 'token_id', 'user' => ['id', 'name', 'email']],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'nuevo@example.com',
    ]);

    $user = User::query()->where('email', 'nuevo@example.com')->firstOrFail();

    expect($user->categories()->count())->toBeGreaterThan(0)
        ->and($user->paymentSources()->count())->toBeGreaterThan(0);
});

test('registration fails with invalid payload via the API', function () {
    $this->postJson(route('api.v1.auth.register'), [
        'email' => 'not-an-email',
    ])->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

test('a user can log in via the API and receives a token', function () {
    $user = User::factory()->create(['password' => 'password']);

    $response = $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'tests',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => ['token', 'user' => ['id', 'email']],
        ]);

    expect($user->tokens()->count())->toBe(1);
});

test('login with wrong credentials returns 422', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

test('a suspended user cannot log in via the API', function () {
    $user = User::factory()->create(['password' => 'password', 'suspended_at' => now()]);

    $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(403)
        ->assertJsonPath('success', false);
});

test('the token user can fetch their profile via me', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.id', $user->id);
});

test('a user can log out revoking the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('tests');

    $this->withToken($token->plainTextToken)
        ->deleteJson(route('api.v1.auth.logout'))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->tokens()->count())->toBe(0);
});

test('protected endpoints reject missing tokens', function () {
    $this->getJson(route('api.v1.dashboard'))
        ->assertStatus(401);
});

test('a suspended token holder is rejected with 403', function () {
    $user = User::factory()->create(['suspended_at' => now()]);
    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.auth.me'))
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});
