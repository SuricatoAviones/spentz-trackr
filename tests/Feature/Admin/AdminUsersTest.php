<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

test('admin users index lists users with expense metrics', function () {
    $target = User::factory()->create([
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'created_at' => now()->subDay(),
    ]);
    $category = Category::factory()->for($target)->create();
    $source = PaymentSource::factory()->for($target)->create();
    Expense::factory()->for($target)->for($category)->for($source, 'paymentSource')
        ->on(today()->toDateString())
        ->create(['amount' => 100, 'usd_amount' => 100, 'usdt_amount' => 100]);

    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 2)
            ->where('users.data.1.name', 'Ana Pérez')
            ->where('users.data.1.expenses_count', 1)
            ->where('users.data.1.total_usd', 100)
            ->where('users.data.1.last_expense_at', today()->toDateString()));
});

test('admin users index filters by name or email', function () {
    User::factory()->create(['name' => 'Pedro', 'email' => 'pedro@example.com']);
    User::factory()->create(['name' => 'María', 'email' => 'maria@example.com']);

    $this->get(route('admin.users.index', ['search' => 'pedro']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Pedro'));

    $this->get(route('admin.users.index', ['search' => 'maria@example.com']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.email', 'maria@example.com'));
});

test('admin users show exposes user details and recent expenses', function () {
    $target = User::factory()->create(['name' => 'Carlos', 'email' => 'carlos@example.com']);
    $category = Category::factory()->for($target)->create(['name' => 'Comida']);
    $source = PaymentSource::factory()->for($target)->create(['name' => 'Efectivo']);
    Expense::factory()->for($target)->for($category)->for($source, 'paymentSource')
        ->create(['description' => 'Almuerzo', 'amount' => 25, 'usd_amount' => 25, 'usdt_amount' => 25]);

    $this->get(route('admin.users.show', $target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/show')
            ->where('user.name', 'Carlos')
            ->where('stats.categories_count', 1)
            ->where('recentExpenses.0.description', 'Almuerzo'));
});

test('an admin can update a user name, email and role', function () {
    $target = User::factory()->create(['name' => 'Viejo', 'email' => 'viejo@example.com']);

    $this->patch(route('admin.users.update', $target), [
        'name' => 'Nuevo',
        'email' => 'nuevo@example.com',
        'is_admin' => true,
    ])->assertRedirect(route('admin.users.show', $target));

    $target->refresh();

    expect($target->name)->toBe('Nuevo')
        ->and($target->email)->toBe('nuevo@example.com')
        ->and($target->is_admin)->toBeTrue();
});

test('an admin cannot assign an email that already exists', function () {
    User::factory()->create(['email' => 'tomado@example.com']);
    $target = User::factory()->create();

    $this->patch(route('admin.users.update', $target), [
        'name' => 'Nombre',
        'email' => 'tomado@example.com',
    ])->assertSessionHasErrors('email');
});

test('an admin can delete another user and its data', function () {
    $target = User::factory()->create();
    $category = Category::factory()->for($target)->create();
    $source = PaymentSource::factory()->for($target)->create();
    Expense::factory()->for($target)->for($category)->for($source, 'paymentSource')->create();

    $this->delete(route('admin.users.destroy', $target))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
    $this->assertDatabaseMissing('expenses', ['user_id' => $target->id]);
    $this->assertDatabaseMissing('categories', ['user_id' => $target->id]);
    $this->assertDatabaseMissing('payment_sources', ['user_id' => $target->id]);
});

test('an admin cannot delete their own account', function () {
    $this->delete(route('admin.users.destroy', $this->admin))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
});

test('the admin dashboard shows global statistics', function () {
    $target = User::factory()->create();
    $category = Category::factory()->for($target)->create();
    $source = PaymentSource::factory()->for($target)->create();
    Expense::factory()->for($target)->for($category)->for($source, 'paymentSource')
        ->create(['amount' => 50, 'usd_amount' => 50, 'usdt_amount' => 50]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.total_users', 2)
            ->where('stats.active_users', 1)
            ->where('stats.total_expenses', 1)
            ->where('stats.total_usd', 50));
});

test('the admin dashboard shows the monthly trend for the current year', function () {
    $target = User::factory()->create();
    $category = Category::factory()->for($target)->create();
    $source = PaymentSource::factory()->for($target)->create();

    Expense::factory()->for($target)->for($category)->for($source, 'paymentSource')
        ->on(now()->format('Y-m').'-05')
        ->create(['description' => 'Este mes', 'amount' => 30, 'usd_amount' => 30, 'usdt_amount' => 30]);
    Expense::factory()->for($target)->for($category)->for($source, 'paymentSource')
        ->on(now()->startOfYear()->toDateString())
        ->create(['description' => 'Enero', 'amount' => 10, 'usd_amount' => 10, 'usdt_amount' => 10]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('monthlyTrend', 12)
            ->where('monthlyTrend.0.total', 10)
            ->where('monthlyTrend.'.(now()->month - 1).'.total', 30));
});

test('the admin dashboard ranks top categories and top users by total spend', function () {
    $ana = User::factory()->create(['name' => 'Ana']);
    $luis = User::factory()->create(['name' => 'Luis']);

    $comida = Category::factory()->for($ana)->create(['name' => 'Comida', 'color' => '#10B981']);
    $transporte = Category::factory()->for($luis)->create(['name' => 'Transporte', 'color' => '#3B82F6']);
    $sourceAna = PaymentSource::factory()->for($ana)->create();
    $sourceLuis = PaymentSource::factory()->for($luis)->create();

    Expense::factory()->for($ana)->for($comida)->for($sourceAna, 'paymentSource')
        ->create(['amount' => 80, 'usd_amount' => 80, 'usdt_amount' => 80]);
    Expense::factory()->for($luis)->for($transporte)->for($sourceLuis, 'paymentSource')
        ->create(['amount' => 20, 'usd_amount' => 20, 'usdt_amount' => 20]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('topCategories', 2)
            ->where('topCategories.0.name', 'Comida')
            ->where('topCategories.0.total', 80)
            ->where('topCategories.0.color', '#10B981')
            ->where('topCategories.0.percent', 80)
            ->has('topUsers', 2)
            ->where('topUsers.0.name', 'Ana')
            ->where('topUsers.0.total', 80)
            ->where('topUsers.0.percent', 80)
            ->where('topUsers.1.name', 'Luis'));
});

test('an admin can mark a user email as verified', function () {
    $target = User::factory()->create(['email_verified_at' => null]);

    $this->post(route('admin.users.verify-email', $target))
        ->assertSessionHas('success');

    expect($target->refresh()->email_verified_at)->not->toBeNull();
});

test('an admin cannot verify an already verified email', function () {
    $target = User::factory()->create();

    $this->post(route('admin.users.verify-email', $target))
        ->assertSessionHas('error');

    expect($target->refresh()->email_verified_at)->not->toBeNull();
});

test('an admin can reset a user password', function () {
    $target = User::factory()->create();

    $this->post(route('admin.users.reset-password', $target), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHas('success');

    expect(Hash::check('new-password', $target->refresh()->password))->toBeTrue();
});

test('reset password requires a confirmation', function () {
    $target = User::factory()->create();

    $this->post(route('admin.users.reset-password', $target), [
        'password' => 'new-password',
    ])->assertSessionHasErrors('password');
});
