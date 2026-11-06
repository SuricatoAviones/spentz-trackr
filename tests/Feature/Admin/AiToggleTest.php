<?php

/*
 * Interruptor del asistente de IA desde el panel de sistema.
 */

use App\Models\AdminAction;
use App\Models\AiSetting;
use App\Models\User;
use App\Support\Features;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
});

test('an admin can turn the ai off and on', function () {
    AiSetting::factory()->for($this->user)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.system.ai.update'), ['enabled' => false])
        ->assertRedirect();

    expect(Features::aiEnabled())->toBeFalse()
        ->and(AiSetting::query()->count())->toBe(1)
        ->and(AdminAction::query()->where('action', 'ai.disabled')->exists())->toBeTrue();

    $this->actingAs($this->admin)->put(route('admin.system.ai.update'), ['enabled' => true]);

    expect(Features::aiEnabled())->toBeTrue();
});

test('a regular user cannot flip the ai switch', function () {
    $this->actingAs($this->user)
        ->put(route('admin.system.ai.update'), ['enabled' => false])
        ->assertForbidden();

    expect(Features::aiEnabled())->toBeTrue();
});

test('the system page shows the ai status', function () {
    AiSetting::factory()->for($this->user)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.system.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('features.ai', true)
            ->where('ai.connected_users', 1)
            ->where('ai.custom_endpoints', false));
});

test('the env default can start the ai off', function () {
    config(['features.ai' => false]);

    expect(Features::aiEnabled())->toBeFalse();
});
