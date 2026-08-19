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

test('sources index lists only the user sources with totals', function () {
    PaymentSource::factory()->for($this->user)->create(['name' => 'Binance']);
    $source = PaymentSource::factory()->for($this->user)->create(['name' => 'Efectivo']);
    $category = Category::factory()->for($this->user)->create();
    Expense::factory()->for($this->user)->for($category)->for($source, 'paymentSource')
        ->create(['amount' => 50, 'usd_amount' => 50, 'usdt_amount' => 50]);

    $otherUser = User::factory()->create();
    PaymentSource::factory()->for($otherUser)->create(['name' => 'Secreto']);

    $this->get(route('sources.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sources/index')
            ->has('sources', 2)
            ->where('sources.0.name', 'Binance')
            ->where('sources.1.name', 'Efectivo')
            ->where('sources.1.expenses_count', 1)
            ->where('sources.1.total_usd', 50)
        );
});

test('a user can create a source', function () {
    $this->post(route('sources.store'), [
        'name' => 'Zelle',
        'icon' => 'banknote',
        'color' => '#3B82F6',
    ])->assertRedirect(route('sources.index'));

    $this->assertDatabaseHas('payment_sources', [
        'user_id' => $this->user->id,
        'name' => 'Zelle',
        'icon' => 'banknote',
        'color' => '#3B82F6',
    ]);
});

test('a user cannot create a source with an invalid color', function () {
    $this->post(route('sources.store'), [
        'name' => 'Zelle',
        'icon' => 'banknote',
        'color' => 'azul',
    ])->assertSessionHasErrors(['color']);

    $this->assertDatabaseCount('payment_sources', 0);
});

test('a user can update their own source', function () {
    $source = PaymentSource::factory()->for($this->user)->create(['name' => 'Wallets']);

    $this->put(route('sources.update', $source), [
        'name' => 'Billeteras digitales',
        'icon' => 'wallet',
        'color' => '#8B5CF6',
    ])->assertRedirect(route('sources.index'));

    expect($source->refresh()->name)->toBe('Billeteras digitales');
});

test('a user cannot update or delete another user source', function () {
    $otherUser = User::factory()->create();
    $foreignSource = PaymentSource::factory()->for($otherUser)->create();

    $this->put(route('sources.update', $foreignSource), [
        'name' => 'Hackeado',
        'icon' => 'wallet',
        'color' => '#10B981',
    ])->assertForbidden();

    $this->delete(route('sources.destroy', $foreignSource))->assertForbidden();

    $this->assertDatabaseCount('payment_sources', 1);
});

test('a source with expenses cannot be deleted', function () {
    $source = PaymentSource::factory()->for($this->user)->create();
    $category = Category::factory()->for($this->user)->create();
    Expense::factory()->for($this->user)->for($category)->for($source, 'paymentSource')->create();

    $this->delete(route('sources.destroy', $source))->assertForbidden();

    $this->assertDatabaseCount('payment_sources', 1);
});

test('an empty source can be deleted', function () {
    $source = PaymentSource::factory()->for($this->user)->create();

    $this->delete(route('sources.destroy', $source))
        ->assertRedirect(route('sources.index'));

    $this->assertDatabaseCount('payment_sources', 0);
});
