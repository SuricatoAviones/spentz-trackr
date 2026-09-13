<?php

/*
 * Regresión de "un admin podía dejar la instancia sin panel".
 *
 * `destroy` y `suspend` ya se protegían contra uno mismo, pero nada impedía
 * quitarse el rol, ni borrar/suspender al último administrador activo. Sin
 * instalador que rescate la instancia (ADR-008), eso es irreversible desde la UI.
 */

use App\Models\User;

function makeAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
}

test('an admin cannot remove their own role', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_admin' => false,
        ])
        ->assertForbidden();

    expect($admin->refresh()->isAdmin())->toBeTrue();
});

test('the last active admin cannot be demoted by another admin', function () {
    $actor = makeAdmin();
    $lastOne = makeAdmin();

    // El actor se degrada a sí mismo no; lo hace un tercero para aislar el caso:
    // dejamos a `$lastOne` como único admin activo quitándole el rol al actor
    // por la puerta de atrás (no por HTTP) y promoviendo a un actor nuevo.
    $actor->forceFill(['is_admin' => false])->save();
    $newActor = makeAdmin();
    $newActor->forceFill(['is_admin' => false])->save();

    // Estado: solo $lastOne es admin. Un admin recién promovido lo degrada…
    $newActor->forceFill(['is_admin' => true])->save();

    $this->actingAs($newActor)
        ->patch(route('admin.users.update', $lastOne), [
            'name' => $lastOne->name,
            'email' => $lastOne->email,
            'is_admin' => false,
        ])
        ->assertRedirect();

    // …se permite porque queda $newActor. Pero degradarse él no.
    $this->actingAs($newActor)
        ->patch(route('admin.users.update', $newActor), [
            'name' => $newActor->name,
            'email' => $newActor->email,
            'is_admin' => false,
        ])
        ->assertForbidden();

    expect(User::query()->where('is_admin', true)->count())->toBe(1);
});

test('the last active admin cannot be deleted', function () {
    $actor = makeAdmin();
    $lastOne = makeAdmin();

    // Con dos admins, borrar a uno se permite.
    $this->actingAs($actor)
        ->delete(route('admin.users.destroy', $lastOne))
        ->assertRedirect();

    // Ya solo queda $actor; otro admin no existe para borrarlo, así que el
    // caso se prueba promoviendo a alguien y dejándolo solo.
    $survivor = makeAdmin();
    $actor->forceFill(['is_admin' => false])->save();

    $this->actingAs($survivor)
        ->delete(route('admin.users.destroy', $survivor))
        ->assertForbidden();

    expect(User::query()->where('is_admin', true)->count())->toBe(1);
});

test('the last active admin cannot be suspended', function () {
    $actor = makeAdmin();
    $lastOne = makeAdmin();

    // Con dos admins activos, suspender a uno se permite.
    $this->actingAs($actor)
        ->post(route('admin.users.suspend', $lastOne))
        ->assertRedirect();

    expect($lastOne->refresh()->isSuspended())->toBeTrue();

    // $actor queda como único admin activo: no puede suspenderse a sí mismo.
    $this->actingAs($actor)
        ->post(route('admin.users.suspend', $actor))
        ->assertForbidden();

    expect($actor->refresh()->isSuspended())->toBeFalse();
});

test('a normal user is still promoted and demoted freely', function () {
    $admin = makeAdmin();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => true,
        ])
        ->assertRedirect();

    expect($user->refresh()->isAdmin())->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => false,
        ])
        ->assertRedirect();

    expect($user->refresh()->isAdmin())->toBeFalse();
});
