<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultCategoriesSeeder extends Seeder
{
    /**
     * Default categories created for each new user.
     *
     * @var array<int, array{name: string, icon: string, color: string}>
     */
    public const DEFAULT_CATEGORIES = [
        ['name' => 'Alimentación', 'icon' => 'shopping-cart', 'color' => '#10B981'],
        ['name' => 'Transporte', 'icon' => 'car', 'color' => '#3B82F6'],
        ['name' => 'Servicios', 'icon' => 'zap', 'color' => '#F59E0B'],
        ['name' => 'Salud', 'icon' => 'heart-pulse', 'color' => '#EF4444'],
        ['name' => 'Ocio', 'icon' => 'gamepad-2', 'color' => '#8B5CF6'],
        ['name' => 'Ropa', 'icon' => 'shirt', 'color' => '#EC4899'],
        ['name' => 'Educación', 'icon' => 'graduation-cap', 'color' => '#06B6D4'],
        ['name' => 'Otros', 'icon' => 'tag', 'color' => '#6B7280'],
    ];

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
