<?php

use App\Models\Category;
use App\Models\CreditCard;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::fake(['ve.dolarapi.com/*' => Http::response([
        ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 30.0],
    ])]);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
});

/** @return array<string, mixed> */
function cardPayload(array $overrides = []): array
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

test('creating a card also creates the payment source that represents it', function () {
    $this->post(route('credit-cards.store'), cardPayload())->assertRedirect();

    $card = CreditCard::query()->firstOrFail();

    expect($card->payment_source_id)->not->toBeNull()
        ->and($card->paymentSource->user_id)->toBe($this->user->id)
        // Banco y últimos cuatro para distinguirla entre varias del mismo banco.
        ->and($card->paymentSource->name)->toBe('Banesco Visa Clásica ·4321');

    // Y aparece de inmediato en el formulario de gastos, sin pasos extra.
    $this->get(route('expenses.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sources.0.id', $card->payment_source_id)
            ->etc()
        );
});

test('the list renders and shows only your own cards', function () {
    CreditCard::factory()->for($this->user)->create(['bank' => 'Banesco']);
    CreditCard::factory()->for(User::factory())->create(['bank' => 'Mercantil']);

    $this->get(route('credit-cards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('credit-cards/index')
            ->has('cards', 1)
            ->where('cards.0.bank', 'Banesco')
            // La tarjeta trae ya calculado su ciclo y su saldo: la vista no
            // hace cuentas.
            ->has('cards.0.cycle.next_cut_date')
            ->has('cards.0.balance.available')
            ->etc()
        );
});

test('a user cannot see or touch another users card', function () {
    $foreign = CreditCard::factory()->for(User::factory())->create();

    $this->get(route('credit-cards.show', $foreign))->assertForbidden();
    $this->put(route('credit-cards.update', $foreign), cardPayload())->assertForbidden();
    $this->delete(route('credit-cards.destroy', $foreign))->assertForbidden();
    $this->post(route('credit-cards.statements.store', $foreign), [
        'cut_date' => now()->subDay()->toDateString(),
        'closing_balance' => 100,
        'exchange_rate' => 30,
    ])->assertForbidden();
});

test('the projected balance is the statement plus charges minus payments', function () {
    $source = PaymentSource::factory()->for($this->user)->create();
    $category = Category::factory()->for($this->user)->create();

    $card = CreditCard::factory()->for($this->user)->create([
        'payment_source_id' => $source->id,
        'currency' => 'ves',
        'credit_limit' => 50000,
    ]);

    CreditCardStatement::factory()->for($card)->create([
        'cut_date' => now()->subDays(10)->toDateString(),
        'closing_balance' => 10000,
        'currency' => 'ves',
        'exchange_rate' => 30,
        'usd_amount' => 333.33,
        'usdt_amount' => 333.33,
    ]);

    // Consumo posterior al corte: es un gasto normal contra el origen.
    Expense::factory()->for($this->user)->for($category)->for($source, 'paymentSource')->create([
        'currency' => 'ves',
        'amount' => 2000,
        'commission' => 14,
        'exchange_rate' => 30,
        'usd_amount' => 67.13,
        'spent_at' => now()->subDays(2),
    ]);

    $this->post(route('credit-cards.payments.store', $card), [
        'amount' => 3000,
        'paid_at' => now()->subDay()->toDateString(),
        'exchange_rate' => 30,
    ])->assertRedirect();

    $balance = $this->get(route('credit-cards.show', $card))
        ->viewData('page')['props']['card']['balance'];

    // 10.000 (corte) + 2.000 + 14 (comisión, que el banco carga junto) − 3.000
    expect($balance['projected_used'])->toBe(9014.0)
        ->and($balance['available'])->toBe(40986.0)
        ->and($balance['is_estimate'])->toBeTrue();
});

test('with no movement after the cut the balance is the bank figure, not an estimate', function () {
    $card = CreditCard::factory()->for($this->user)->create(['credit_limit' => 50000]);

    CreditCardStatement::factory()->for($card)->create([
        'cut_date' => now()->subDays(3)->toDateString(),
        'closing_balance' => 8000,
    ]);

    $balance = $this->get(route('credit-cards.show', $card))
        ->viewData('page')['props']['card']['balance'];

    expect($balance['projected_used'])->toBe(8000.0)
        ->and($balance['is_estimate'])->toBeFalse();
});

test('a card payment never counts as an expense', function () {
    // La trampa clásica: si el abono se registrara como gasto, el consumo se
    // contaría dos veces —al comprar y al pagar— y los informes mentirían.
    $source = PaymentSource::factory()->for($this->user)->create();
    $category = Category::factory()->for($this->user)->create();

    $card = CreditCard::factory()->for($this->user)->create(['payment_source_id' => $source->id]);

    Expense::factory()->for($this->user)->for($category)->for($source, 'paymentSource')->create([
        'currency' => 'ves', 'amount' => 3000, 'exchange_rate' => 30,
        'usd_amount' => 100, 'usdt_amount' => 100, 'spent_at' => now(),
    ]);

    $totalsBefore = $this->get(route('expenses.index'))->viewData('page')['props']['totals'];

    $this->post(route('credit-cards.payments.store', $card), [
        'amount' => 3000,
        'paid_at' => now()->toDateString(),
        'exchange_rate' => 30,
    ])->assertRedirect();

    $totalsAfter = $this->get(route('expenses.index'))->viewData('page')['props']['totals'];

    expect($totalsAfter)->toBe($totalsBefore)
        ->and(Expense::query()->count())->toBe(1);
});

test('movements in another currency are flagged instead of silently converted', function () {
    $source = PaymentSource::factory()->for($this->user)->create();
    $category = Category::factory()->for($this->user)->create();

    // Tarjeta en Bs con un consumo en USD: convertirlo con la tasa de hoy
    // falsearía un saldo que el banco lleva en bolívares.
    $card = CreditCard::factory()->for($this->user)->create([
        'payment_source_id' => $source->id,
        'currency' => 'ves',
    ]);

    CreditCardStatement::factory()->for($card)->create([
        'cut_date' => now()->subDays(5)->toDateString(),
        'closing_balance' => 5000,
    ]);

    Expense::factory()->for($this->user)->for($category)->for($source, 'paymentSource')->create([
        'currency' => 'usd', 'amount' => 40, 'exchange_rate' => null,
        'usd_amount' => 40, 'usdt_amount' => 40, 'spent_at' => now()->subDay(),
    ]);

    $balance = $this->get(route('credit-cards.show', $card))
        ->viewData('page')['props']['card']['balance'];

    expect($balance['projected_used'])->toBe(5000.0)
        ->and($balance['foreign_movements'])->toBe(1);
});

test('deleting a card keeps the payment source when it already has expenses', function () {
    $source = PaymentSource::factory()->for($this->user)->create();
    $category = Category::factory()->for($this->user)->create();

    $card = CreditCard::factory()->for($this->user)->create(['payment_source_id' => $source->id]);

    Expense::factory()->for($this->user)->for($category)->for($source, 'paymentSource')->create();

    $this->delete(route('credit-cards.destroy', $card))->assertRedirect();

    // El historial de lo que se compró con la tarjeta no se toca.
    expect(CreditCard::query()->count())->toBe(0)
        ->and(PaymentSource::query()->whereKey($source->id)->exists())->toBeTrue()
        ->and(Expense::query()->count())->toBe(1);
});

test('deleting an unused card cleans up its payment source', function () {
    $this->post(route('credit-cards.store'), cardPayload())->assertRedirect();

    $card = CreditCard::query()->firstOrFail();
    $sourceId = $card->payment_source_id;

    $this->delete(route('credit-cards.destroy', $card))->assertRedirect();

    expect(PaymentSource::query()->whereKey($sourceId)->exists())->toBeFalse();
});

test('two statements cannot share a cut date', function () {
    $card = CreditCard::factory()->for($this->user)->create(['currency' => 'ves']);
    $cutDate = now()->subDays(4)->toDateString();

    $payload = ['cut_date' => $cutDate, 'closing_balance' => 1000, 'exchange_rate' => 30];

    $this->post(route('credit-cards.statements.store', $card), $payload)->assertRedirect();
    $this->post(route('credit-cards.statements.store', $card), $payload)
        ->assertSessionHasErrors('cut_date');

    expect($card->statements()->count())->toBe(1);
});

test('a bs card requires an exchange rate on statements and payments', function () {
    $card = CreditCard::factory()->for($this->user)->create(['currency' => 'ves']);

    $this->post(route('credit-cards.statements.store', $card), [
        'cut_date' => now()->subDay()->toDateString(),
        'closing_balance' => 1000,
    ])->assertSessionHasErrors('exchange_rate');

    $this->post(route('credit-cards.payments.store', $card), [
        'amount' => 500,
        'paid_at' => now()->toDateString(),
    ])->assertSessionHasErrors('exchange_rate');
});

test('a payment cannot be attached to another cards statement', function () {
    $mine = CreditCard::factory()->for($this->user)->create(['currency' => 'usd']);
    $other = CreditCard::factory()->for($this->user)->create(['currency' => 'usd']);

    $foreignStatement = CreditCardStatement::factory()->for($other)->create();

    $this->post(route('credit-cards.payments.store', $mine), [
        'amount' => 100,
        'paid_at' => now()->toDateString(),
        'credit_card_statement_id' => $foreignStatement->id,
    ])->assertSessionHasErrors('credit_card_statement_id');
});

test('covering a statement in full marks it as paid', function () {
    $card = CreditCard::factory()->for($this->user)->create(['currency' => 'usd']);

    $statement = CreditCardStatement::factory()->for($card)->create([
        'closing_balance' => 200,
        'currency' => 'usd',
        'exchange_rate' => null,
        'usd_amount' => 200,
        'usdt_amount' => 200,
    ]);

    // Un abono parcial lo deja abierto, como hace el banco.
    $this->post(route('credit-cards.payments.store', $card), [
        'amount' => 120, 'paid_at' => now()->toDateString(),
        'credit_card_statement_id' => $statement->id,
    ])->assertRedirect();

    expect($statement->refresh()->paid_at)->toBeNull();

    $this->post(route('credit-cards.payments.store', $card), [
        'amount' => 80, 'paid_at' => now()->toDateString(),
        'credit_card_statement_id' => $statement->id,
    ])->assertRedirect();

    expect($statement->refresh()->paid_at)->not->toBeNull();
});

test('renaming a card keeps its payment source label in sync', function () {
    $this->post(route('credit-cards.store'), cardPayload())->assertRedirect();

    $card = CreditCard::query()->firstOrFail();

    $this->put(route('credit-cards.update', $card), cardPayload([
        'name' => 'Mastercard Oro',
        'last_four' => '9999',
    ]))->assertRedirect();

    expect($card->refresh()->paymentSource->name)->toBe('Banesco Mastercard Oro ·9999');
});
