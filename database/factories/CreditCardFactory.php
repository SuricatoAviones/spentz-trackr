<?php

namespace Database\Factories;

use App\Enums\CardBrand;
use App\Enums\Currency;
use App\Models\CreditCard;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditCard>
 */
class CreditCardFactory extends Factory
{
    protected $model = CreditCard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'payment_source_id' => PaymentSource::factory(),
            'bank' => fake()->randomElement(['Banesco', 'Mercantil', 'BNC', 'Provincial']),
            'name' => 'Visa Clásica',
            'last_four' => (string) fake()->numberBetween(1000, 9999),
            'brand' => CardBrand::Visa,
            'currency' => Currency::Ves,
            'credit_limit' => 50000,
            'cut_day' => 15,
            'due_day' => 5,
            'annual_interest_rate' => 60,
            'minimum_payment_rate' => 5,
            'icon' => 'credit-card',
            'color' => '#8B5CF6',
            'active' => true,
        ];
    }

    public function usd(): static
    {
        return $this->state(fn () => ['currency' => Currency::Usd, 'credit_limit' => 1500]);
    }
}
