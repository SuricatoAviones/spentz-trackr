<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Expense;
use App\Models\ExpenseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseItem>
 */
class ExpenseItemFactory extends Factory
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
            'expense_id' => Expense::factory(),
            'currency' => Currency::Usd,
            'amount' => $amount,
            'exchange_rate' => null,
            'usd_amount' => $amount,
            'usdt_amount' => $amount,
        ];
    }

    /**
     * Set the item currency to bolívares with a given exchange rate.
     */
    public function ves(float $exchangeRate = 28.0000): static
    {
        return $this->state(function (array $attributes): array {
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
}
