<?php

namespace Database\Factories;

use App\Models\Income;
use App\Models\IncomeReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeReceipt>
 */
class IncomeReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'income_id' => Income::factory(),
            'path' => 'receipts/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
        ];
    }
}
