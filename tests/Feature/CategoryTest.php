<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('categories index lists only the user categories with totals', function () {
    Category::factory()->for($this->user)->create(['name' => 'AlimentaciÃ³n']);
    Category::factory()->for($this->user)->create(['name' => 'Transporte']);

    $category = Category::query()->where('name', 'AlimentaciÃ³n')->first();
    $source = PaymentSource::factory()->for($this->user)->create();
    Expense::factory()->for($this->user)->for($category)->for($source, 'paymentSource')
        ->create(['amount' => 100, 'usd_amount' => 100, 'usdt_amount' => 100]);

    $otherUser = User::factory()->create();
    Category::factory()->for($otherUser)->create(['name' => 'Secreta']);

    $this->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/index')
            ->has('categories', 2)
            ->where('categories.0.name', 'AlimentaciÃ³n')
            ->where('categories.0.expenses_count', 1)
            ->where('categories.0.total_usd', 100)
        );
});

test('a user can create a category', function () {
    $this->post(route('categories.store'), [
        'name' => 'Vivienda',
        'icon' => 'home',
        'color' => '#10B981',
        'type' => 'expense',
    ])->assertRedirect(route('categories.index'));

    $this->assertDatabaseHas('categories', [
        'user_id' => $this->user->id,
        'name' => 'Vivienda',
        'icon' => 'home',
        'color' => '#10B981',
        'type' => 'expense',
    ]);
});

test('a user can create an income category', function () {
    $this->post(route('categories.store'), [
        'name' => 'Salario',
        'icon' => 'dollar-sign',
        'color' => '#3B82F6',
        'type' => 'income',
    ])->assertRedirect(route('categories.index'));

    $this->assertDatabaseHas('categories', [
        'user_id' => $this->user->id,
        'name' => 'Salario',
        'type' => 'income',
    ]);
});

test('a user cannot create a category with an invalid color', function () {
    $this->post(route('categories.store'), [
        'name' => 'Vivienda',
        'icon' => 'home',
        'color' => 'rojo',
    ])->assertSessionHasErrors(['color']);

    $this->assertDatabaseCount('categories', 0);
});

test('a user can update their own category', function () {
    $category = Category::factory()->for($this->user)->create(['name' => 'Ocio']);

    $this->put(route('categories.update', $category), [
        'name' => 'Entretenimiento',
        'icon' => 'gamepad-2',
        'color' => '#8B5CF6',
    ])->assertRedirect(route('categories.index'));

    expect($category->refresh()->name)->toBe('Entretenimiento');
});

test('a user can set a monthly budget on a category', function () {
    $category = Category::factory()->for($this->user)->create();

    $this->put(route('categories.update', $category), [
        'name' => $category->name,
        'icon' => $category->icon,
        'color' => $category->color,
        'budget' => 150,
    ])->assertRedirect(route('categories.index'));

    expect($category->refresh()->budget)->toBe('150.00');
});

test('a negative budget is rejected', function () {
    $category = Category::factory()->for($this->user)->create();

    $this->put(route('categories.update', $category), [
        'name' => $category->name,
        'icon' => $category->icon,
        'color' => $category->color,
        'budget' => -5,
    ])->assertSessionHasErrors('budget');

    expect($category->refresh()->budget)->toBeNull();
});

test('categories index exposes the budget and the monthly spent', function () {
    $category = Category::factory()->for($this->user)->create(['budget' => 200]);
    $source = PaymentSource::factory()->for($this->user)->create();
    Expense::factory()->for($this->user)->for($category)->for($source, 'paymentSource')
        ->on(now()->format('Y-m').'-10')
        ->create(['amount' => 50, 'usd_amount' => 50, 'usdt_amount' => 50]);

    $this->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/index')
            ->where('categories.0.budget', '200.00')
            ->where('categories.0.monthly_spent', 50)
        );
});

test('a user cannot update or delete another user category', function () {
    $otherUser = User::factory()->create();
    $foreignCategory = Category::factory()->for($otherUser)->create();

    $this->put(route('categories.update', $foreignCategory), [
        'name' => 'Hackeada',
        'icon' => 'tag',
        'color' => '#10B981',
    ])->assertForbidden();

    $this->delete(route('categories.destroy', $foreignCategory))->assertForbidden();

    $this->assertDatabaseCount('categories', 1);
});

test('a category with expenses cannot be deleted', function () {
    $category = Category::factory()->for($this->user)->create();
    $source = PaymentSource::factory()->for($this->user)->create();
    Expense::factory()->for($this->user)->for($category)->for($source, 'paymentSource')->create();

    $this->delete(route('categories.destroy', $category))->assertForbidden();

    $this->assertDatabaseCount('categories', 1);
});

test('an empty category can be deleted', function () {
    $category = Category::factory()->for($this->user)->create();

    $this->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'));

    $this->assertDatabaseCount('categories', 0);
});
