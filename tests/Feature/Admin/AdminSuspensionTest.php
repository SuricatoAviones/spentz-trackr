<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('an admin can suspend and reactivate a user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $this->actingAs($admin);

    $this->post(route('admin.users.suspend', $target))
        ->assertSessionHas('success');

    expect($target->refresh()->isSuspended())->toBeTrue();

    $this->post(route('admin.users.reactivate', $target))
        ->assertSessionHas('success');

    expect($target->refresh()->isSuspended())->toBeFalse();
});

test('an admin cannot suspend their own account', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $this->post(route('admin.users.suspend', $admin))
        ->assertForbidden();

    expect($admin->refresh()->isSuspended())->toBeFalse();
});

test('suspending an already suspended user returns an error', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['suspended_at' => now()]);
    $this->actingAs($admin);

    $this->post(route('admin.users.suspend', $target))
        ->assertSessionHas('error');
});

test('reactivating a non-suspended user returns an error', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $this->actingAs($admin);

    $this->post(route('admin.users.reactivate', $target))
        ->assertSessionHas('error');
});

test('a suspended user is logged out and blocked from the app', function () {
    $user = User::factory()->create(['suspended_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();

    expect(Auth::check())->toBeFalse();
});

test('suspension blocks access to the admin panel too', function () {
    $admin = User::factory()->create(['is_admin' => true, 'suspended_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});
