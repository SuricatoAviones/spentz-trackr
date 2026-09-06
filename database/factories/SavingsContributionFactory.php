<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsContribution>
 */
class SavingsContributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 5, 500);

        return [
            'savings_goal_id' => SavingsGoal::factory(),
            'income_id' => null,
            'amount' => $amount,
            'currency' => Currency::Usd,
            'exchange_rate' => null,
            'usd_amount' => $amount,
            'usdt_amount' => $amount,
            'contributed_at' => fake()->date(),
            'note' => null,
        ];
    }
}
