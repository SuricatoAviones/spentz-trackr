<?php

use App\Enums\Currency;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->category = Category::factory()->for($this->user)->create();
    $this->source = PaymentSource::factory()->for($this->user)->create();
    $this->actingAs($this->user);
});

test('guests are redirected to the login page', function () {
    auth()->logout();

    $this->get(route('expenses.index'))->assertRedirect(route('login'));
});

test('a user can register an expense in USD', function () {
    $response = $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 25.5,
        'description' => 'Mercado semanal',
        'spent_at' => now()->toDateString(),
    ]);

    $expense = Expense::query()->first();

    $response->assertRedirect(route('expenses.show', $expense));
    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'user_id' => $this->user->id,
        'currency' => Currency::Usd->value,
        'amount' => 25.5,
        'usd_amount' => 25.5,
        'usdt_amount' => 25.5,
        'exchange_rate' => null,
    ]);
});

test('a user can register an expense in Bs freezing the exchange rate', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 140,
        'exchange_rate' => 28.5,
        'description' => 'Pasaje',
        'spent_at' => now()->toDateString(),
    ])->assertRedirect();

    $expense = Expense::query()->first();

    expect($expense->currency)->toBe(Currency::Ves)
        ->and($expense->exchange_rate)->toBe('28.5000')
        ->and($expense->usd_amount)->toBe('4.91')
        ->and($expense->usdt_amount)->toBe('4.91');
});

test('a Bs expense uses the stored day rate when no rate is sent', function () {
    ExchangeRate::factory()->create([
        'user_id' => null,
        'source' => 'api',
        'provider' => 'bcv',
        'rate' => 30.0,
        'rate_date' => now()->toDateString(),
    ]);

    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 300,
        'description' => 'Comida',
        'spent_at' => now()->toDateString(),
    ])->assertRedirect();

    $expense = Expense::query()->first();

    expect($expense->exchange_rate)->toBe('30.0000')
        ->and($expense->rate_provider)->toBe('bcv')
        ->and($expense->usd_amount)->toBe('10.00');
});

test('a Bs expense stores the chosen rate provider', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 140,
        'exchange_rate' => 31.5,
        'rate_provider' => 'bcv',
        'description' => 'Mercado',
        'spent_at' => now()->toDateString(),
    ])->assertRedirect();

    $expense = Expense::query()->first();

    expect($expense->rate_provider)->toBe('bcv')
        ->and($expense->exchange_rate)->toBe('31.5000')
        ->and($expense->usd_amount)->toBe('4.44');
});

test('a Bs expense with a custom rate is stored as custom', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 100,
        'exchange_rate' => 29.9,
        'rate_provider' => 'custom',
        'description' => 'Farmacia',
        'spent_at' => now()->toDateString(),
    ])->assertRedirect();

    $expense = Expense::query()->first();

    expect($expense->rate_provider)->toBe('custom')
        ->and($expense->usd_amount)->toBe('3.34');
});

test('an invalid rate provider is rejected', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 100,
        'exchange_rate' => 29.9,
        'rate_provider' => 'banco-x',
        'description' => 'Invalido',
        'spent_at' => now()->toDateString(),
    ])->assertSessionHasErrors('rate_provider');

    $this->assertDatabaseCount('expenses', 0);
});

test('the expense create page exposes bcv, paralelo and manual rates', function () {
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
        'rate' => 31.5,
        'rate_date' => now()->toDateString(),
    ]);
    ExchangeRate::factory()->manual($this->user->id)->create([
        'rate' => 29.0,
        'rate_date' => now()->toDateString(),
    ]);

    $this->get(route('expenses.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/create')
            ->where('rates.bcv', '30.0000')
            ->where('rates.paralelo', '31.5000')
            ->where('rates.manual', '29.0000')
        );
});

test('a Bs expense without any available rate is rejected', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 300,
        'description' => 'Comida',
        'spent_at' => now()->toDateString(),
    ])->assertSessionHasErrors('exchange_rate');

    $this->assertDatabaseCount('expenses', 0);
});

test('an expense in USDT is stored with 1:1 equivalents', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usdt->value,
        'amount' => 50,
        'description' => 'SuscripciA3n',
        'spent_at' => now()->toDateString(),
    ])->assertRedirect();

    $expense = Expense::query()->first();

    expect($expense->usd_amount)->toBe('50.00')
        ->and($expense->usdt_amount)->toBe('50.00')
        ->and($expense->exchange_rate)->toBeNull();
});

test('expense validation rejects future dates, invalid currencies and foreign categories', function () {
    $otherUser = User::factory()->create();
    $foreignCategory = Category::factory()->for($otherUser)->create();

    $this->post(route('expenses.store'), [
        'category_id' => $foreignCategory->id,
        'payment_source_id' => $this->source->id,
        'currency' => 'eur',
        'amount' => -5,
        'description' => 'Invalido',
        'spent_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors([
        'category_id',
        'currency',
        'amount',
        'spent_at',
    ]);

    $this->assertDatabaseCount('expenses', 0);
});

