<?php

/*
 * Regresión del hallazgo "los comprobantes son públicos".
 *
 * Vivían en el disco `public`, servido directo desde `public/storage` sin pasar
 * por Laravel: cualquiera con la URL veía la factura de cualquier usuario. Ahora
 * viven en un disco privado y solo salen por una ruta con política.
 */

use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentSource;
use App\Models\User;
use App\Support\Presenters\ExpensePresenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('receipts');

    $this->user = User::factory()->create();
    $this->category = Category::factory()->for($this->user)->create();
    $this->source = PaymentSource::factory()->for($this->user)->create();
});

function expenseWithReceipt(User $user, Category $category, PaymentSource $source): Expense
{
    test()->actingAs($user)->post(route('expenses.store'), [
        'category_id' => $category->id,
        'payment_source_id' => $source->id,
        'currency' => 'usd',
        'amount' => 10,
        'description' => 'Con comprobante',
        'spent_at' => now()->toDateString(),
        'receipt' => UploadedFile::fake()->image('factura.jpg', 60, 60),
    ])->assertRedirect();

    return Expense::query()->latest('id')->firstOrFail();
}

test('receipts are never stored where the web server can serve them', function () {
    $expense = expenseWithReceipt($this->user, $this->category, $this->source);
    $path = $expense->receipts()->firstOrFail()->path;

    Storage::disk('receipts')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

test('the receipt url is an authorized route, not a public file', function () {
    $expense = expenseWithReceipt($this->user, $this->category, $this->source);
    $receipt = $expense->receipts()->firstOrFail();

    $url = ExpensePresenter::present(
        $expense->load(['category', 'paymentSource', 'receipts', 'items'])
    )['receipts'][0]['url'];

    expect($url)->toBe(route('expenses.receipts.show', [$expense->id, $receipt->id]))
        ->and($url)->not->toContain('/storage/');
});

test('the owner can download their own receipt', function () {
    $expense = expenseWithReceipt($this->user, $this->category, $this->source);
    $receipt = $expense->receipts()->firstOrFail();

    $this->actingAs($this->user)
        ->get(route('expenses.receipts.show', [$expense->id, $receipt->id]))
        ->assertOk();
});

test('another user cannot download someone elses receipt', function () {
    $expense = expenseWithReceipt($this->user, $this->category, $this->source);
    $receipt = $expense->receipts()->firstOrFail();

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('expenses.receipts.show', [$expense->id, $receipt->id]))
        ->assertForbidden();
});

test('a guest cannot download a receipt', function () {
    $expense = expenseWithReceipt($this->user, $this->category, $this->source);
    $receipt = $expense->receipts()->firstOrFail();

    // El helper deja al dueño autenticado; aquí queremos el caso anónimo.
    auth()->logout();

    $this->get(route('expenses.receipts.show', [$expense->id, $receipt->id]))
        ->assertRedirect(route('login'));
});

test('a receipt cannot be fetched through a different expense of the same user', function () {
    $withReceipt = expenseWithReceipt($this->user, $this->category, $this->source);
    $receipt = $withReceipt->receipts()->firstOrFail();

    $other = Expense::factory()
        ->for($this->user)
        ->for($this->category)
        ->for($this->source, 'paymentSource')
        ->create();

    $this->actingAs($this->user)
        ->get(route('expenses.receipts.show', [$other->id, $receipt->id]))
        ->assertNotFound();
});

test('income receipts are protected the same way', function () {
    $user = User::factory()->create(['tracking_type' => 'income']);
    $category = Category::factory()->for($user)->income()->create();

    $this->actingAs($user)->post(route('incomes.store'), [
        'category_id' => $category->id,
        'currency' => 'usd',
        'amount' => 100,
        'description' => 'Con comprobante',
        'received_at' => now()->toDateString(),
        'receipt' => UploadedFile::fake()->image('recibo.jpg', 60, 60),
    ])->assertRedirect();

    $income = Income::query()->latest('id')->firstOrFail();
    $receipt = $income->receipts()->firstOrFail();

    Storage::disk('public')->assertMissing($receipt->path);

    $this->actingAs($user)
        ->get(route('incomes.receipts.show', [$income->id, $receipt->id]))
        ->assertOk();

    $this->actingAs(User::factory()->create(['tracking_type' => 'income']))
        ->get(route('incomes.receipts.show', [$income->id, $receipt->id]))
        ->assertForbidden();
});

test('deleting an account wipes its receipt files from disk', function () {
    $expense = expenseWithReceipt($this->user, $this->category, $this->source);
    $path = $expense->receipts()->firstOrFail()->path;

    Storage::disk('receipts')->assertExists($path);

    $this->user->delete();

    // Las filas caen por cascadeOnDelete; el fichero no lo sabía y quedaba
    // huérfano y accesible para siempre.
    Storage::disk('receipts')->assertMissing($path);
});
