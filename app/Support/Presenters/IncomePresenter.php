<?php

namespace App\Support\Presenters;

use App\Models\Income;
use App\Models\IncomeReceipt;
use Illuminate\Support\Facades\Storage;

/**
 * Single source of truth for the JSON shape of an income, shared by the web
 * (Inertia) and REST API controllers.
 */
final class IncomePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Income $income): array
    {
        return [
            'id' => $income->id,
            'description' => $income->description,
            'note' => $income->note,
            'amount' => $income->amount,
            'currency' => $income->currency->value,
            'exchange_rate' => $income->exchange_rate,
            'rate_provider' => $income->rate_provider,
            'usd_amount' => $income->usd_amount,
            'usdt_amount' => $income->usdt_amount,
            'received_at' => $income->received_at->toDateString(),
            'category' => [
                'id' => $income->category_id,
                'name' => $income->category->name,
                'icon' => $income->category->icon,
                'color' => $income->category->color,
            ],
            'has_receipt' => $income->receipts->isNotEmpty(),
            'receipts' => $income->receipts
                ->map(fn (IncomeReceipt $receipt): array => [
                    'id' => $receipt->id,
                    'url' => Storage::disk('public')->url($receipt->path),
                    'original_name' => $receipt->original_name,
                ])
                ->values()
                ->all(),
        ];
    }
}
