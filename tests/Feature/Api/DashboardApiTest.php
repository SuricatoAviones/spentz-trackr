<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->category = Category::factory()->for($this->user)->create();
    $this->source = PaymentSource::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user);
});

test('the dashboard returns expense data for expense tracking users', function () {
    Expense::factory()->for($this->user)->for($this->category)->for($this->source)->create([
        'amount' => 50,
        'usd_amount' => 50,
        'usdt_amount' => 50,
        'spent_at' => now()->toDateString(),
    ]);

    $this->getJson(route('api.v1.dashboard'))
        ->assertOk()
        ->assertJsonPath('data.mode', 'expenses')
        ->assertJsonPath('data.expenses.totals.usd', 50)
        ->assertJsonStructure([
            'data' => [
                'mode',
                'month',
                'expenses' => ['totals', 'categories', 'sources', 'trend', 'recent'],
            ],
        ]);
});

test('the dashboard includes budget categories', function () {
    $budgetCategory = Category::factory()->for($this->user)->create(['budget' => 100]);

    $this->getJson(route('api.v1.dashboard'))
        ->assertOk()
        ->assertJsonPath('data.expenses.budgets.0.name', $budgetCategory->name)
        ->assertJsonPath('data.expenses.budgets.0.amount', 100);
});

test('the dashboard respects both mode for users tracking income and expenses', function () {
    $user = User::factory()->create(['tracking_type' => 'both']);
    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.dashboard'))
        ->assertOk()
        ->assertJsonPath('data.mode', 'both')
        ->assertJsonStructure(['data' => ['expenses', 'incomes']]);
});
