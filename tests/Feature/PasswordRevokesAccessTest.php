<?php

/*
 * Regresión del hallazgo "cambiar la contraseña no corta el acceso vivo".
 *
 * Antes, la sesión y los tokens de API emitidos seguían valiendo después del
 * cambio: quien ya estuviera dentro se quedaba dentro, que es justo lo contrario
 * de lo que espera quien cambia su clave porque sospecha de una intrusión.
 */

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('changing your own password revokes every api token', function () {
    $user = User::factory()->create(['password' => 'Contrasena-Vieja-1!']);
    $user->createToken('sesion-movil', ['*'], now()->addDays(90));

    expect($user->tokens()->count())->toBe(1);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'Contrasena-Vieja-1!',
            'password' => 'Contrasena-Nueva-9!',
            'password_confirmation' => 'Contrasena-Nueva-9!',
        ])
        ->assertSessionHasNoErrors();

    expect($user->tokens()->count())->toBe(0);
});

test('changing your own password drops other stored sessions but keeps yours', function () {
    $user = User::factory()->create(['password' => 'Contrasena-Vieja-1!']);
    $other = User::factory()->create();

    // Sesión abierta en otro dispositivo, y la de un tercero que no debe tocarse.
    DB::table('sessions')->insert([
        ['id' => 'sesion-otro-dispositivo', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
        ['id' => 'sesion-de-otro-usuario', 'user_id' => $other->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
    ]);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'Contrasena-Vieja-1!',
            'password' => 'Contrasena-Nueva-9!',
            'password_confirmation' => 'Contrasena-Nueva-9!',
        ])
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('id', 'sesion-otro-dispositivo')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'sesion-de-otro-usuario')->exists())->toBeTrue();
});

test('an admin password reset revokes the target tokens and sessions', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    $target = User::factory()->create();
    $target->createToken('sesion-movil', ['*'], now()->addDays(90));

    DB::table('sessions')->insert([
        'id' => 'sesion-comprometida', 'user_id' => $target->id, 'ip_address' => null,
        'user_agent' => null, 'payload' => '', 'last_activity' => time(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.users.reset-password', $target), [
            'password' => 'Reset-Del-Admin-7!',
            'password_confirmation' => 'Reset-Del-Admin-7!',
        ])
        ->assertSessionHasNoErrors();

    expect($target->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'sesion-comprometida')->exists())->toBeFalse();
});
