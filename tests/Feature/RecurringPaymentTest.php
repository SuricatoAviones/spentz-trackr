<?php

use App\Enums\Currency;
use App\Enums\Frequency;
use App\Models\Category;
use App\Models\RecurringPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('guests are redirected to the login page', function () {
    auth()->logout();

    $this->get(route('recurring-payments.index'))->assertRedirect(route('login'));
});

test('the index page exposes payments with due status and categories', function () {
    $payment = RecurringPayment::factory()->for($this->user)->create([
        'name' => 'Spotify',
        'amount' => '4.99',
        'currency' => Currency::Usd,
        'usd_amount' => '4.99',
        'usdt_amount' => '4.99',
        'frequency' => Frequency::Monthly,
        'next_due_date' => today()->toDateString(),
    ]);
    $category = Category::factory()->for($this->user)->create(['name' => 'Entretenimiento']);

    $this->get(route('recurring-payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('recurring-payments/index')
            ->has('payments', 1)
            ->where('payments.0.name', 'Spotify')
            ->where('payments.0.due', true)
            ->where('payments.0.usd_amount', '4.99')
            ->where('payments.0.frequency_label', 'Mensual')
            ->has('categories', 1)
            ->where('categories.0.name', 'Entretenimiento')
        );
});

test('a user can create a recurring payment in USD', function () {
    $this->post(route('recurring-payments.store'), [
        'name' => 'Spotify',
        'amount' => '4.99',
        'currency' => Currency::Usd->value,
        'frequency' => Frequency::Monthly->value,
        'next_due_date' => today()->toDateString(),
    ])->assertRedirect(route('recurring-payments.index'));

    $payment = RecurringPayment::query()->first();

    expect($payment->user_id)->toBe($this->user->id)
        ->and($payment->amount)->toBe('4.99')
        ->and($payment->usd_amount)->toBe('4.99')
        ->and($payment->usdt_amount)->toBe('4.99')
        ->and($payment->exchange_rate)->toBeNull()
        ->and($payment->active)->toBeTrue()
        ->and($payment->last_paid_at)->toBeNull();
});

test('a user can create a recurring payment in Bs normalizing to USD', function () {
    $this->post(route('recurring-payments.store'), [
        'name' => 'Gimnasio',
        'amount' => '570',
        'currency' => Currency::Ves->value,
        'exchange_rate' => '28.5',
        'frequency' => Frequency::Monthly->value,
        'next_due_date' => today()->toDateString(),
    ])->assertRedirect(route('recurring-payments.index'));

    $payment = RecurringPayment::query()->first();

    expect($payment->exchange_rate)->toBe('28.5000')
        ->and($payment->usd_amount)->toBe('20.00')
        ->and($payment->usdt_amount)->toBe('20.00');
});

test('a Bs recurring payment without an exchange rate fails validation', function () {
    $this->post(route('recurring-payments.store'), [
        'name' => 'Gimnasio',
        'amount' => 570,
        'currency' => Currency::Ves->value,
        'frequency' => Frequency::Monthly->value,
        'next_due_date' => today()->toDateString(),
    ])->assertSessionHasErrors('exchange_rate');
});

test('a user can update a recurring payment', function () {
    $payment = RecurringPayment::factory()->for($this->user)->create();

    $this->put(route('recurring-payments.update', $payment), [
        'name' => 'Spotify Premium',
        'amount' => '9.99',
        'currency' => Currency::Usd->value,
        'frequency' => Frequency::Yearly->value,
        'next_due_date' => today()->toDateString(),
    ])->assertRedirect(route('recurring-payments.index'));

    $payment->refresh();

    expect($payment->name)->toBe('Spotify Premium')
        ->and($payment->amount)->toBe('9.99')
        ->and($payment->usd_amount)->toBe('9.99')
        ->and($payment->frequency)->toBe(Frequency::Yearly);
});

test('a user can delete a recurring payment', function () {
    $payment = RecurringPayment::factory()->for($this->user)->create();

    $this->delete(route('recurring-payments.destroy', $payment))
        ->assertRedirect(route('recurring-payments.index'));

    $this->assertDatabaseMissing('recurring_payments', ['id' => $payment->id]);
});

test('a user cannot modify another user recurring payment', function () {
    $other = User::factory()->create();
    $payment = RecurringPayment::factory()->for($other)->create();

    $this->put(route('recurring-payments.update', $payment), [
        'name' => 'Intruso',
        'amount' => 10,
        'currency' => Currency::Usd->value,
        'frequency' => Frequency::Monthly->value,
        'next_due_date' => today()->toDateString(),
    ])->assertForbidden();

    $this->delete(route('recurring-payments.destroy', $payment))->assertForbidden();
});

test('marking a payment as paid advances the next due date', function () {
    $payment = RecurringPayment::factory()->for($this->user)->create([
        'frequency' => Frequency::Monthly,
        'next_due_date' => today()->toDateString(),
        'last_paid_at' => null,
    ]);

    $this->post(route('recurring-payments.pay', $payment))
        ->assertRedirect(route('recurring-payments.index'));

    $payment->refresh();

    expect($payment->last_paid_at->toDateString())->toBe(today()->toDateString())
        ->and($payment->next_due_date->toDateString())->toBe(
            Carbon::parse(today()->toDateString())->addMonth()->toDateString()
        );
});

test('marking a payment as paid respects the weekly frequency', function () {
    $payment = RecurringPayment::factory()->for($this->user)->create([
        'frequency' => Frequency::Weekly,
        'next_due_date' => today()->toDateString(),
    ]);

    $this->post(route('recurring-payments.pay', $payment))->assertRedirect();

    $payment->refresh();

    expect($payment->next_due_date->toDateString())->toBe(
        Carbon::parse(today()->toDateString())->addDays(7)->toDateString()
    );
});

test('a user cannot mark another user payment as paid', function () {
    $other = User::factory()->create();
    $payment = RecurringPayment::factory()->for($other)->create();

    $this->post(route('recurring-payments.pay', $payment))->assertForbidden();
});

test('a recurring payment can be linked to a category', function () {
    $category = Category::factory()->for($this->user)->create();

    $this->post(route('recurring-payments.store'), [
        'name' => 'Netflix',
        'amount' => '11.99',
        'currency' => Currency::Usd->value,
        'frequency' => Frequency::Monthly->value,
        'next_due_date' => today()->toDateString(),
        'category_id' => $category->id,
    ])->assertRedirect(route('recurring-payments.index'));

    $payment = RecurringPayment::query()->first();

    expect($payment->category_id)->toBe($category->id);
});
