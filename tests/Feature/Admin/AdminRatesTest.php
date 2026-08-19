<?php

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'name' => 'Admin Principal',
        'email' => 'admin@example.com',
    ]);
    $this->actingAs($this->admin);
});

test('admin rates index shows today API rates, manual overrides and history', function () {
    ExchangeRate::factory()->create([
        'user_id' => null,
        'source' => 'api',
        'provider' => 'bcv',
        'rate' => 30.0,
        'rate_date' => now()->toDateString(),
    ]);
    ExchangeRate::factory()->create([
        'user_id' => null,
        'source' => 'api',
        'provider' => 'paralelo',
        'rate' => 32.5,
        'rate_date' => now()->toDateString(),
    ]);

    $user = User::factory()->create(['name' => 'Ana Pérez']);
    ExchangeRate::factory()->manual($user->id)->create([
        'rate' => 28.0,
        'rate_date' => now()->toDateString(),
    ]);

    ExchangeRate::factory()->create([
        'user_id' => null,
        'source' => 'api',
        'provider' => 'bcv',
        'rate' => 29.0,
        'rate_date' => now()->subDay()->toDateString(),
    ]);

    $this->get(route('admin.rates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rates/index')
            ->where('today.bcv', '30.0000')
            ->where('today.paralelo', '32.5000')
            ->has('manualToday', 1)
            ->where('manualToday.0.user.name', 'Ana Pérez')
            ->where('manualToday.0.rate', '28.0000')
            ->has('history', 4));
});

test('an admin can override the rate of the day for bcv and paralelo', function () {
    Cache::put('exchange-rate:0:'.now()->toDateString(), ['stale'], 300);

    $this->put(route('admin.rates.update'), [
        'bcv' => 35.5,
        'paralelo' => 36.25,
    ])->assertSessionHas('success');

    $bcv = ExchangeRate::query()->where('provider', 'bcv')->first();
    $paralelo = ExchangeRate::query()->where('provider', 'paralelo')->first();

    expect($bcv)->not->toBeNull()
        ->and($bcv->user_id)->toBeNull()
        ->and($bcv->source)->toBe('api')
        ->and($bcv->rate)->toBe('35.5000')
        ->and($bcv->rate_date->toDateString())->toBe(now()->toDateString());

    expect($paralelo)->not->toBeNull()
        ->and($paralelo->rate)->toBe('36.2500')
        ->and($paralelo->rate_date->toDateString())->toBe(now()->toDateString());

    expect(Cache::has('exchange-rate:0:'.now()->toDateString()))->toBeFalse();
});

test('an admin can update a single provider rate keeping the other', function () {
    ExchangeRate::factory()->create([
        'user_id' => null,
        'source' => 'api',
        'provider' => 'bcv',
        'rate' => 30.0,
        'rate_date' => now()->toDateString(),
    ]);

    $this->put(route('admin.rates.update'), ['paralelo' => 33.0]);

    $paralelo = ExchangeRate::query()->where('provider', 'paralelo')->first();
    $bcv = ExchangeRate::query()->where('provider', 'bcv')->first();

    expect($paralelo)->not->toBeNull()
        ->and($paralelo->rate)->toBe('33.0000')
        ->and($paralelo->rate_date->toDateString())->toBe(now()->toDateString());

    expect($bcv)->not->toBeNull()
        ->and($bcv->rate)->toBe('30.0000');
});

test('an invalid or empty rate override is rejected', function () {
    $this->put(route('admin.rates.update'), ['bcv' => 0])
        ->assertSessionHasErrors('bcv');

    $this->put(route('admin.rates.update'), [])
        ->assertSessionHasErrors(['bcv', 'paralelo']);

    $this->assertDatabaseCount('exchange_rates', 0);
});

test('admin rates sync fetches and persists the rates from dolarapi', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
            ['moneda' => 'USD', 'fuente' => 'paralelo', 'promedio' => 32.10],
        ]),
    ]);

    $this->post(route('admin.rates.sync'))
        ->assertSessionHas('success');

    expect(ExchangeRate::query()->where('source', 'api')->count())->toBe(2);
});

test('admin rates sync reports an error when the API fails', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response(null, 500),
    ]);

    $this->post(route('admin.rates.sync'))
        ->assertSessionHas('error');

    $this->assertDatabaseCount('exchange_rates', 0);
});
