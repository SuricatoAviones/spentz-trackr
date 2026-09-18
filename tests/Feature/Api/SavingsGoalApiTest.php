<?php

use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('a user can list their savings goals with progress via the API', function () {
    $goal = SavingsGoal::factory()->for($this->user)->create([
        'name' => 'Viaje',
        'target_amount' => 1000,
        'target_usd_amount' => 1000,
    ]);
    SavingsContribution::factory()->for($goal, 'goal')->create(['amount' => 250, 'usd_amount' => 250]);

    $this->getJson(route('api.v1.savings-goals.index'))
        ->assertOk()
        ->assertJsonPath('data.goals.0.name', 'Viaje')
        ->assertJsonPath('data.goals.0.saved', 250)
        ->assertJsonPath('data.goals.0.percent', 25);
});

test('the savings goals list can be filtered by achievement', function () {
    SavingsGoal::factory()->for($this->user)->create(['name' => 'Pendiente']);
    SavingsGoal::factory()->for($this->user)->create(['name' => 'Cumplida', 'achieved_at' => now()]);

    $this->getJson(route('api.v1.savings-goals.index', ['achieved' => 1]))
        ->assertOk()
        ->assertJsonCount(1, 'data.goals')
        ->assertJsonPath('data.goals.0.name', 'Cumplida');
});

test('a user can create a savings goal via the API', function () {
    $this->postJson(route('api.v1.savings-goals.store'), [
        'name' => 'Laptop',
        'target_amount' => 800,
        'currency' => 'usd',
    ])->assertStatus(201)
        ->assertJsonPath('success', true);

    expect(SavingsGoal::query()->first())
        ->name->toBe('Laptop')
        ->target_usd_amount->toBe('800.00');
});

test('a bolivares savings goal freezes its target in usd with the sent rate', function () {
    $this->postJson(route('api.v1.savings-goals.store'), [
        'name' => 'Nevera',
        'target_amount' => 4000,
        'currency' => 'ves',
        'exchange_rate' => 40,
    ])->assertStatus(201);

    expect(SavingsGoal::query()->first()->target_usd_amount)->toBe('100.00');
});

test('a bolivares savings goal requires an exchange rate', function () {
    $this->postJson(route('api.v1.savings-goals.store'), [
        'name' => 'Nevera',
        'target_amount' => 4000,
        'currency' => 'ves',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('exchange_rate');
});

test('a user can show, update and delete their savings goal via the API', function () {
    $goal = SavingsGoal::factory()->for($this->user)->create(['name' => 'Viejo']);

    $this->getJson(route('api.v1.savings-goals.show', $goal))
        ->assertOk()
        ->assertJsonPath('data.goal.name', 'Viejo');

    $this->putJson(route('api.v1.savings-goals.update', $goal), [
        'name' => 'Nuevo',
        'target_amount' => 500,
        'currency' => 'usd',
    ])->assertOk()
        ->assertJsonPath('data.goal.name', 'Nuevo');

    $this->deleteJson(route('api.v1.savings-goals.destroy', $goal))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(SavingsGoal::query()->count())->toBe(0);
});

test('a user cannot touch another user savings goal via the API', function () {
    $goal = SavingsGoal::factory()->for(User::factory())->create();

    $this->getJson(route('api.v1.savings-goals.show', $goal))->assertStatus(403);
    $this->deleteJson(route('api.v1.savings-goals.destroy', $goal))->assertStatus(403);
});

test('a contribution marks the goal as achieved and removing it undoes that', function () {
    $goal = SavingsGoal::factory()->for($this->user)->create([
        'target_amount' => 100,
        'target_usd_amount' => 100,
    ]);

    $this->postJson(route('api.v1.savings-goals.contributions.store', $goal), [
        'amount' => 100,
        'currency' => 'usd',
        'contributed_at' => today()->toDateString(),
    ])->assertStatus(201);

    expect($goal->fresh()->achieved_at)->not->toBeNull();

    $contribution = SavingsContribution::query()->firstOrFail();

    $this->deleteJson(route('api.v1.savings-goals.contributions.destroy', [$goal, $contribution]))
        ->assertOk();

    expect($goal->fresh()->achieved_at)->toBeNull();
});

test('a contribution from another goal cannot be deleted through this one', function () {
    $goal = SavingsGoal::factory()->for($this->user)->create();
    $other = SavingsGoal::factory()->for($this->user)->create();
    $contribution = SavingsContribution::factory()->for($other, 'goal')->create();

    $this->deleteJson(route('api.v1.savings-goals.contributions.destroy', [$goal, $contribution]))
        ->assertStatus(404);

    expect(SavingsContribution::query()->count())->toBe(1);
});
