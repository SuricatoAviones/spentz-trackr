<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Category;
use App\Models\Income;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Income>
 */
class IncomeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1, 500);

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory()->income(),
            'currency' => Currency::Usd,
            'amount' => $amount,
            'exchange_rate' => null,
            'usd_amount' => $amount,
            'usdt_amount' => $amount,
            'description' => fake()->sentence(3),
            'note' => fake()->optional()->sentence(6),
            'received_at' => fake()->date(),
        ];
    }

    /**
     * Set the income currency to bolívares with a given exchange rate.
     */
    public function ves(float $exchangeRate = 28.0000): static
    {
        return $this->state(function (array $attributes) use ($exchangeRate): array {
            $amount = $attributes['amount'] ?? fake()->randomFloat(2, 100, 5000);

            return [
                'currency' => Currency::Ves,
                'amount' => $amount,
                'exchange_rate' => $exchangeRate,
                'usd_amount' => round($amount / $exchangeRate, 2),
                'usdt_amount' => round($amount / $exchangeRate, 2),
            ];
        });
    }

    /**
     * Set the income currency to USDT (1:1 with USD).
     */
    public function usdt(): static
    {
        return $this->state(function (array $attributes): array {
            $amount = $attributes['amount'] ?? fake()->randomFloat(2, 1, 500);

            return [
                'currency' => Currency::Usdt,
                'amount' => $amount,
                'exchange_rate' => null,
                'usd_amount' => $amount,
                'usdt_amount' => $amount,
            ];
        });
    }

    /**
     * Set the income date to a specific day.
     */
    public function on(string $date): static
    {
        return $this->state(fn (): array => ['received_at' => $date]);
    }
}
