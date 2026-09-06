<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('guests are redirected to the login page on the budget preference endpoint', function () {
    auth()->logout();

    $this->put(route('budget-preference.update'), [
        'monthly_budget' => 100,
    ])->assertRedirect(route('login'));
});

test('a user can set their global monthly budget', function () {
    $this->put(route('budget-preference.update'), [
        'monthly_budget' => 500,
    ])->assertRedirect()->assertSessionHas('success');

    expect((float) $this->user->refresh()->monthly_budget)->toBe(500.0);
});

test('a user can clear their global monthly budget', function () {
    $this->user->update(['monthly_budget' => 500]);

    $this->put(route('budget-preference.update'), [
        'monthly_budget' => '',
    ])->assertRedirect();

    expect($this->user->refresh()->monthly_budget)->toBeNull();
});

test('a negative monthly budget is rejected', function () {
    $this->put(route('budget-preference.update'), [
        'monthly_budget' => -10,
    ])->assertSessionHasErrors('monthly_budget');

    expect($this->user->refresh()->monthly_budget)->toBeNull();
});

test('the ajustes page exposes the monthly budget and spent', function () {
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
        ->create(['amount' => 80, 'usd_amount' => 80, 'usdt_amount' => 80]);

    $this->user->update(['monthly_budget' => 300]);

    $this->get(route('ajustes'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ajustes')
            ->where('monthlyBudget', '300.00')
            ->where('monthlySpent', 80)
        );
});

test('the dashboard exposes the global monthly budget', function () {
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
        ->create(['amount' => 120, 'usd_amount' => 120, 'usdt_amount' => 120]);

    $this->user->update(['monthly_budget' => 500]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('monthlyBudget', 500)
            ->where('totals.usd', 120)
        );
});

test('the dashboard exposes a null monthly budget when unset', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
            ['moneda' => 'USD', 'fuente' => 'paralelo', 'promedio' => 32.10],
        ]),
    ]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('monthlyBudget', null)
        );
});
