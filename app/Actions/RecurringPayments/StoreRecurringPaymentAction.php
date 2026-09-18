<?php

namespace App\Actions\RecurringPayments;

use App\Enums\Currency;
use App\Models\RecurringPayment;
use App\Models\User;
use App\Services\ExpenseConversionService;

/**
 * Crea un pago recurrente congelando su equivalente USD/USDT (ADR-001).
 * Compartida por la web y la API.
 */
class StoreRecurringPaymentAction
{
    public function __construct(private readonly ExpenseConversionService $converter) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): RecurringPayment
    {
        $currency = Currency::from($data['currency']);

        $converted = $this->converter->convert(
            $currency,
            (float) $data['amount'],
            $currency === Currency::Ves ? (float) $data['exchange_rate'] : null,
        );

        return $user->recurringPayments()->create([
            ...$data,
            'exchange_rate' => $currency === Currency::Ves ? $data['exchange_rate'] : null,
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
            'last_paid_at' => null,
        ]);
    }
}
