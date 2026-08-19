<?php

use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('ajustes shows the manual rate when the user has one', function () {
    ExchangeRate::factory()->manual($this->user->id)->create([
        'rate' => 27.5,
        'rate_date' => now()->toDateString(),
    ]);

    $this->get(route('ajustes'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ajustes')
            ->where('rate.rate', '27.5000')
            ->where('rate.provider', 'user')
            ->where('rate.source', 'manual')
        );
});

test('ajustes shows the API rate when the user has no manual rate', function () {
    ExchangeRate::factory()->create([
        'user_id' => null,
        'source' => 'api',
        'provider' => 'bcv',
        'rate' => 30.0,
        'rate_date' => now()->toDateString(),
    ]);

    $this->get(route('ajustes'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ajustes')
            ->where('rate.rate', '30.0000')
            ->where('rate.provider', 'bcv')
        );
});

test('a user can save a manual rate', function () {
    $this->put(route('exchange-rate.update'), ['rate' => 26.75])
        ->assertSessionHas('success');

    $rate = ExchangeRate::query()->first();

    expect($rate->user_id)->toBe($this->user->id)
        ->and($rate->source)->toBe('manual')
        ->and($rate->provider)->toBe('user')
        ->and($rate->rate)->toBe('26.7500')
        ->and($rate->rate_date->toDateString())->toBe(now()->toDateString());
});

test('a manual rate is preferred over the API rate', function () {
    ExchangeRate::factory()->create([
        'user_id' => null,
        'source' => 'api',
        'provider' => 'bcv',
        'rate' => 30.0,
        'rate_date' => now()->toDateString(),
    ]);

    $this->put(route('exchange-rate.update'), ['rate' => 26.75]);

    $this->get(route('ajustes'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ajustes')
            ->where('rate.provider', 'user')
            ->where('rate.rate', '26.7500')
        );
});

test('an invalid rate is rejected', function () {
    $this->put(route('exchange-rate.update'), ['rate' => 0])
        ->assertSessionHasErrors('rate');

    $this->assertDatabaseCount('exchange_rates', 0);
});

test('sync fetches the rates from dolarapi and persists them', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
            ['moneda' => 'USD', 'fuente' => 'paralelo', 'promedio' => 32.10],
        ]),
    ]);

    $this->post(route('exchange-rate.sync'))
        ->assertSessionHas('success');

    $bcv = ExchangeRate::query()->where('provider', 'bcv')->first();
    $paralelo = ExchangeRate::query()->where('provider', 'paralelo')->first();

    expect($bcv)->not->toBeNull()
        ->and($bcv->user_id)->toBeNull()
        ->and($bcv->source)->toBe('api')
        ->and($bcv->rate)->toBe('31.2500')
        ->and($bcv->rate_date->toDateString())->toBe(now()->toDateString());

    expect($paralelo)->not->toBeNull()
        ->and($paralelo->rate)->toBe('32.1000')
        ->and($paralelo->rate_date->toDateString())->toBe(now()->toDateString());
});

test('sync still parses the legacy associative payload', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            'usd' => ['bcv' => 31.25, 'paralelo' => 32.10],
        ]),
    ]);

    $this->post(route('exchange-rate.sync'))
        ->assertSessionHas('success');

    expect(ExchangeRate::query()->where('source', 'api')->count())->toBe(2);
});

test('sync reports an error when the API returns an unexpected payload', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([['moneda' => 'EUR', 'fuente' => 'oficial', 'promedio' => 1.08]]),
    ]);

    $this->post(route('exchange-rate.sync'))
        ->assertSessionHas('error');

    $this->assertDatabaseCount('exchange_rates', 0);
});

test('sync reports an error when the API is unreachable', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response(null, 500),
    ]);

    $this->post(route('exchange-rate.sync'))
        ->assertSessionHas('error');

    $this->assertDatabaseCount('exchange_rates', 0);
});

test('ajustes shows the monthly expense count', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
            ['moneda' => 'USD', 'fuente' => 'paralelo', 'promedio' => 32.10],
        ]),
    ]);

    $category = Category::factory()->for($this->user)->create();
    $source = PaymentSource::factory()->for($this->user)->create();
    Expense::factory()->for($this->user)->for($category)->for($source, 'paymentSource')
        ->on(now()->format('Y-m').'-10')
        ->create();

    $this->get(route('ajustes'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ajustes')
            ->where('monthlyExpenseCount', 1)
        );
});

test('ajustes auto-syncs the API rate when no rate is stored', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            'usd' => ['bcv' => 31.25, 'paralelo' => 32.10],
        ]),
    ]);

    $this->get(route('ajustes'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ajustes')
            ->where('rate.source', 'api')
            ->where('rate.provider', 'paralelo')
            ->where('rate.rate', '32.1000')
        );

    expect(ExchangeRate::query()->where('source', 'api')->count())->toBe(2);
});

test('ajustes does not auto-sync when a manual rate exists today', function () {
    ExchangeRate::factory()->manual($this->user->id)->create([
        'rate' => 27.5,
        'rate_date' => now()->toDateString(),
    ]);

    $this->get(route('ajustes'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ajustes')
            ->where('rate.provider', 'user')
        );

    $this->assertDatabaseCount('exchange_rates', 1);
});

test('ajustes does not auto-sync when the API rate is from today', function () {
    ExchangeRate::factory()->create([
        'user_id' => null,
        'source' => 'api',
        'provider' => 'bcv',
        'rate' => 30.0,
        'rate_date' => now()->toDateString(),
    ]);

    $this->get(route('ajustes'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ajustes')
            ->where('rate.provider', 'bcv')
        );

    $this->assertDatabaseCount('exchange_rates', 1);
});

test('ajustes auto-syncs when the API rate is stale', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            'usd' => ['bcv' => 31.25, 'paralelo' => 32.10],
        ]),
    ]);

    ExchangeRate::factory()->create([
        'user_id' => null,
        'source' => 'api',
        'provider' => 'bcv',
        'rate' => 30.0,
        'rate_date' => now()->subDay()->toDateString(),
    ]);

    $this->get(route('ajustes'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ajustes')
            ->where('rate.provider', 'paralelo')
            ->where('rate.rate', '32.1000')
        );

    $this->assertDatabaseCount('exchange_rates', 3);
});

test('ajustes keeps working when the auto-sync fails', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response(null, 500),
    ]);

    $this->get(route('ajustes'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ajustes')
            ->where('rate.source', 'none')
        );

    $this->assertDatabaseCount('exchange_rates', 0);
});
