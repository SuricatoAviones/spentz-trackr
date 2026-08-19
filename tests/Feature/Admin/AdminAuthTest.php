<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseReceipt;
use App\Models\PaymentSource;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a guest is redirected to login when visiting the admin panel', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('a regular user cannot access the admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('a regular user cannot access admin user management', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.users.show', $target))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('admin.users.update', $target), [
            'name' => 'Hackeado',
            'email' => 'hackeado@example.com',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('admin.users.destroy', $target))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.users.verify-email', $target))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.users.reset-password', $target), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.users.suspend', $target))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.users.reactivate', $target))
        ->assertForbidden();
});

test('a regular user cannot access admin expense management', function () {
    $user = User::factory()->create();
    $expense = Expense::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('admin.expenses.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('admin.expenses.destroy', $expense))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.expenses.export'))
        ->assertForbidden();

    $receipt = ExpenseReceipt::factory()->for($expense)->create();

    $this->actingAs($user)
        ->get(route('admin.expenses.receipts.show', $receipt))
        ->assertForbidden();
});

test('a regular user cannot access the admin audit log or system pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.audit.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.system.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.system.backup'))
        ->assertForbidden();
});

test('a regular user cannot access admin exchange rate management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.rates.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('admin.rates.update'), ['bcv' => 30])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.rates.sync'))
        ->assertForbidden();
});

test('a regular user cannot access admin category and source management', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $source = PaymentSource::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('admin.categories.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('admin.categories.update', $category), [
            'name' => 'Hack',
            'icon' => 'tag',
            'color' => '#10B981',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('admin.categories.destroy', $category))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.sources.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('admin.sources.update', $source), [
            'name' => 'Hack',
            'icon' => 'wallet',
            'color' => '#3B82F6',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('admin.sources.destroy', $source))
        ->assertForbidden();
});

test('an admin can access the admin dashboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('stats.total_users', 1)
            ->has('recentUsers', 1));
});

test('an admin can access the users list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 1));
});
