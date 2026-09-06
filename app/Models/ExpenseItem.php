<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\ExpenseItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $expense_id
 * @property Currency $currency
 * @property string $amount
 * @property string|null $exchange_rate
 * @property string $usd_amount
 * @property string $usdt_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'expense_id',
    'currency',
    'amount',
    'exchange_rate',
    'usd_amount',
    'usdt_amount',
])]
class ExpenseItem extends Model
{
    /** @use HasFactory<ExpenseItemFactory> */
    use HasFactory;

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'usd_amount' => 'decimal:2',
            'usdt_amount' => 'decimal:2',
        ];
    }
}
