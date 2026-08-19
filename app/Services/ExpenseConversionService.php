<?php

namespace App\Services;

use App\Enums\Currency;

class ExpenseConversionService
{
    /**
     * Calculate the frozen USD and USDT equivalents for an expense.
     *
     * USDT is treated 1:1 with USD. VES amounts are divided by the
     * exchange rate captured at the moment of the transaction.
     *
     * @return array{usd_amount: float, usdt_amount: float}
     */
    public function convert(Currency $currency, float $amount, ?float $exchangeRate = null): array
    {
        if ($currency === Currency::Ves) {
            if ($exchangeRate === null || $exchangeRate <= 0) {
                throw new \InvalidArgumentException('Una tasa de cambio mayor a 0 es obligatoria para gastos en Bs.');
            }

            $usd = round($amount / $exchangeRate, 2);

            return [
                'usd_amount' => $usd,
                'usdt_amount' => $usd,
            ];
        }

        return [
            'usd_amount' => round($amount, 2),
            'usdt_amount' => round($amount, 2),
        ];
    }
}
