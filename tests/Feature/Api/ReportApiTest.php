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

test('the annual report returns monthly and annual data via the API', function () {
    Expense::factory()->for($this->user)->for($this->category)->for($this->source)->create([
        'amount' => 120,
        'usd_amount' => 120,
        'usdt_amount' => 120,
        'spent_at' => now()->startOfYear()->addMonths(1)->toDateString(),
    ]);

    $this->getJson(route('api.v1.reports.index', ['year' => now()->year]))
        ->assertOk()
        ->assertJsonPath('data.showExpenses', true)
        ->assertJsonPath('data.year', now()->year)
        ->assertJsonStructure([
            'data' => [
                'year',
                'years',
                'annual',
                'incomeAnnual',
                'net',
                'showIncomes',
                'showExpenses',
                'months',
                'incomeMonths',
                'categories',
                'sources',
            ],
        ])
        ->assertJsonPath('data.annual.usd', 120);
});

test('the monthly summary returns a trend via the API', function () {
    $this->getJson(route('api.v1.reports.monthly-summary', ['months' => 3]))
        ->assertOk()
        ->assertJsonCount(3, 'data.trend')
        ->assertJsonStructure(['data' => ['trend']]);
});
