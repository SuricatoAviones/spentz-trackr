<?php

use App\Models\Category;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

/** @return array<string, mixed> */
function apiCardPayload(array $overrides = []): array
{
    return [
        'bank' => 'Banesco',
        'name' => 'Visa Clásica',
        'last_four' => '4321',
        'brand' => 'visa',
        'currency' => 'ves',
        'credit_limit' => 50000,
        'cut_day' => 15,
        'due_day' => 5,
        'annual_interest_rate' => 60,
        'minimum_payment_rate' => 5,
        ...$overrides,
    ];
}

test('a user can list their cards with cycle and balance via the API', function () {
    CreditCard::factory()->for($this->user)->create(['bank' => 'Banesco']);
    CreditCard::factory()->for(User::factory())->create(['bank' => 'Mercantil']);

    $this->getJson(route('api.v1.credit-cards.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.cards')
        ->assertJsonPath('data.cards.0.bank', 'Banesco')
        ->assertJsonStructure(['data' => ['cards' => [['cycle' => ['next_cut_date'], 'balance' => ['available', 'is_estimate']]]]]);
});

test('creating a card via the API also creates its payment source', function () {
    $this->postJson(route('api.v1.credit-cards.store'), apiCardPayload())
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $card = CreditCard::query()->firstOrFail();

    expect($card->payment_source_id)->not->toBeNull()
        ->and($card->paymentSource->name)->toBe('Banesco Visa Clásica ·4321');
});

test('the card detail carries statements, payments and recent charges', function () {
    $card = CreditCard::factory()->for($this->user)->create();
    $category = Category::factory()->for($this->user)->create();
    Expense::factory()->for($this->user)->create([
        'payment_source_id' => $card->payment_source_id,
        'category_id' => $category->id,
        'description' => 'Mercado',
    ]);

    $this->getJson(route('api.v1.credit-cards.show', $card))
        ->assertOk()
        ->assertJsonPath('data.card.id', $card->id)
        ->assertJsonPath('data.recent_charges.0.description', 'Mercado')
        ->assertJsonStructure(['data' => ['card' => ['statements', 'payments']]]);
});

test('a bolivares card requires an exchange rate for statements and payments', function () {
    $card = CreditCard::factory()->for($this->user)->create();

    $this->postJson(route('api.v1.credit-cards.statements.store', $card), [
        'cut_date' => today()->subDays(2)->toDateString(),
        'closing_balance' => 3000,
    ])->assertStatus(422)->assertJsonValidationErrors('exchange_rate');

    $this->postJson(route('api.v1.credit-cards.payments.store', $card), [
        'amount' => 500,
        'paid_at' => today()->toDateString(),
    ])->assertStatus(422)->assertJsonValidationErrors('exchange_rate');
});

test('a statement freezes its usd amount and deduces the due date', function () {
    $card = CreditCard::factory()->for($this->user)->create(['cut_day' => 15, 'due_day' => 5]);

    $this->postJson(route('api.v1.credit-cards.statements.store', $card), [
        'cut_date' => '2026-09-15',
        'closing_balance' => 3000,
        'exchange_rate' => 30,
    ])->assertStatus(201);

    $statement = CreditCardStatement::query()->firstOrFail();

    expect($statement->usd_amount)->toBe('100.00')
        // El día de pago (5) es anterior al de corte (15): vence el mes siguiente.
        ->and($statement->due_date->toDateString())->toBe('2026-10-05');
});

test('a payment covering the statement closes it and is not counted as an expense', function () {
    $card = CreditCard::factory()->for($this->user)->create();
    $statement = CreditCardStatement::factory()->for($card)->create([
        'closing_balance' => 1000,
        'paid_at' => null,
    ]);

    $this->postJson(route('api.v1.credit-cards.payments.store', $card), [
        'amount' => 1000,
        'paid_at' => today()->toDateString(),
        'credit_card_statement_id' => $statement->id,
        'exchange_rate' => 40,
    ])->assertStatus(201);

    expect($statement->fresh()->paid_at)->not->toBeNull()
        // Pagar la tarjeta no es gastar: si contara, cada consumo se contaría dos veces.
        ->and(Expense::query()->count())->toBe(0);
});

test('a payment cannot be imputed to a statement of another card', function () {
    $card = CreditCard::factory()->for($this->user)->create();
    $foreignStatement = CreditCardStatement::factory()
        ->for(CreditCard::factory()->for($this->user))
        ->create();

    $this->postJson(route('api.v1.credit-cards.payments.store', $card), [
        'amount' => 100,
        'paid_at' => today()->toDateString(),
        'credit_card_statement_id' => $foreignStatement->id,
        'exchange_rate' => 40,
    ])->assertStatus(422)->assertJsonValidationErrors('credit_card_statement_id');
});

test('statements and payments of another card cannot be deleted through this one', function () {
    $card = CreditCard::factory()->for($this->user)->create();
    $other = CreditCard::factory()->for($this->user)->create();
    $statement = CreditCardStatement::factory()->for($other)->create();
    $payment = CreditCardPayment::factory()->for($other)->create();

    $this->deleteJson(route('api.v1.credit-cards.statements.destroy', [$card, $statement]))->assertStatus(404);
    $this->deleteJson(route('api.v1.credit-cards.payments.destroy', [$card, $payment]))->assertStatus(404);
});

test('deleting a card keeps the expenses made with it', function () {
    $card = CreditCard::factory()->for($this->user)->create();
    $category = Category::factory()->for($this->user)->create();
    Expense::factory()->for($this->user)->create([
        'payment_source_id' => $card->payment_source_id,
        'category_id' => $category->id,
    ]);

    $this->deleteJson(route('api.v1.credit-cards.destroy', $card))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(CreditCard::query()->count())->toBe(0)
        ->and(Expense::query()->count())->toBe(1);
});

test('a user cannot see or touch another users card via the API', function () {
    $foreign = CreditCard::factory()->for(User::factory())->create();

    $this->getJson(route('api.v1.credit-cards.show', $foreign))->assertStatus(403);
    $this->putJson(route('api.v1.credit-cards.update', $foreign), apiCardPayload())->assertStatus(403);
    $this->deleteJson(route('api.v1.credit-cards.destroy', $foreign))->assertStatus(403);
    $this->postJson(route('api.v1.credit-cards.statements.store', $foreign), [
        'cut_date' => today()->subDay()->toDateString(),
        'closing_balance' => 100,
        'exchange_rate' => 40,
    ])->assertStatus(403);
});
