<?php

namespace App\Actions\RecurringPayments;

use App\Models\RecurringPayment;
use Illuminate\Support\Facades\Date;

/**
 * Marca el recurrente como pagado hoy y adelanta el vencimiento un periodo.
 *
 * No crea ningún `Expense`: un recurrente es un recordatorio de lo que toca
 * pagar, no el gasto en sí — quien quiera registrarlo crea el gasto aparte.
 */
class MarkRecurringPaymentPaidAction
{
    public function handle(RecurringPayment $payment): RecurringPayment
    {
        $today = Date::today();

        $payment->update([
            'last_paid_at' => $today->toDateString(),
            'next_due_date' => $payment->frequency->advance($today)->format('Y-m-d'),
        ]);

        return $payment;
    }
}
