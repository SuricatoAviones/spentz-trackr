<?php

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('a user can fetch today rates via the API', function () {
    ExchangeRate::factory()->create([
        'user_id' => null,
        'source' => 'api',
        'provider' => 'bcv',
        'rate' => 36.5,
        'rate_date' => now()->toDateString(),
    ]);

    $this->getJson(route('api.v1.rates.index'))
        ->assertOk()
        ->assertJsonPath('data.rates.bcv', '36.5000');
});

test('a user can save a manual rate via the API', function () {
    $this->putJson(route('api.v1.rates.update'), ['rate' => 37.25])
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('exchange_rates', [
        'user_id' => $this->user->id,
        'source' => 'manual',
        'provider' => 'user',
        'rate' => '37.2500',
    ]);
});

test('a manual rate above zero is required via the API', function () {
    $this->putJson(route('api.v1.rates.update'), ['rate' => 0])
        ->assertStatus(422);
});

test('the sync endpoint stores API rates and returns success', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 36.5],
            ['moneda' => 'USD', 'fuente' => 'paralelo', 'promedio' => 38.2],
        ]),
    ]);

    $this->postJson(route('api.v1.rates.sync'))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('exchange_rates', [
        'user_id' => null,
        'source' => 'api',
        'provider' => 'bcv',
        'rate' => '36.5000',
    ]);
});

test('the sync endpoint returns 503 when the API is unavailable', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response(status: 500),
    ]);

    $this->postJson(route('api.v1.rates.sync'))
        ->assertStatus(503)
        ->assertJsonPath('success', false);
});
