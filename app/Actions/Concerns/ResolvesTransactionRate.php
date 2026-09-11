<?php

namespace App\Actions\Concerns;

use App\Enums\Currency;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the exchange rate frozen onto a transaction: the value submitted by
 * the client wins; otherwise the user's rate of the day is used. Non-VES
 * transactions never carry a rate.
 */
trait ResolvesTransactionRate
{
    /**
     * @param  string  $errorKey  validation key to attach the "no rate" error to
     * @return array{rate: float|null, provider: string|null}
     */
    protected function resolveTransactionRate(
        User $user,
        Currency $currency,
        int|float|string|null $submittedRate,
        ?string $submittedProvider,
        string $errorKey = 'exchange_rate',
    ): array {
        if ($currency !== Currency::Ves) {
            return ['rate' => null, 'provider' => null];
        }

        $rate = $submittedRate !== null ? (float) $submittedRate : null;
        $provider = $submittedProvider;

        if ($rate === null) {
            $dayRate = app(ExchangeRateService::class)->rateForUser($user);
            $rate = (float) $dayRate['rate'];
            $provider = $dayRate['provider'] !== 'none' ? $dayRate['provider'] : null;
        }

        if ($rate <= 0) {
            throw ValidationException::withMessages([
                $errorKey => __('validation.app.no_rate_available'),
            ]);
        }

        return ['rate' => $rate, 'provider' => $provider];
    }
}
