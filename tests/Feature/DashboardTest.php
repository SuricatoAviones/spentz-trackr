<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
            ['moneda' => 'USD', 'fuente' => 'paralelo', 'promedio' => 32.10],
        ]),
    ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard exposes the budgets with the monthly spent', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
            ['moneda' => 'USD', 'fuente' => 'paralelo', 'promedio' => 32.10],
        ]),
    ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $budgeted = Category::factory()->for($user)->create(['budget' => 200]);
    Category::factory()->for($user)->create(['name' => 'Sin presupuesto']);
    $source = PaymentSource::factory()->for($user)->create();
    Expense::factory()->for($user)->for($budgeted)->for($source, 'paymentSource')
        ->on(now()->format('Y-m').'-10')
        ->create(['amount' => 50, 'usd_amount' => 50, 'usdt_amount' => 50]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('budgets', 1)
            ->where('budgets.0.name', $budgeted->name)
            ->where('budgets.0.budget', 200)
            ->where('budgets.0.spent', 50)
        );
});

test('dashboard exposes income totals for income-only tracking', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
            ['moneda' => 'USD', 'fuente' => 'paralelo', 'promedio' => 32.10],
        ]),
    ]);

    $user = User::factory()->create(['tracking_type' => 'income']);
    $this->actingAs($user);

    $incomeCategory = Category::factory()->for($user)->income()->create();
    Income::factory()->for($user)->for($incomeCategory)
        ->on(now()->format('Y-m').'-10')
        ->create(['amount' => 100, 'usd_amount' => 100, 'usdt_amount' => 100]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('trackingType', 'income')
            ->where('incomeTotals.usd', 100)
            ->where('incomeTotals.usdt', 100)
            ->where('incomeCategories.0.id', $incomeCategory->id)
            ->has('recentIncomes', 1)
            ->missing('totals')
            ->missing('recentExpenses')
        );
});

test('dashboard exposes the net balance when tracking both', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
            ['moneda' => 'USD', 'fuente' => 'paralelo', 'promedio' => 32.10],
        ]),
    ]);

    $user = User::factory()->create(['tracking_type' => 'both']);
    $this->actingAs($user);

    $expenseCategory = Category::factory()->for($user)->create();
    $incomeCategory = Category::factory()->for($user)->income()->create();
    $source = PaymentSource::factory()->for($user)->create();
    Expense::factory()->for($user)->for($expenseCategory)->for($source, 'paymentSource')
        ->on(now()->format('Y-m').'-05')
        ->create(['amount' => 50, 'usd_amount' => 50, 'usdt_amount' => 50]);
    Income::factory()->for($user)->for($incomeCategory)
        ->on(now()->format('Y-m').'-10')
        ->create(['amount' => 200, 'usd_amount' => 200, 'usdt_amount' => 200]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('trackingType', 'both')
            ->where('totals.usd', 50)
            ->where('incomeTotals.usd', 200)
            ->where('net.usd', 150)
            ->where('net.usdt', 150)
            ->has('recentExpenses', 1)
            ->has('recentIncomes', 1)
            ->has('recent')
        );
});
