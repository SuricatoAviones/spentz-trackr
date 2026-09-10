<?php

namespace App\Support\Presenters;

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\ExpenseReceipt;
use Illuminate\Support\Facades\Storage;

/**
 * Single source of truth for the JSON shape of an expense, shared by the web
 * (Inertia) and REST API controllers.
 */
final class ExpensePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'description' => $expense->description,
            'note' => $expense->note,
            'amount' => $expense->amount,
            'currency' => $expense->currency->value,
            'payment_method' => $expense->payment_method?->value,
            'commission' => $expense->commission,
            'exchange_rate' => $expense->exchange_rate,
            'rate_provider' => $expense->rate_provider,
            'usd_amount' => $expense->usd_amount,
            'usdt_amount' => $expense->usdt_amount,
            'spent_at' => $expense->spent_at->toDateString(),
            'category' => [
                'id' => $expense->category_id,
                'name' => $expense->category->name,
                'icon' => $expense->category->icon,
                'color' => $expense->category->color,
            ],
            'source' => [
                'id' => $expense->payment_source_id,
                'name' => $expense->paymentSource->name,
                'icon' => $expense->paymentSource->icon,
                'color' => $expense->paymentSource->color,
            ],
            'has_receipt' => $expense->receipts->isNotEmpty(),
            'items' => $expense->items
                ->map(fn (ExpenseItem $item): array => [
                    'id' => $item->id,
                    'currency' => $item->currency->value,
                    'amount' => $item->amount,
                    'exchange_rate' => $item->exchange_rate,
                    'usd_amount' => $item->usd_amount,
                    'usdt_amount' => $item->usdt_amount,
                ])
                ->values()
                ->all(),
            'receipts' => $expense->receipts
                ->map(fn (ExpenseReceipt $receipt): array => [
                    'id' => $receipt->id,
                    'url' => Storage::disk('public')->url($receipt->path),
                    'original_name' => $receipt->original_name,
                ])
                ->values()
                ->all(),
        ];
    }
}
