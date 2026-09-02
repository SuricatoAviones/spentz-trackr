<?php

use App\Enums\Currency;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
            ['moneda' => 'USD', 'fuente' => 'paralelo', 'promedio' => 32.10],
        ]),
    ]);

    $this->user = User::factory()->create(['tracking_type' => 'income']);
    $this->category = Category::factory()->for($this->user)->income()->create();
    $this->actingAs($this->user);
});

test('guests are redirected to the login page', function () {
    auth()->logout();

    $this->get(route('incomes.index'))->assertRedirect(route('login'));
});

test('a user can register an income in USD', function () {
    $response = $this->post(route('incomes.store'), [
        'category_id' => $this->category->id,
        'currency' => Currency::Usd->value,
        'amount' => 250,
        'description' => 'Salario',
        'received_at' => now()->toDateString(),
    ]);

    $income = Income::query()->first();

    $response->assertRedirect(route('incomes.show', $income));
    $this->assertDatabaseHas('incomes', [
        'id' => $income->id,
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'currency' => Currency::Usd->value,
        'amount' => 250,
        'usd_amount' => 250,
        'usdt_amount' => 250,
        'exchange_rate' => null,
    ]);
});

test('a user can register an income in Bs freezing the exchange rate', function () {
    $this->post(route('incomes.store'), [
        'category_id' => $this->category->id,
        'currency' => Currency::Ves->value,
        'amount' => 1400,
        'exchange_rate' => 28.5,
        'description' => 'Venta',
        'received_at' => now()->toDateString(),
    ])->assertRedirect();

    $income = Income::query()->first();

    expect($income->currency)->toBe(Currency::Ves)
        ->and($income->exchange_rate)->toBe('28.5000')
        ->and($income->usd_amount)->toBe('49.12')
        ->and($income->usdt_amount)->toBe('49.12');
});

test('an income in USDT is stored with 1:1 equivalents', function () {
    $this->post(route('incomes.store'), [
        'category_id' => $this->category->id,
        'currency' => Currency::Usdt->value,
        'amount' => 80,
        'description' => 'Pago USDT',
        'received_at' => now()->toDateString(),
    ])->assertRedirect();

    $income = Income::query()->first();

    expect($income->usd_amount)->toBe('80.00')
        ->and($income->usdt_amount)->toBe('80.00')
        ->and($income->exchange_rate)->toBeNull();
});

