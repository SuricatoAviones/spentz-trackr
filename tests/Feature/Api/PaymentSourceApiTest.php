<?php

use App\Models\PaymentSource;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('a user can list their payment sources via the API', function () {
    PaymentSource::factory()->for($this->user)->create(['name' => 'Efectivo']);

    $this->getJson(route('api.v1.sources.index'))
        ->assertOk()
        ->assertJsonPath('data.sources.0.name', 'Efectivo');
});

test('a user can create a payment source via the API', function () {
    $this->postJson(route('api.v1.sources.store'), [
        'name' => 'Pago móvil',
        'icon' => 'phone',
        'color' => '#123456',
    ])->assertStatus(201)
        ->assertJsonPath('success', true);

    expect(PaymentSource::query()->first()->is_system)->toBeFalse();
});

test('a user can update their payment source via the API', function () {
    $source = PaymentSource::factory()->for($this->user)->create(['name' => 'Viejo']);

    $this->putJson(route('api.v1.sources.update', $source), [
        'name' => 'Nuevo',
        'icon' => 'card',
        'color' => '#654321',
    ])->assertOk()
        ->assertJsonPath('success', true);

    expect($source->fresh()->name)->toBe('Nuevo');
});

test('a user cannot update another user payment source via the API', function () {
    $other = User::factory()->create();
    $source = PaymentSource::factory()->for($other)->create();

    $this->putJson(route('api.v1.sources.update', $source), [
        'name' => 'Hack',
        'icon' => 'x',
        'color' => '#000000',
    ])->assertStatus(403);
});

test('a user can delete their payment source via the API', function () {
    $source = PaymentSource::factory()->for($this->user)->create();

    $this->deleteJson(route('api.v1.sources.destroy', $source))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(PaymentSource::query()->count())->toBe(0);
});