test('a user can only see their own expenses', function () {
    Expense::factory()->for($this->user)->for($this->category)->for($this->source, 'paymentSource')->create();
    $otherUser = User::factory()->create();
    $foreignExpense = Expense::factory()->for($otherUser)->create();

    $response = $this->get(route('expenses.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('expenses/index')
        ->has('expenses.data', 1)
    );

    expect(Expense::query()->forUser($this->user->id)->count())->toBe(1)
        ->and($foreignExpense->user_id)->not->toBe($this->user->id);
});

test('a user cannot view, edit or delete another user expense', function () {
    $otherUser = User::factory()->create();
    $foreignExpense = Expense::factory()->for($otherUser)->create();

    $this->get(route('expenses.show', $foreignExpense))->assertForbidden();
    $this->get(route('expenses.edit', $foreignExpense))->assertForbidden();
    $this->put(route('expenses.update', $foreignExpense), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Hack',
        'spent_at' => now()->toDateString(),
    ])->assertForbidden();
    $this->delete(route('expenses.destroy', $foreignExpense))->assertForbidden();

    $this->assertDatabaseCount('expenses', 1);
});

test('a user can update an expense and the conversion is recalculated', function () {
    $expense = Expense::factory()
        ->for($this->user)
        ->for($this->category)
        ->for($this->source, 'paymentSource')
        ->create(['amount' => 10, 'usd_amount' => 10, 'usdt_amount' => 10]);

    $this->put(route('expenses.update', $expense), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 100,
        'exchange_rate' => 20,
        'rate_provider' => 'paralelo',
        'description' => 'Actualizado',
        'spent_at' => now()->toDateString(),
    ])->assertRedirect(route('expenses.show', $expense));

    $expense->refresh();

    expect($expense->currency)->toBe(Currency::Ves)
        ->and($expense->exchange_rate)->toBe('20.0000')
        ->and($expense->rate_provider)->toBe('paralelo')
        ->and($expense->usd_amount)->toBe('5.00')
        ->and($expense->usdt_amount)->toBe('5.00');
});

test('a user can delete their own expense', function () {
    $expense = Expense::factory()
        ->for($this->user)
        ->for($this->category)
        ->for($this->source, 'paymentSource')
        ->create();

    $this->delete(route('expenses.destroy', $expense))
        ->assertRedirect(route('expenses.index'));

    $this->assertDatabaseCount('expenses', 0);
});

test('an expense can store a receipt image and remove it', function () {
    Storage::fake('public');

    $expense = Expense::factory()
        ->for($this->user)
        ->for($this->category)
        ->for($this->source, 'paymentSource')
        ->create();

    $this->put(route('expenses.update', $expense), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Con comprobante',
        'spent_at' => now()->toDateString(),
        'receipt' => UploadedFile::fake()->image('comprobante.jpg', 100, 100),
    ])->assertRedirect();

    expect($expense->receipts()->count())->toBe(1);
    Storage::disk('public')->assertExists($expense->receipts()->first()->path);

    $this->get(route('expenses.show', $expense))
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/show')
            ->has('expense.receipts', 1)
            ->where('expense.receipts.0.original_name', 'comprobante.jpg')
        );

    $this->put(route('expenses.update', $expense), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Con comprobante',
        'spent_at' => now()->toDateString(),
        'remove_receipt' => true,
    ])->assertRedirect();

    expect($expense->receipts()->count())->toBe(0);
});

test('the expenses index can be filtered by search, currency and category', function () {
    Expense::factory()
        ->for($this->user)
        ->for($this->category)
        ->for($this->source, 'paymentSource')
        ->create(['description' => 'Mercado', 'currency' => Currency::Usd]);
    Expense::factory()
        ->for($this->user)
        ->for($this->category)
        ->for($this->source, 'paymentSource')
        ->usdt()
        ->create(['description' => 'Netflix']);

    $this->get(route('expenses.index', ['search' => 'mercado']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/index')
            ->has('expenses.data', 1)
            ->where('expenses.data.0.description', 'Mercado')
        );

    $this->get(route('expenses.index', ['currency' => Currency::Usdt->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/index')
            ->has('expenses.data', 1)
            ->where('expenses.data.0.description', 'Netflix')
        );

    $this->get(route('expenses.index', ['category_id' => $this->category->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/index')
            ->has('expenses.data', 2)
        );
});

test('a Bs expense with a mobile payment commission keeps the base amount and the commission apart', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 100,
        'exchange_rate' => 50,
        'payment_method' => 'pago_movil',
        'commission' => 14,
        'description' => 'Recarga',
        'spent_at' => now()->toDateString(),
    ])->assertRedirect();

    $expense = Expense::query()->first();

    expect($expense->amount)->toBe('100.00')
        ->and($expense->payment_method?->value)->toBe('pago_movil')
        ->and($expense->commission)->toBe('14.00')
        ->and($expense->usd_amount)->toBe('2.28')
        ->and($expense->usdt_amount)->toBe('2.28');
});

test('a Bs expense without commission (checked option) stores the base amount', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 100,
        'exchange_rate' => 50,
        'payment_method' => 'transferencia',
        'commission' => 0,
        'description' => 'Mercado',
        'spent_at' => now()->toDateString(),
    ])->assertRedirect();

    $expense = Expense::query()->first();

    expect($expense->amount)->toBe('100.00')
        ->and($expense->payment_method?->value)->toBe('transferencia')
        ->and($expense->commission)->toBe('0.00')
        ->and($expense->usd_amount)->toBe('2.00');
});

