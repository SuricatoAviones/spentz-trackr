<?php

use App\Models\AdminAction;
use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Principal']);
    $this->actingAs($this->admin);
});

test('admin actions are recorded in the audit log', function () {
    $target = User::factory()->unverified()->create();

    $this->patch(route('admin.users.update', $target), [
        'name' => 'Nuevo nombre',
        'email' => $target->email,
    ]);
    $this->post(route('admin.users.verify-email', $target));
    $this->post(route('admin.users.suspend', $target));
    $this->delete(route('admin.users.destroy', $target));

    $this->assertDatabaseHas('admin_actions', ['action' => 'user.updated']);
    $this->assertDatabaseHas('admin_actions', ['action' => 'user.verified']);
    $this->assertDatabaseHas('admin_actions', ['action' => 'user.suspended']);
    $this->assertDatabaseHas('admin_actions', ['action' => 'user.deleted']);

    expect(AdminAction::count())->toBe(4);
});

test('expense, category and source admin actions are recorded', function () {
    $target = User::factory()->create();
    $expense = Expense::factory()->for($target)->create();
    $category = Category::factory()->for($target)->create();
    $source = PaymentSource::factory()->for($target)->create();

    $this->delete(route('admin.expenses.destroy', $expense));
    $this->patch(route('admin.categories.update', $category), [
        'name' => 'Nueva categoría',
        'icon' => 'tag',
        'color' => '#10B981',
    ]);
    $this->delete(route('admin.categories.destroy', $category));
    $this->delete(route('admin.sources.destroy', $source));

    $this->assertDatabaseHas('admin_actions', ['action' => 'expense.deleted']);
    $this->assertDatabaseHas('admin_actions', ['action' => 'category.updated']);
    $this->assertDatabaseHas('admin_actions', ['action' => 'category.deleted']);
    $this->assertDatabaseHas('admin_actions', ['action' => 'source.deleted']);
});

test('the audit page lists recorded actions with admin and target', function () {
    $target = User::factory()->create(['name' => 'Ana Pérez']);
    $this->post(route('admin.users.suspend', $target));

    $this->get(route('admin.audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit/index')
            ->has('actions.data', 1)
            ->where('actions.data.0.action', 'user.suspended')
            ->where('actions.data.0.admin.name', 'Admin Principal')
            ->where('actions.data.0.target', 'Ana Pérez'));
});

test('audit records survive when the target user is deleted', function () {
    $target = User::factory()->create();
    $this->post(route('admin.users.suspend', $target));

    $this->delete(route('admin.users.destroy', $target));

    expect(AdminAction::count())->toBe(2);
    expect(AdminAction::first()->target_type)->toBe(User::class);
    expect(AdminAction::first()->target_id)->toBe($target->id);
});
