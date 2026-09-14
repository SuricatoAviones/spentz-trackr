<?php

namespace App\Support\Presenters;

use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\CreditCardStatement;
use App\Services\CreditCardCycleService;

/**
 * Forma JSON única de una tarjeta. Ver la regla del presenter en
 * `.ai/rules/controllers.md`: las páginas React consumen esto, nunca el modelo.
 */
final class CreditCardPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(CreditCard $card, ?CreditCardCycleService $cycle = null): array
    {
        $cycle ??= app(CreditCardCycleService::class);
        $summary = $cycle->summary($card);

        return [
            'id' => $card->id,
            'bank' => $card->bank,
            'name' => $card->name,
            'last_four' => $card->last_four,
            'brand' => $card->brand->value,
            'currency' => $card->currency->value,
            'credit_limit' => $card->credit_limit,
            'cut_day' => $card->cut_day,
            'due_day' => $card->due_day,
            'annual_interest_rate' => $card->annual_interest_rate,
            'minimum_payment_rate' => $card->minimum_payment_rate,
            'icon' => $card->icon,
            'color' => $card->color,
            'active' => $card->active,
            'note' => $card->note,
            'payment_source_id' => $card->payment_source_id,
            'cycle' => $summary['cycle'],
            'balance' => $summary['balance'],
            'usd' => $summary['usd'],
        ];
    }

    /**
     * Ficha completa: añade el historial de cortes y abonos.
     *
     * @return array<string, mixed>
     */
    public static function detail(CreditCard $card, ?CreditCardCycleService $cycle = null): array
    {
        return [
            ...self::present($card, $cycle),
            'statements' => $card->statements
                ->sortByDesc('cut_date')
                ->map(fn (CreditCardStatement $statement): array => [
                    'id' => $statement->id,
                    'cut_date' => $statement->cut_date->toDateString(),
                    'due_date' => $statement->due_date->toDateString(),
                    'closing_balance' => $statement->closing_balance,
                    'minimum_payment' => $statement->minimum_payment,
                    'currency' => $statement->currency->value,
                    'exchange_rate' => $statement->exchange_rate,
                    'usd_amount' => $statement->usd_amount,
                    'paid_at' => $statement->paid_at?->toDateString(),
                    'note' => $statement->note,
                ])
                ->values()
                ->all(),
            'payments' => $card->payments
                ->sortByDesc('paid_at')
                ->map(fn (CreditCardPayment $payment): array => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency->value,
                    'usd_amount' => $payment->usd_amount,
                    'paid_at' => $payment->paid_at->toDateString(),
                    'statement_id' => $payment->credit_card_statement_id,
                    'note' => $payment->note,
                ])
                ->values()
                ->all(),
        ];
    }
}