test('a commission without a payment method is rejected in Bs', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 100,
        'exchange_rate' => 50,
        'commission' => 14,
        'description' => 'Invalido',
        'spent_at' => now()->toDateString(),
    ])->assertSessionHasErrors('payment_method');

    $this->assertDatabaseCount('expenses', 0);
});

test('payment method and commission are rejected for non-Bs currencies', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'payment_method' => 'pago_movil',
        'commission' => 2,
        'description' => 'Invalido',
        'spent_at' => now()->toDateString(),
    ])->assertSessionHasErrors(['payment_method', 'commission']);

    $this->assertDatabaseCount('expenses', 0);
});

test('a Bs expense recalculates the equivalents when a commission is added', function () {
    $expense = Expense::factory()
        ->for($this->user)
        ->for($this->category)
        ->for($this->source, 'paymentSource')
        ->create([
            'currency' => Currency::Ves,
            'amount' => 100,
            'exchange_rate' => 20,
            'usd_amount' => 5,
            'usdt_amount' => 5,
        ]);

    $this->put(route('expenses.update', $expense), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 100,
        'exchange_rate' => 20,
        'payment_method' => 'transferencia',
        'commission' => 8,
        'description' => 'Actualizado',
        'spent_at' => now()->toDateString(),
    ])->assertRedirect(route('expenses.show', $expense));

    $expense->refresh();

    expect($expense->amount)->toBe('100.00')
        ->and($expense->commission)->toBe('8.00')
        ->and($expense->payment_method?->value)->toBe('transferencia')
        ->and($expense->usd_amount)->toBe('5.40');
});

test('a user can register a mixed expense with a secondary currency item', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Compras mixtas',
        'spent_at' => now()->toDateString(),
        'items' => [
            ['currency' => Currency::Ves->value, 'amount' => 140, 'exchange_rate' => 28],
        ],
    ])->assertRedirect();

    $expense = Expense::query()->first();

    expect($expense->currency)->toBe(Currency::Usd)
        ->and($expense->usd_amount)->toBe('15.00')
        ->and($expense->usdt_amount)->toBe('15.00')
        ->and($expense->items)->toHaveCount(1)
        ->and($expense->items->first()->currency)->toBe(Currency::Ves)
        ->and($expense->items->first()->usd_amount)->toBe('5.00');
});

test('a mixed expense aggregates usd and usdt from multiple items', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usdt->value,
        'amount' => 30,
        'description' => 'Varios ítems',
        'spent_at' => now()->toDateString(),
        'items' => [
            ['currency' => Currency::Usd->value, 'amount' => 20],
            ['currency' => Currency::Ves->value, 'amount' => 200, 'exchange_rate' => 40],
        ],
    ])->assertRedirect();

    $expense = Expense::query()->first();

    expect($expense->usd_amount)->toBe('55.00')
        ->and($expense->usdt_amount)->toBe('55.00')
        ->and($expense->items)->toHaveCount(2);
});

test('updating an expense replaces its mixed items', function () {
    $expense = Expense::factory()
        ->for($this->user)
        ->for($this->category)
        ->for($this->source, 'paymentSource')
        ->create();

    $expense->items()->create([
        'currency' => Currency::Usd,
        'amount' => 5,
        'usd_amount' => 5,
        'usdt_amount' => 5,
    ]);

    $this->put(route('expenses.update', $expense), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 12,
        'description' => 'Actualizado mixto',
        'spent_at' => now()->toDateString(),
        'items' => [
            ['currency' => Currency::Ves->value, 'amount' => 56, 'exchange_rate' => 28],
        ],
    ])->assertRedirect(route('expenses.show', $expense));

    $expense->refresh();

    expect($expense->usd_amount)->toBe('14.00')
        ->and($expense->items)->toHaveCount(1);
});

test('a mixed ves item requires a valid exchange rate', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Mixto sin tasa',
        'spent_at' => now()->toDateString(),
        'items' => [
            ['currency' => Currency::Ves->value, 'amount' => 100, 'exchange_rate' => 0],
        ],
    ])->assertSessionHasErrors('items.0.exchange_rate');

    expect(Expense::query()->count())->toBe(0);
});
