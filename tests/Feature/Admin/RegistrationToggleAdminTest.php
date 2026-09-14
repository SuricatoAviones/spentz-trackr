<?php

/*
 * Interruptor del registro de usuarios desde el panel de sistema.
 *
 * Antes vivía solo en `REGISTRATION_ENABLED`, y por tanto exigía acceso al
 * servidor. El gate NO puede estar en `config/fortify.php` porque ese array se
 * construye al cargar la configuración —sin base de datos y horneado por
 * `config:cache`—, así que la feature de Fortify queda siempre registrada y el
 * corte lo hace `EnsureRegistrationEnabled`.
 */

use App\Models\User;
use App\Support\Features;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
});

/** @return array<string, string> */
function signupPayload(string $email = 'nuevo@example.com'): array
{
    return [
        'name' => 'Nuevo Usuario',
        'email' => $email,
        'password' => 'Clave-Segura-2026!',
        'password_confirmation' => 'Clave-Segura-2026!',
    ];
}

test('registration works while it is open', function () {
    $this->get(route('register'))->assertOk();
    $this->post(route('register.store'), signupPayload())->assertRedirect();

    expect(User::query()->where('email', 'nuevo@example.com')->exists())->toBeTrue();
});

test('the api endpoint also works while registration is open', function () {
    $this->postJson(route('api.v1.auth.register'), signupPayload('api-abierto@example.com'))
        ->assertCreated();

    expect(User::query()->where('email', 'api-abierto@example.com')->exists())->toBeTrue();
});

test('closing registration hides the web form and rejects the post', function () {
    Features::setRegistrationEnabled(false);

    // 404 y no 503: una instancia privada no anuncia que tiene una puerta.
    $this->get(route('register'))->assertNotFound();
    $this->post(route('register.store'), signupPayload())->assertNotFound();

    expect(User::query()->where('email', 'nuevo@example.com')->exists())->toBeFalse();
});

test('closing registration also shuts the api endpoint', function () {
    Features::setRegistrationEnabled(false);

    // Dejar abierta la puerta de la API haría el interruptor decorativo.
    $this->postJson(route('api.v1.auth.register'), signupPayload('api@example.com'))
        ->assertNotFound();

    expect(User::query()->where('email', 'api@example.com')->exists())->toBeFalse();
});

test('login stops offering the sign up link when registration is closed', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('features.registration', true)->etc());

    Features::setRegistrationEnabled(false);

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('features.registration', false)->etc());
});

test('an admin flips it from the system panel and it is audited', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.system.registration.update'), ['enabled' => false])
        ->assertRedirect();

    expect(Features::registrationEnabled())->toBeFalse();
    $this->assertDatabaseHas('admin_actions', [
        'admin_id' => $this->admin->id,
        'action' => 'registration.disabled',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.system.registration.update'), ['enabled' => true])
        ->assertRedirect();

    expect(Features::registrationEnabled())->toBeTrue();
    $this->assertDatabaseHas('admin_actions', ['action' => 'registration.enabled']);
});

test('a normal user cannot flip it', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->put(route('admin.system.registration.update'), ['enabled' => false])
        ->assertForbidden();

    expect(Features::registrationEnabled())->toBeTrue();
});

test('an admin can still create users while registration is closed', function () {
    Features::setRegistrationEnabled(false);

    // Cerrar el registro público no puede dejar al admin sin forma de dar de
    // alta a nadie: el panel sigue funcionando.
    $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
});

test('closing registration leaves login and password reset alone', function () {
    Features::setRegistrationEnabled(false);

    $this->get(route('login'))->assertOk();
    $this->get(route('password.request'))->assertOk();
});
