<?php

use App\Enums\Currency;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->category = Category::factory()->for($this->user)->create();
    $this->incomeCategory = Category::factory()->for($this->user)->create(['type' => 'income']);
    $this->source = PaymentSource::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user);
});

test('a user can list their expenses via the API', function () {
    Expense::factory()->for($this->user)->for($this->category)->for($this->source)->create(['amount' => 50, 'description' => 'Cena']);

    $this->getJson(route('api.v1.expenses.index'))
        ->assertOk()
        ->assertJsonPath('data.expenses.total', 1)
        ->assertJsonPath('data.expenses.data.0.description', 'Cena')
        ->assertJsonStructure([
            'data' => ['expenses', 'filters', 'totals', 'categories', 'sources'],
        ]);
});

test('a user can create an expense in USD via the API', function () {
    $response = $this->postJson(route('api.v1.expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 25.5,
        'description' => 'Mercado',
        'spent_at' => now()->toDateString(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $response->json('data.id'));

    $expense = Expense::query()->first();

    expect($expense->usd_amount)->toBe('25.50')
        ->and($expense->usdt_amount)->toBe('25.50')
        ->and($expense->exchange_rate)->toBeNull();
});

test('a user can create a Bs expense freezing the rate via the API', function () {
    $this->postJson(route('api.v1.expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 140,
        'exchange_rate' => 28.5,
        'description' => 'Pasaje',
        'spent_at' => now()->toDateString(),
    ])->assertStatus(201);

    $expense = Expense::query()->first();

    expect($expense->currency)->toBe(Currency::Ves)
        ->and($expense->exchange_rate)->toBe('28.5000')
        ->and($expense->usd_amount)->toBe('4.91')
        ->and($expense->usdt_amount)->toBe('4.91');
});

test('a Bs expense uses the stored day rate via the API', function () {
    ExchangeRate::factory()->create([
        'user_id' => null,
        'source' => 'api',
        'provider' => 'bcv',
        'rate' => 30.0,
        'rate_date' => now()->toDateString(),
    ]);

    $this->postJson(route('api.v1.expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 300,
        'description' => 'Compra',
        'spent_at' => now()->toDateString(),
    ])->assertStatus(201);

    expect(Expense::query()->first()->usd_amount)->toBe('10.00');
});

test('a Bs expense without a rate is rejected via the API', function () {
    $this->postJson(route('api.v1.expenses.store'), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Ves->value,
        'amount' => 100,
        'description' => 'Sin tasa',
        'spent_at' => now()->toDateString(),
    ])->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

test('validation errors return 422 via the API', function () {
    $this->postJson(route('api.v1.expenses.store'), [
        'currency' => Currency::Usd->value,
    ])->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

test('a user can update their expense via the API', function () {
    $expense = Expense::factory()->for($this->user)->for($this->category)->for($this->source)->create(['amount' => 10]);

    $this->putJson(route('api.v1.expenses.update', $expense), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 20,
        'description' => 'Actualizado',
        'spent_at' => now()->toDateString(),
    ])->assertOk()
        ->assertJsonPath('data.expense.description', 'Actualizado');

    expect($expense->fresh()->amount)->toBe('20.00');
});

test('a user cannot update another user expense via the API', function () {
    $other = User::factory()->create();
    $otherCategory = Category::factory()->for($other)->create();
    $otherSource = PaymentSource::factory()->for($other)->create();
    $expense = Expense::factory()->for($other)->for($otherCategory)->for($otherSource)->create();

    $this->putJson(route('api.v1.expenses.update', $expense), [
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'currency' => Currency::Usd->value,
        'amount' => 20,
        'description' => 'Hack',
        'spent_at' => now()->toDateString(),
    ])->assertStatus(403);
});

test('a user can delete their expense via the API', function () {
    $expense = Expense::factory()->for($this->user)->for($this->category)->for($this->source)->create();

    $this->deleteJson(route('api.v1.expenses.destroy', $expense))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Expense::query()->count())->toBe(0);
});