test('income validation rejects foreign and expense categories', function () {
    $otherUser = User::factory()->create();
    $foreignCategory = Category::factory()->for($otherUser)->income()->create();
    $expenseCategory = Category::factory()->for($this->user)->create();

    $this->post(route('incomes.store'), [
        'category_id' => $foreignCategory->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Invalido',
        'received_at' => now()->toDateString(),
    ])->assertSessionHasErrors('category_id');

    $this->post(route('incomes.store'), [
        'category_id' => $expenseCategory->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Invalido',
        'received_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors(['category_id', 'received_at']);

    $this->assertDatabaseCount('incomes', 0);
});

test('a user can only see their own incomes and only income categories are listed', function () {
    Income::factory()->for($this->user)->for($this->category)->create();
    $otherUser = User::factory()->create();
    Income::factory()->for($otherUser)->create();
    Category::factory()->for($this->user)->create(['name' => 'Gasto categoria']);

    $this->get(route('incomes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('incomes/index')
            ->has('incomes.data', 1)
            ->has('categories', 1)
            ->where('categories.0.id', $this->category->id)
        );
});

test('the income create page only exposes income categories', function () {
    Category::factory()->for($this->user)->create(['name' => 'Gasto categoria']);

    $this->get(route('incomes.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('incomes/create')
            ->has('categories', 1)
            ->where('categories.0.id', $this->category->id)
        );
});

test('a user cannot view, edit or delete another user income', function () {
    $otherUser = User::factory()->create();
    $foreignIncome = Income::factory()->for($otherUser)->create();

    $this->get(route('incomes.show', $foreignIncome))->assertForbidden();
    $this->get(route('incomes.edit', $foreignIncome))->assertForbidden();
    $this->put(route('incomes.update', $foreignIncome), [
        'category_id' => $this->category->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Hack',
        'received_at' => now()->toDateString(),
    ])->assertForbidden();
    $this->delete(route('incomes.destroy', $foreignIncome))->assertForbidden();

    $this->assertDatabaseCount('incomes', 1);
});

test('a user can update an income and the conversion is recalculated', function () {
    $income = Income::factory()
        ->for($this->user)
        ->for($this->category)
        ->create(['amount' => 10, 'usd_amount' => 10, 'usdt_amount' => 10]);

    $this->put(route('incomes.update', $income), [
        'category_id' => $this->category->id,
        'currency' => Currency::Ves->value,
        'amount' => 100,
        'exchange_rate' => 20,
        'rate_provider' => 'bcv',
        'description' => 'Actualizado',
        'received_at' => now()->toDateString(),
    ])->assertRedirect(route('incomes.show', $income));

    $income->refresh();

    expect($income->currency)->toBe(Currency::Ves)
        ->and($income->exchange_rate)->toBe('20.0000')
        ->and($income->rate_provider)->toBe('bcv')
        ->and($income->usd_amount)->toBe('5.00')
        ->and($income->usdt_amount)->toBe('5.00');
});

test('a user can delete their own income', function () {
    $income = Income::factory()
        ->for($this->user)
        ->for($this->category)
        ->create();

    $this->delete(route('incomes.destroy', $income))
        ->assertRedirect(route('incomes.index'));

    $this->assertDatabaseCount('incomes', 0);
});

test('an income can store a receipt image and remove it', function () {
    Storage::fake('public');

    $income = Income::factory()
        ->for($this->user)
        ->for($this->category)
        ->create();

    $this->put(route('incomes.update', $income), [
        'category_id' => $this->category->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Con comprobante',
        'received_at' => now()->toDateString(),
        'receipt' => UploadedFile::fake()->image('comprobante.jpg', 100, 100),
    ])->assertRedirect();

    expect($income->receipts()->count())->toBe(1);
    Storage::disk('public')->assertExists($income->receipts()->first()->path);

    $this->put(route('incomes.update', $income), [
        'category_id' => $this->category->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Con comprobante',
        'received_at' => now()->toDateString(),
        'remove_receipt' => true,
    ])->assertRedirect();

    expect($income->receipts()->count())->toBe(0);
});

test('the incomes index can be filtered by search and currency', function () {
    Income::factory()
        ->for($this->user)
        ->for($this->category)
        ->create(['description' => 'Salario']);
    Income::factory()
        ->for($this->user)
        ->for($this->category)
        ->usdt()
        ->create(['description' => 'Regalia']);

    $this->get(route('incomes.index', ['search' => 'salario']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('incomes/index')
            ->has('incomes.data', 1)
            ->where('incomes.data.0.description', 'Salario')
        );

    $this->get(route('incomes.index', ['currency' => Currency::Usdt->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('incomes/index')
            ->has('incomes.data', 1)
            ->where('incomes.data.0.description', 'Regalia')
        );
});

test('income pages are blocked when tracking type is expense only', function () {
    $this->user->update(['tracking_type' => 'expenses']);

    $this->get(route('incomes.index'))->assertNotFound();
    $this->get(route('incomes.create'))->assertNotFound();
    $this->post(route('incomes.store'), [
        'category_id' => $this->category->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Bloqueado',
        'received_at' => now()->toDateString(),
    ])->assertNotFound();

    $this->assertDatabaseCount('incomes', 0);
});

test('expense pages are blocked when tracking type is income only', function () {
    $source = PaymentSource::factory()->for($this->user)->create();
    $expenseCategory = Category::factory()->for($this->user)->create();

    $this->get(route('expenses.index'))->assertNotFound();
    $this->post(route('expenses.store'), [
        'category_id' => $expenseCategory->id,
        'payment_source_id' => $source->id,
        'currency' => Currency::Usd->value,
        'amount' => 10,
        'description' => 'Bloqueado',
        'spent_at' => now()->toDateString(),
    ])->assertNotFound();

    $this->assertDatabaseCount('expenses', 0);
});

test('expense pages work for expense only tracking', function () {
    $this->user->update(['tracking_type' => 'expenses']);
    $expenseCategory = Category::factory()->for($this->user)->create();
    $source = PaymentSource::factory()->for($this->user)->create();
    Expense::factory()
        ->for($this->user)
        ->for($expenseCategory)
        ->for($source, 'paymentSource')
        ->create();

    $this->get(route('expenses.index'))->assertOk();
});
