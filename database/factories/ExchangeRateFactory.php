<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'source' => 'api',
            'rate' => fake()->randomFloat(4, 25, 40),
            'provider' => 'dolarapi',
            'rate_date' => fake()->date(),
        ];
    }

    /**
     * Mark the rate as a manual user override.
     */
    public function manual(int $userId): static
    {
        return $this->state(fn (): array => [
            'user_id' => $userId,
            'source' => 'manual',
            'provider' => 'user',
        ]);
    }
}
