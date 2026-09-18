<?php

namespace App\Actions\SavingsGoals;

use App\Actions\SavingsGoals\Concerns\RefreshesAchievement;
use App\Enums\Currency;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Services\ExpenseConversionService;

/**
 * Registra un aporte a la meta, congelando su equivalente en USD/USDT con la
 * tasa del momento (ADR-001).
 */
class StoreContributionAction
{
    use RefreshesAchievement;

    public function __construct(private readonly ExpenseConversionService $converter) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(SavingsGoal $goal, array $data): SavingsContribution
    {
        $currency = Currency::from($data['currency']);
        $exchangeRate = $data['exchange_rate'] ?? null;

        $converted = $this->converter->convert(
            $currency,
            (float) $data['amount'],
            $currency === Currency::Ves ? (float) $exchangeRate : null,
        );

        $contribution = $goal->contributions()->create([
            ...$data,
            'exchange_rate' => $currency === Currency::Ves ? $exchangeRate : null,
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
        ]);

        $this->refreshAchievement($goal);

        return $contribution;
    }
}
