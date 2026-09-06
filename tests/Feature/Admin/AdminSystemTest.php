<?php

use App\Models\AdminAction;
use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

test('the system page shows status and data counts', function () {
    $target = User::factory()->create();
    $category = Category::factory()->for($target)->create();
    $source = PaymentSource::factory()->for($target)->create();
    Expense::factory()->for($target)->for($category)->for($source, 'paymentSource')->create();

    $this->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/system')
            ->where('status.database_ok', true)
            ->where('status.storage_writable', true)
            ->where('counts.users', 2)
            ->where('counts.expenses', 1)
            ->where('counts.categories', 1)
            ->where('counts.payment_sources', 1));
});

test('an admin can download a JSON backup of the database', function () {
    $target = User::factory()->create(['name' => 'Ana Backup']);
    Expense::factory()->for($target)->create(['description' => 'Gasto respaldado']);

    $response = $this->post(route('admin.system.backup'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/json');
    $response->assertDownload('spent_trackr_backup_'.now()->format('Y-m-d').'.json');

    $content = $response->streamedContent();
    expect($content)->toContain('Ana Backup');
    expect($content)->toContain('Gasto respaldado');
    expect($content)->not->toContain('two_factor_secret');
    expect($content)->not->toContain('remember_token');
    expect($content)->not->toContain('"password"');
});

test('generating a backup is recorded in the audit log', function () {
    $this->post(route('admin.system.backup'))->assertOk();

    expect(AdminAction::where('action', 'backup.generated')->count())->toBe(1);
});
