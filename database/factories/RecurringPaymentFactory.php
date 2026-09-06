<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\Frequency;
use App\Models\RecurringPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringPayment>
 */
class RecurringPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1, 100);

        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'name' => fake()->words(2, true),
            'amount' => $amount,
            'currency' => Currency::Usd,
            'exchange_rate' => null,
            'usd_amount' => $amount,
            'usdt_amount' => $amount,
            'frequency' => Frequency::Monthly,
            'next_due_date' => fake()->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
            'last_paid_at' => null,
            'icon' => 'wallet',
            'color' => '#10B981',
            'active' => true,
            'note' => null,
        ];
    }
}
