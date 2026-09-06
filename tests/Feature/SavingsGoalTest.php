<?php

use App\Enums\Currency;
use App\Models\Income;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('guests are redirected to the login page', function () {
    auth()->logout();

    $this->get(route('savings-goals.index'))->assertRedirect(route('login'));
});

test('the index page exposes goals with progress and income options', function () {
    $goal = SavingsGoal::factory()->for($this->user)->create([
        'name' => 'Fondo',
        'target_amount' => 100,
        'target_usd_amount' => '100.00',
    ]);
    SavingsContribution::factory()->create([
        'savings_goal_id' => $goal->id,
        'amount' => 25,
        'usd_amount' => '25.00',
        'usdt_amount' => '25.00',
    ]);
    $income = Income::factory()->for($this->user)->create(['description' => 'Pago UP']);

    $this->get(route('savings-goals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('savings-goals/index')
            ->has('goals', 1)
            ->where('goals.0.name', 'Fondo')
            ->where('goals.0.saved', 25)
            ->where('goals.0.percent', 25)
            ->where('goals.0.contributions.0.usd_amount', '25.00')
            ->has('incomes', 1)
            ->where('incomes.0.description', 'Pago UP')
        );
});

test('a user can create a savings goal in USD', function () {
    $this->post(route('savings-goals.store'), [
        'name' => 'Fondo de emergencia',
        'target_amount' => 500,
        'currency' => Currency::Usd->value,
    ])->assertRedirect(route('savings-goals.index'));

    $goal = SavingsGoal::query()->first();

    expect($goal->user_id)->toBe($this->user->id)
        ->and($goal->target_usd_amount)->toBe('500.00')
        ->and($goal->exchange_rate)->toBeNull()
        ->and($goal->achieved_at)->toBeNull();
});

test('a user can create a savings goal in Bs normalizing the target to USD', function () {
    $this->post(route('savings-goals.store'), [
        'name' => 'Vacaciones',
        'target_amount' => '2850',
        'currency' => Currency::Ves->value,
        'exchange_rate' => '28.5',
    ])->assertRedirect(route('savings-goals.index'));

    $goal = SavingsGoal::query()->first();

    expect($goal->exchange_rate)->toBe('28.5000')
        ->and($goal->target_usd_amount)->toBe('100.00');
});

test('a Bs goal without an exchange rate fails validation', function () {
    $this->post(route('savings-goals.store'), [
        'name' => 'Vacaciones',
        'target_amount' => 2850,
        'currency' => Currency::Ves->value,
    ])->assertSessionHasErrors('exchange_rate');
});

test('a user can update a savings goal', function () {
    $goal = SavingsGoal::factory()->for($this->user)->create();

    $this->put(route('savings-goals.update', $goal), [
        'name' => 'Meta renovada',
        'target_amount' => 1000,
        'currency' => Currency::Usd->value,
    ])->assertRedirect(route('savings-goals.index'));

    $goal->refresh();

    expect($goal->name)->toBe('Meta renovada')
        ->and($goal->target_amount)->toBe('1000.00')
        ->and($goal->target_usd_amount)->toBe('1000.00');
});

test('a user can delete a savings goal', function () {
    $goal = SavingsGoal::factory()->for($this->user)->create();

    $this->delete(route('savings-goals.destroy', $goal))
        ->assertRedirect(route('savings-goals.index'));

    $this->assertDatabaseMissing('savings_goals', ['id' => $goal->id]);
});

test('a user cannot modify another user savings goal', function () {
    $other = User::factory()->create();
    $goal = SavingsGoal::factory()->for($other)->create();

    $this->put(route('savings-goals.update', $goal), [
        'name' => 'Intruso',
        'target_amount' => 10,
        'currency' => Currency::Usd->value,
    ])->assertForbidden();

    $this->delete(route('savings-goals.destroy', $goal))->assertForbidden();
});

test('a user can add a contribution in USD linked to an income', function () {
    $goal = SavingsGoal::factory()->for($this->user)->create([
        'target_amount' => 100,
        'target_usd_amount' => '100.00',
    ]);
    $income = Income::factory()->for($this->user)->create();

    $this->post(route('savings-goals.contributions.store', $goal), [
        'income_id' => $income->id,
        'amount' => 40,
        'currency' => Currency::Usd->value,
        'contributed_at' => now()->toDateString(),
    ])->assertRedirect(route('savings-goals.index'));

    $contribution = SavingsContribution::query()->first();

    expect($contribution->savings_goal_id)->toBe($goal->id)
        ->and($contribution->income_id)->toBe($income->id)
        ->and($contribution->usd_amount)->toBe('40.00');

    $goal->refresh();
    expect($goal->achieved_at)->toBeNull();
});

test('a goal is marked as achieved when contributions reach the target', function () {
    $goal = SavingsGoal::factory()->for($this->user)->create([
        'target_amount' => 100,
        'target_usd_amount' => '100.00',
    ]);

    $this->post(route('savings-goals.contributions.store', $goal), [
        'amount' => 60,
        'currency' => Currency::Usd->value,
        'contributed_at' => now()->toDateString(),
    ])->assertRedirect();

    $goal->refresh();
    expect($goal->achieved_at)->toBeNull();

    $this->post(route('savings-goals.contributions.store', $goal), [
        'amount' => 50,
        'currency' => Currency::Usd->value,
        'contributed_at' => now()->toDateString(),
    ])->assertRedirect();

    $goal->refresh();
    expect($goal->achieved_at)->not->toBeNull();
});

test('a contribution in Bs normalizes to USD and requires a rate', function () {
    $goal = SavingsGoal::factory()->for($this->user)->create([
        'target_amount' => 100,
        'target_usd_amount' => '100.00',
    ]);

    $this->post(route('savings-goals.contributions.store', $goal), [
        'amount' => 570,
        'currency' => Currency::Ves->value,
        'exchange_rate' => 28.5,
        'contributed_at' => now()->toDateString(),
    ])->assertRedirect();

    $contribution = SavingsContribution::query()->first();

    expect($contribution->usd_amount)->toBe('20.00')
        ->and($contribution->usdt_amount)->toBe('20.00');

    $this->post(route('savings-goals.contributions.store', $goal), [
        'amount' => 570,
        'currency' => Currency::Ves->value,
        'contributed_at' => now()->toDateString(),
    ])->assertSessionHasErrors('exchange_rate');
});

test('removing a contribution can un-achieve a goal', function () {
    $goal = SavingsGoal::factory()->for($this->user)->create([
        'target_amount' => 100,
        'target_usd_amount' => '100.00',
        'achieved_at' => now(),
    ]);
    $contribution = SavingsContribution::factory()->create([
        'savings_goal_id' => $goal->id,
        'amount' => 100,
        'usd_amount' => '100.00',
        'usdt_amount' => '100.00',
    ]);

    $this->delete(route('savings-goals.contributions.destroy', [
        $goal,
        $contribution,
    ]))->assertRedirect(route('savings-goals.index'));

    $this->assertDatabaseMissing('savings_contributions', ['id' => $contribution->id]);

    $goal->refresh();
    expect($goal->achieved_at)->toBeNull();
});

test('a user cannot contribute to another user savings goal', function () {
    $other = User::factory()->create();
    $goal = SavingsGoal::factory()->for($other)->create();

    $this->post(route('savings-goals.contributions.store', $goal), [
        'amount' => 10,
        'currency' => Currency::Usd->value,
        'contributed_at' => now()->toDateString(),
    ])->assertForbidden();
});
