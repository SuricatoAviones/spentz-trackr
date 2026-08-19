<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseReceipt;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'name' => 'Admin Principal',
        'email' => 'admin@example.com',
    ]);
    $this->actingAs($this->admin);
});

test('admin expenses index lists expenses from all users', function () {
    $first = User::factory()->create(['name' => 'Ana Pérez']);
    $second = User::factory()->create(['name' => 'Luis Gómez']);
    $category = Category::factory()->for($first)->create(['name' => 'Comida']);
    $source = PaymentSource::factory()->for($first)->create(['name' => 'Efectivo']);

    Expense::factory()->for($first)->for($category)->for($source, 'paymentSource')
        ->on('2026-08-01')
        ->create(['description' => 'Almuerzo', 'amount' => 10, 'usd_amount' => 10, 'usdt_amount' => 10]);
    Expense::factory()->for($second)
        ->on('2026-08-05')
        ->create(['description' => 'Cena']);

    $this->get(route('admin.expenses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/expenses/index')
            ->has('expenses.data', 2)
            ->where('expenses.data.0.description', 'Cena')
            ->where('expenses.data.0.user.name', 'Luis Gómez')
            ->where('expenses.data.1.description', 'Almuerzo')
            ->where('expenses.data.1.category', 'Comida')
            ->where('expenses.data.1.source', 'Efectivo')
            ->where('expenses.data.1.currency', 'usd'));
});

test('admin expenses index filters by user, currency, category and dates', function () {
    $ana = User::factory()->create(['name' => 'Ana']);
    $luis = User::factory()->create(['name' => 'Luis']);
    $category = Category::factory()->for($ana)->create(['name' => 'Comida']);
    $source = PaymentSource::factory()->for($ana)->create(['name' => 'Efectivo']);

    Expense::factory()->for($ana)->for($category)->for($source, 'paymentSource')
        ->ves(30)
        ->on('2026-08-01')
        ->create(['description' => 'Mercado']);
    Expense::factory()->for($ana)->for($category)->for($source, 'paymentSource')
        ->on('2026-08-10')
        ->create(['description' => 'Restaurante', 'amount' => 20, 'usd_amount' => 20, 'usdt_amount' => 20]);
    Expense::factory()->for($luis)->create(['description' => 'Gasto ajeno']);

    $this->get(route('admin.expenses.index', ['user_id' => $luis->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('expenses.data', 1)
            ->where('expenses.data.0.description', 'Gasto ajeno'));

    $this->get(route('admin.expenses.index', ['currency' => 'ves']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('expenses.data', 1)
            ->where('expenses.data.0.description', 'Mercado'));

    $this->get(route('admin.expenses.index', ['category_id' => $category->id]))
        ->assertInertia(fn (Assert $page) => $page->has('expenses.data', 2));

    $this->get(route('admin.expenses.index', ['from' => '2026-08-01', 'to' => '2026-08-02']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('expenses.data', 1)
            ->where('expenses.data.0.description', 'Mercado'));

    $this->get(route('admin.expenses.index', ['search' => 'Restaurante']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('expenses.data', 1)
            ->where('expenses.data.0.description', 'Restaurante'));
});

test('an admin can delete any expense', function () {
    $target = User::factory()->create();
    $expense = Expense::factory()->for($target)->create();

    $this->delete(route('admin.expenses.destroy', $expense))
        ->assertRedirect(route('admin.expenses.index'));

    $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
});

test('admin expenses index exposes users and categories for the filters', function () {
    $target = User::factory()->create(['name' => 'Ana Pérez']);
    $category = Category::factory()->for($target)->create(['name' => 'Comida']);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users', 2)
            ->where('users.1.name', 'Ana Pérez')
            ->has('categories', 1)
            ->where('categories.0.name', 'Comida')
            ->where('categories.0.user_name', 'Ana Pérez'));
});

test('an admin can export all expenses to CSV', function () {
    $target = User::factory()->create(['name' => 'Ana Pérez']);
    $category = Category::factory()->for($target)->create(['name' => 'Comida']);
    $source = PaymentSource::factory()->for($target)->create(['name' => 'Efectivo']);
    Expense::factory()->for($target)->for($category)->for($source, 'paymentSource')
        ->on('2026-08-01')
        ->create([
            'description' => 'Almuerzo',
            'amount' => 10,
            'exchange_rate' => 1.25,
            'rate_provider' => 'api',
            'usd_amount' => 10,
            'usdt_amount' => 10,
            'note' => 'Con amigos',
        ]);

    $response = $this->get(route('admin.expenses.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $response->assertDownload('gastos_globales_'.now()->format('Y-m-d').'.csv');

    $content = $response->streamedContent();
    expect($content)->toContain('fecha');
    expect($content)->toContain('Almuerzo');
    expect($content)->toContain('Ana Pérez');
    expect($content)->toContain('Con amigos');
});

test('admin CSV export respects the filters', function () {
    $ana = User::factory()->create(['name' => 'Ana']);
    $luis = User::factory()->create(['name' => 'Luis']);

    Expense::factory()->for($ana)->create(['description' => 'Gasto Ana']);
    Expense::factory()->for($luis)->create(['description' => 'Gasto Luis']);

    $this->get(route('admin.expenses.export', ['user_id' => $luis->id]))
        ->assertOk();

    $content = $this->get(route('admin.expenses.export', ['user_id' => $luis->id]))->streamedContent();
    expect($content)->toContain('Gasto Luis');
    expect($content)->not->toContain('Gasto Ana');
});

test('the admin expenses index exposes the receipts of each expense', function () {
    $target = User::factory()->create();
    $expense = Expense::factory()->for($target)->create(['description' => 'Almuerzo']);
    ExpenseReceipt::factory()->for($expense)->create([
        'path' => 'receipts/uno.jpg',
        'original_name' => 'comprobante.jpg',
    ]);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('expenses.data.0.receipts', 1)
            ->where('expenses.data.0.receipts.0.original_name', 'comprobante.jpg'));
});

test('an admin can download an expense receipt', function () {
    Storage::fake('public');
    Storage::disk('public')->put('receipts/factura.pdf', 'contenido del pdf');

    $target = User::factory()->create();
    $expense = Expense::factory()->for($target)->create();
    $receipt = ExpenseReceipt::factory()->for($expense)->create([
        'path' => 'receipts/factura.pdf',
        'original_name' => 'factura.pdf',
    ]);

    $response = $this->get(route('admin.expenses.receipts.show', $receipt));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->streamedContent())->toBe('contenido del pdf');
});
