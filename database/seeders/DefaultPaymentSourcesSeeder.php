<?php

namespace Database\Seeders;

use App\Actions\Users\AssignDefaultUserDataAction;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Backfill the default payment sources for users created before
 * AssignDefaultUserDataAction existed. New users get them at creation time
 * (Fortify registration and `admin:create`), so this is a one-off repair tool.
 */
class DefaultPaymentSourcesSeeder extends Seeder
{
    /**
     * Default payment sources created for each new user.
     *
     * @var array<int, array{name: string, icon: string, color: string}>
     */
    public const DEFAULT_SOURCES = AssignDefaultUserDataAction::DEFAULT_PAYMENT_SOURCES;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()
            ->whereDoesntHave('paymentSources')
            ->get();

        foreach ($users as $user) {
            foreach (self::DEFAULT_SOURCES as $source) {
                $user->paymentSources()->create([...$source, 'is_system' => true]);
            }
        }
    }
}
