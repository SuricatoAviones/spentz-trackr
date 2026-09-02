<?php

use App\Enums\Currency;
use App\Models\Category;
use App\Models\Income;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->category = Category::factory()->for($this->user)->create(['type' => 'income']);
    Sanctum::actingAs($this->user);
});

test('a user can list their incomes via the API', function () {
    Income::factory()->for($this->user)->for($this->category)->create(['amount' => 100, 'description' => 'Sueldo']);

    $this->getJson(route('api.v1.incomes.index'))
        ->assertOk()
        ->assertJsonPath('data.incomes.total', 1)
        ->assertJsonPath('data.incomes.data.0.description', 'Sueldo')
        ->assertJsonStructure(['data' => ['incomes', 'filters', 'totals', 'categories']]);
});

test('a user can create an income via the API', function () {
    $response = $this->postJson(route('api.v1.incomes.store'), [
        'category_id' => $this->category->id,
        'currency' => Currency::Usd->value,
        'amount' => 500,
        'description' => 'Salario',
        'received_at' => now()->toDateString(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    $income = Income::query()->first();

    expect($income->usd_amount)->toBe('500.00')
        ->and($income->exchange_rate)->toBeNull();
});

test('a user can create a Bs income freezing the rate via the API', function () {
    $this->postJson(route('api.v1.incomes.store'), [
        'category_id' => $this->category->id,
        'currency' => Currency::Ves->value,
        'amount' => 5700,
        'exchange_rate' => 38.0,
        'description' => 'Bono',
        'received_at' => now()->toDateString(),
    ])->assertStatus(201);

    $income = Income::query()->first();

    expect($income->exchange_rate)->toBe('38.0000')
        ->and($income->usd_amount)->toBe('150.00');
});

test('an income category cannot be an expense category via the API', function () {
    $expenseCategory = Category::factory()->for($this->user)->create(['type' => 'expense']);

    $this->postJson(route('api.v1.incomes.store'), [
        'category_id' => $expenseCategory->id,
        'currency' => Currency::Usd->value,
        'amount' => 500,
        'description' => 'Mal',
        'received_at' => now()->toDateString(),
    ])->assertStatus(422);
});

test('a user can update and delete an income via the API', function () {
    $income = Income::factory()->for($this->user)->for($this->category)->create(['amount' => 100]);

    $this->putJson(route('api.v1.incomes.update', $income), [
        'category_id' => $this->category->id,
        'currency' => Currency::Usd->value,
        'amount' => 200,
        'description' => 'Actualizado',
        'received_at' => now()->toDateString(),
    ])->assertOk()
        ->assertJsonPath('data.income.description', 'Actualizado');

    expect($income->fresh()->amount)->toBe('200.00');

    $this->deleteJson(route('api.v1.incomes.destroy', $income))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Income::query()->count())->toBe(0);
});

test('a user cannot update another user income via the API', function () {
    $other = User::factory()->create();
    $otherCategory = Category::factory()->for($other)->create(['type' => 'income']);
    $income = Income::factory()->for($other)->for($otherCategory)->create();

    $this->putJson(route('api.v1.incomes.update', $income), [
        'category_id' => $this->category->id,
        'currency' => Currency::Usd->value,
        'amount' => 999,
        'description' => 'Hack',
        'received_at' => now()->toDateString(),
    ])->assertStatus(403);
});
