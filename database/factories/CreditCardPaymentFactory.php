<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditCardPayment>
 */
class CreditCardPaymentFactory extends Factory
{
    protected $model = CreditCardPayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = 2000;

        return [
            'credit_card_id' => CreditCard::factory(),
            'credit_card_statement_id' => null,
            'amount' => $amount,
            'currency' => Currency::Ves,
            'exchange_rate' => 30,
            'usd_amount' => round($amount / 30, 2),
            'usdt_amount' => round($amount / 30, 2),
            'paid_at' => now()->toDateString(),
        ];
    }
}
