<?php

use App\Models\RecurringPayment;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('a user can list their recurring payments with the overdue ones first', function () {
    RecurringPayment::factory()->for($this->user)->create([
        'name' => 'Netflix',
        'next_due_date' => today()->addWeek()->toDateString(),
    ]);
    RecurringPayment::factory()->for($this->user)->create([
        'name' => 'Internet',
        'next_due_date' => today()->subDay()->toDateString(),
    ]);

    $this->getJson(route('api.v1.recurring-payments.index'))
        ->assertOk()
        ->assertJsonPath('data.payments.0.name', 'Internet')
        ->assertJsonPath('data.payments.0.due', true)
        ->assertJsonPath('data.payments.1.due', false);
});

test('the recurring payments list can be filtered by due and active', function () {
    RecurringPayment::factory()->for($this->user)->create([
        'name' => 'Vencido',
        'next_due_date' => today()->subDay()->toDateString(),
    ]);
    RecurringPayment::factory()->for($this->user)->create([
        'name' => 'Pausado',
        'active' => false,
        'next_due_date' => today()->subDay()->toDateString(),
    ]);

    $this->getJson(route('api.v1.recurring-payments.index', ['due' => 1]))
        ->assertOk()
        ->assertJsonCount(1, 'data.payments')
        ->assertJsonPath('data.payments.0.name', 'Vencido');

    $this->getJson(route('api.v1.recurring-payments.index', ['active' => 0]))
        ->assertOk()
        ->assertJsonCount(1, 'data.payments')
        ->assertJsonPath('data.payments.0.name', 'Pausado');
});

test('a user can create a recurring payment via the API', function () {
    $this->postJson(route('api.v1.recurring-payments.store'), [
        'name' => 'Spotify',
        'amount' => 11,
        'currency' => 'usd',
        'frequency' => 'monthly',
        'next_due_date' => today()->addMonth()->toDateString(),
    ])->assertStatus(201)
        ->assertJsonPath('success', true);

    expect(RecurringPayment::query()->first())
        ->name->toBe('Spotify')
        ->usd_amount->toBe('11.00');
});

test('a bolivares recurring payment freezes its usd equivalent with the sent rate', function () {
    $this->postJson(route('api.v1.recurring-payments.store'), [
        'name' => 'Condominio',
        'amount' => 400,
        'currency' => 'ves',
        'exchange_rate' => 40,
        'frequency' => 'monthly',
        'next_due_date' => today()->addMonth()->toDateString(),
    ])->assertStatus(201);

    expect(RecurringPayment::query()->first()->usd_amount)->toBe('10.00');
});

test('a user can pause a recurring payment via the API', function () {
    $payment = RecurringPayment::factory()->for($this->user)->create(['name' => 'Gym']);

    $this->putJson(route('api.v1.recurring-payments.update', $payment), [
        'name' => 'Gym',
        'amount' => 20,
        'currency' => 'usd',
        'frequency' => 'monthly',
        'next_due_date' => today()->addMonth()->toDateString(),
        'active' => false,
    ])->assertOk()
        ->assertJsonPath('data.payment.active', false);

    expect($payment->fresh()->active)->toBeFalse();
});

test('marking a recurring payment as paid advances the due date and creates no expense', function () {
    $payment = RecurringPayment::factory()->for($this->user)->create([
        'frequency' => 'monthly',
        'next_due_date' => today()->subDay()->toDateString(),
    ]);

    $this->postJson(route('api.v1.recurring-payments.pay', $payment))
        ->assertOk()
        ->assertJsonPath('data.payment.next_due_date', today()->addMonth()->toDateString())
        ->assertJsonPath('data.payment.last_paid_at', today()->toDateString());

    expect($this->user->expenses()->count())->toBe(0);
});

test('a user cannot touch another user recurring payment via the API', function () {
    $payment = RecurringPayment::factory()->for(User::factory())->create();

    $this->getJson(route('api.v1.recurring-payments.show', $payment))->assertStatus(403);
    $this->postJson(route('api.v1.recurring-payments.pay', $payment))->assertStatus(403);
    $this->deleteJson(route('api.v1.recurring-payments.destroy', $payment))->assertStatus(403);
});

test('a user can delete their recurring payment via the API', function () {
    $payment = RecurringPayment::factory()->for($this->user)->create();

    $this->deleteJson(route('api.v1.recurring-payments.destroy', $payment))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(RecurringPayment::query()->count())->toBe(0);
});
