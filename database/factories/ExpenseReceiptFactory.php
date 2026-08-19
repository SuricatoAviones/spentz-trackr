<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseReceipt>
 */
class ExpenseReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'path' => 'receipts/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
        ];
    }
}
