<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'name' => 'Admin Principal',
        'email' => 'admin@example.com',
    ]);
    $this->actingAs($this->admin);
});

test('admin categories index lists categories from all users with metrics', function () {
    $target = User::factory()->create(['name' => 'Ana Pérez']);
    $category = Category::factory()->for($target)->create([
        'name' => 'Comida',
        'budget' => 200,
    ]);
    $source = PaymentSource::factory()->for($target)->create();
    Expense::factory()->for($target)->for($category)->for($source, 'paymentSource')
        ->create(['amount' => 50, 'usd_amount' => 50, 'usdt_amount' => 50]);

    $this->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/categories/index')
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Comida')
            ->where('categories.data.0.user.name', 'Ana Pérez')
            ->where('categories.data.0.expenses_count', 1)
            ->where('categories.data.0.total_usd', 50)
            ->where('categories.data.0.can_delete', false)
            ->where('categories.data.0.budget', '200.00'));
});

test('admin categories index filters by user and search', function () {
    $ana = User::factory()->create(['name' => 'Ana']);
    $luis = User::factory()->create(['name' => 'Luis']);
    Category::factory()->for($ana)->create(['name' => 'Comida']);
    Category::factory()->for($luis)->create(['name' => 'Transporte']);

    $this->get(route('admin.categories.index', ['user_id' => $luis->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Transporte'));

    $this->get(route('admin.categories.index', ['search' => 'comida']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Comida'));
});

test('an admin can rename a category of another user', function () {
    $target = User::factory()->create();
    $category = Category::factory()->for($target)->create(['name' => 'Viejo']);

    $this->patch(route('admin.categories.update', $category), [
        'name' => 'Nuevo',
        'icon' => 'tag',
        'color' => '#10B981',
    ])->assertSessionHas('success');

    expect($category->refresh()->name)->toBe('Nuevo');
});

test('an admin cannot delete a category with expenses', function () {
    $target = User::factory()->create();
    $category = Category::factory()->for($target)->create();
    $source = PaymentSource::factory()->for($target)->create();
    Expense::factory()->for($target)->for($category)->for($source, 'paymentSource')->create();

    $this->delete(route('admin.categories.destroy', $category))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('an admin can delete a category without expenses', function () {
    $target = User::factory()->create();
    $category = Category::factory()->for($target)->create();

    $this->delete(route('admin.categories.destroy', $category))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('admin sources index lists sources from all users with metrics', function () {
    $target = User::factory()->create(['name' => 'Ana Pérez']);
    $source = PaymentSource::factory()->for($target)->create(['name' => 'Efectivo']);

    $this->get(route('admin.sources.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/sources/index')
            ->has('sources.data', 1)
            ->where('sources.data.0.name', 'Efectivo')
            ->where('sources.data.0.user.name', 'Ana Pérez')
            ->where('sources.data.0.can_delete', true));
});

test('an admin can rename a source of another user', function () {
    $target = User::factory()->create();
    $source = PaymentSource::factory()->for($target)->create(['name' => 'Viejo']);

    $this->patch(route('admin.sources.update', $source), [
        'name' => 'Nuevo',
        'icon' => 'wallet',
        'color' => '#3B82F6',
    ])->assertSessionHas('success');

    expect($source->refresh()->name)->toBe('Nuevo');
});

test('an admin cannot delete a source with expenses', function () {
    $target = User::factory()->create();
    $category = Category::factory()->for($target)->create();
    $source = PaymentSource::factory()->for($target)->create();
    Expense::factory()->for($target)->for($category)->for($source, 'paymentSource')->create();

    $this->delete(route('admin.sources.destroy', $source))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('payment_sources', ['id' => $source->id]);
});

test('an admin can delete a source without expenses', function () {
    $target = User::factory()->create();
    $source = PaymentSource::factory()->for($target)->create();

    $this->delete(route('admin.sources.destroy', $source))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('payment_sources', ['id' => $source->id]);
});
