<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsGoal>
 */
class SavingsGoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $targetAmount = fake()->randomFloat(2, 100, 5000);

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'target_amount' => $targetAmount,
            'currency' => Currency::Usd,
            'exchange_rate' => null,
            'target_usd_amount' => $targetAmount,
            'icon' => 'piggy-bank',
            'color' => '#10B981',
            'deadline' => null,
            'note' => null,
        ];
    }
}
