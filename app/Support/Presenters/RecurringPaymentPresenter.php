<?php

namespace App\Support\Presenters;

use App\Models\RecurringPayment;

/**
 * Forma JSON única de un pago recurrente (web + API).
 */
final class RecurringPaymentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(RecurringPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'name' => $payment->name,
            'amount' => $payment->amount,
            'currency' => $payment->currency->value,
            'usd_amount' => $payment->usd_amount,
            'frequency' => $payment->frequency->value,
            'next_due_date' => $payment->next_due_date?->toDateString(),
            'last_paid_at' => $payment->last_paid_at?->toDateString(),
            'category_id' => $payment->category_id,
            'icon' => $payment->icon,
            'color' => $payment->color,
            'active' => $payment->active,
            'note' => $payment->note,
            'due' => self::isDue($payment),
        ];
    }

    /**
     * Vencido = activo, con fecha, y esa fecha no es futura.
     */
    public static function isDue(RecurringPayment $payment): bool
    {
        return $payment->active
            && $payment->next_due_date !== null
            && ! $payment->next_due_date->isAfter(today());
    }
}
