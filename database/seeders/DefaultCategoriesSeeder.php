<?php

namespace Database\Seeders;

use App\Actions\Users\AssignDefaultUserDataAction;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Backfill the default categories for users created before
 * AssignDefaultUserDataAction existed. New users get them at creation time
 * (Fortify registration and `admin:create`), so this is a one-off repair tool.
 */
class DefaultCategoriesSeeder extends Seeder
{
    /**
     * Default categories created for each new user.
     *
     * @var array<int, array{name: string, icon: string, color: string}>
     */
    public const DEFAULT_CATEGORIES = AssignDefaultUserDataAction::DEFAULT_CATEGORIES;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()
            ->whereDoesntHave('categories')
            ->get();

        foreach ($users as $user) {
            foreach (self::DEFAULT_CATEGORIES as $category) {
                $user->categories()->create([...$category, 'is_system' => true]);
            }
        }
    }
}
