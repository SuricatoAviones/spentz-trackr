<?php

namespace Database\Factories;

use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentSource>
 */
class PaymentSourceFactory extends Factory
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
            'name' => fake()->unique()->randomElement(['Binance', 'Bancos', 'Wallets', 'Efectivo']),
            'icon' => fake()->randomElement(['bitcoin', 'landmark', 'wallet', 'banknote']),
            'color' => fake()->randomElement(['#3B82F6', '#10B981', '#8B5CF6', '#F59E0B']),
            'is_system' => false,
        ];
    }
}
