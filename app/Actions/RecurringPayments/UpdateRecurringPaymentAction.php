<?php

namespace App\Actions\RecurringPayments;

use App\Enums\Currency;
use App\Models\RecurringPayment;
use App\Services\ExpenseConversionService;

class UpdateRecurringPaymentAction
{
    public function __construct(private readonly ExpenseConversionService $converter) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(RecurringPayment $payment, array $data): RecurringPayment
    {
        $currency = Currency::from($data['currency']);

        $converted = $this->converter->convert(
            $currency,
            (float) $data['amount'],
            $currency === Currency::Ves ? (float) $data['exchange_rate'] : null,
        );

        $payment->update([
            ...$data,
            'exchange_rate' => $currency === Currency::Ves ? $data['exchange_rate'] : null,
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
        ]);

        return $payment;
    }
}
