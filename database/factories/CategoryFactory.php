<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->randomElement(['Alimentación', 'Transporte', 'Servicios', 'Salud', 'Ocio', 'Ropa', 'Educación', 'Otros']),
            'icon' => fake()->randomElement(['shopping-cart', 'car', 'zap', 'heart-pulse', 'gamepad-2', 'shirt', 'graduation-cap', 'tag']),
            'color' => fake()->randomElement(['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#6B7280']),
            'is_system' => false,
        ];
    }
}
