<?php

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('a user can list their categories via the API', function () {
    Category::factory()->for($this->user)->create(['name' => 'Comida', 'budget' => 100]);

    $this->getJson(route('api.v1.categories.index'))
        ->assertOk()
        ->assertJsonPath('data.categories.0.name', 'Comida')
        ->assertJsonStructure(['data' => ['categories', 'monthlyCount']]);
});

test('a user can create an expense category via the API', function () {
    $response = $this->postJson(route('api.v1.categories.store'), [
        'name' => 'Transporte',
        'icon' => 'car',
        'color' => '#FF0000',
        'type' => CategoryType::Expense->value,
        'budget' => 50,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    $category = Category::query()->first();

    expect($category->type)->toBe(CategoryType::Expense)
        ->and($category->budget)->toBe('50.00')
        ->and($category->is_system)->toBeFalse();
});

test('income categories never store a budget via the API', function () {
    $this->postJson(route('api.v1.categories.store'), [
        'name' => 'Salario',
        'icon' => 'briefcase',
        'color' => '#00FF00',
        'type' => CategoryType::Income->value,
        'budget' => 50,
    ])->assertStatus(201);

    expect(Category::query()->first()->budget)->toBeNull();
});

test('a user can update their category via the API', function () {
    $category = Category::factory()->for($this->user)->create(['name' => 'Viejo']);

    $this->putJson(route('api.v1.categories.update', $category), [
        'name' => 'Nuevo',
        'icon' => 'laptop',
        'color' => '#0000FF',
    ])->assertOk()
        ->assertJsonPath('success', true);

    expect($category->fresh()->name)->toBe('Nuevo');
});

test('a user cannot update another user category via the API', function () {
    $other = User::factory()->create();
    $category = Category::factory()->for($other)->create();

    $this->putJson(route('api.v1.categories.update', $category), [
        'name' => 'Hack',
        'icon' => 'x',
        'color' => '#000000',
    ])->assertStatus(403);
});

test('a user can delete their category via the API', function () {
    $category = Category::factory()->for($this->user)->create();

    $this->deleteJson(route('api.v1.categories.destroy', $category))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Category::query()->count())->toBe(0);
});
