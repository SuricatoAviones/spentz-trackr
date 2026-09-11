<?php

use App\Actions\Expenses\StoreExpenseAction;
use App\Actions\Expenses\UpdateExpenseAction;
use App\Enums\Currency;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\PaymentSource;
use App\Models\User;
use Tests\TestCase;

beforeEach(function () {
    $this->user = User::factory()->create(['min_commission' => 14, 'commission_rate' => 0.30]);
    $this->category = Category::factory()->for($this->user)->create();
    $this->source = PaymentSource::factory()->for($this->user)->create();
});

function expensePayload(array $overrides = []): array
{
    /** @var TestCase $case */
    $case = test();

    return array_merge([
        'category_id' => $case->category->id,
        'payment_source_id' => $case->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 20,
        'description' => 'Test',
        'spent_at' => now()->toDateString(),
    ], $overrides);
}

test('StoreExpenseAction freezes usd/usdt for a USD expense', function () {
    $expense = app(StoreExpenseAction::class)->handle($this->user, expensePayload());

    expect($expense->usd_amount)->toBe('20.00')
        ->and($expense->usdt_amount)->toBe('20.00')
        ->and($expense->exchange_rate)->toBeNull();
});

test('StoreExpenseAction adds the Bs commission before converting', function () {
    $expense = app(StoreExpenseAction::class)->handle($this->user, expensePayload([
        'currency' => Currency::Ves->value,
        'amount' => 280,
        'exchange_rate' => 28,
        'payment_method' => 'pago_movil',
        'commission' => 14,
    ]));

    // (280 + 14) / 28 = 10.50
    expect($expense->amount)->toBe('280.00')
        ->and($expense->commission)->toBe('14.00')
        ->and($expense->usd_amount)->toBe('10.50');
});

test('StoreExpenseAction falls back to the stored day rate for Bs', function () {
    ExchangeRate::query()->create([
        'user_id' => null, 'source' => 'api', 'provider' => 'bcv',
        'rate' => 40, 'rate_date' => now()->toDateString(),
    ]);

    $expense = app(StoreExpenseAction::class)->handle($this->user, expensePayload([
        'currency' => Currency::Ves->value,
        'amount' => 400,
    ]));

    expect($expense->exchange_rate)->toBe('40.0000')
        ->and($expense->usd_amount)->toBe('10.00');
});

test('UpdateExpenseAction recomputes totals and replaces items', function () {
    $expense = app(StoreExpenseAction::class)->handle($this->user, expensePayload([
        'amount' => 10,
        'items' => [['currency' => Currency::Usd->value, 'amount' => 5]],
    ]));

    app(UpdateExpenseAction::class)->handle($this->user, $expense, expensePayload([
        'amount' => 12,
        'items' => [['currency' => Currency::Ves->value, 'amount' => 56, 'exchange_rate' => 28]],
    ]));

    $expense->refresh();

    expect($expense->items)->toHaveCount(1)
        ->and($expense->usd_amount)->toBe('14.00'); // 12 + 56/28
});
