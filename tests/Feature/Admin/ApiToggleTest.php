<?php

/*
 * Interruptor de la API REST desde el panel de sistema.
 */

use App\Models\AppSetting;
use App\Models\User;
use App\Support\Features;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
});

test('the api answers normally while it is enabled', function () {
    $token = $this->user->createToken('test', ['*'], now()->addDay())->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.email', $this->user->email);
});

test('turning the api off closes every endpoint with a 503', function () {
    Features::setApiEnabled(false);

    $token = $this->user->createToken('test', ['*'], now()->addDay())->plainTextToken;

    // 503 y no 404: quien integra merece saber que el servicio existe y está
    // apagado, en vez de perder la tarde buscando una ruta mal escrita.
    $this->withToken($token)
        ->getJson(route('api.v1.auth.me'))
        ->assertStatus(503)
        ->assertJsonPath('success', false);

    // También las rutas públicas de la API.
    $this->postJson(route('api.v1.auth.login'), [
        'email' => $this->user->email,
        'password' => 'password',
    ])->assertStatus(503);
});

test('turning the api off leaves the web app untouched', function () {
    Features::setApiEnabled(false);

    $this->actingAs($this->user)->get(route('dashboard'))->assertOk();
    $this->actingAs($this->user)->get(route('expenses.index'))->assertOk();
});

test('turning it back on restores the tokens that were already issued', function () {
    $token = $this->user->createToken('test', ['*'], now()->addDay())->plainTextToken;

    Features::setApiEnabled(false);
    $this->withToken($token)->getJson(route('api.v1.auth.me'))->assertStatus(503);

    Features::setApiEnabled(true);

    // Apagar es reversible: no se revoca nada.
    $this->withToken($token)->getJson(route('api.v1.auth.me'))->assertOk();
});

test('an admin can flip the switch from the system panel', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.system.index'))
        ->assertInertia(fn (Assert $page) => $page->where('features.api', true)->etc());

    $this->actingAs($this->admin)
        ->put(route('admin.system.api.update'), ['enabled' => false])
        ->assertRedirect();

    expect(Features::apiEnabled())->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('admin.system.index'))
        ->assertInertia(fn (Assert $page) => $page->where('features.api', false)->etc());
});

test('a normal user cannot flip it', function () {
    $this->actingAs($this->user)
        ->put(route('admin.system.api.update'), ['enabled' => false])
        ->assertForbidden();

    expect(Features::apiEnabled())->toBeTrue();
});

test('flipping the switch is written to the audit log', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.system.api.update'), ['enabled' => false])
        ->assertRedirect();

    $this->assertDatabaseHas('admin_actions', [
        'admin_id' => $this->admin->id,
        'action' => 'api.disabled',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.system.api.update'), ['enabled' => true])
        ->assertRedirect();

    $this->assertDatabaseHas('admin_actions', ['action' => 'api.enabled']);
});

test('the stored preference wins over the env default', function () {
    // El .env fija el arranque; lo que toque un admin manda a partir de ahí.
    config(['features.api' => true]);
    Features::setApiEnabled(false);

    expect(Features::apiEnabled())->toBeFalse();

    config(['features.api' => false]);
    Features::setApiEnabled(true);

    expect(Features::apiEnabled())->toBeTrue();
});

test('with nothing stored the env default decides', function () {
    AppSetting::query()->delete();
    Cache::flush();

    config(['features.api' => false]);
    expect(Features::apiEnabled())->toBeFalse();

    config(['features.api' => true]);
    expect(Features::apiEnabled())->toBeTrue();
});

test('the cached settings map is refreshed on write', function () {
    // Se lee en cada petición de API, así que va a caché: si el write no la
    // invalidara, apagar la API no surtiría efecto hasta que expirase.
    expect(Features::apiEnabled())->toBeTrue();

    Features::setApiEnabled(false);

    expect(Features::apiEnabled())->toBeFalse();
});
