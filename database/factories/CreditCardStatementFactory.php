<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\CreditCard;
use App\Models\CreditCardStatement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditCardStatement>
 */
class CreditCardStatementFactory extends Factory
{
    protected $model = CreditCardStatement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $balance = 10000;

        return [
            'credit_card_id' => CreditCard::factory(),
            'cut_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'closing_balance' => $balance,
            'minimum_payment' => round($balance * 0.05, 2),
            'currency' => Currency::Ves,
            'exchange_rate' => 30,
            'usd_amount' => round($balance / 30, 2),
            'usdt_amount' => round($balance / 30, 2),
        ];
    }
}
