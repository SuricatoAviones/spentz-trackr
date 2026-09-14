<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\CreditCardStatementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $credit_card_id
 * @property Carbon $cut_date
 * @property Carbon $due_date
 * @property string $closing_balance
 * @property string|null $minimum_payment
 * @property Currency $currency
 * @property string|null $exchange_rate
 * @property string $usd_amount
 * @property string $usdt_amount
 * @property Carbon|null $paid_at
 * @property string|null $note
 * @property-read CreditCard $creditCard
 */
#[Fillable([
    'cut_date',
    'due_date',
    'closing_balance',
    'minimum_payment',
    'currency',
    'exchange_rate',
    'usd_amount',
    'usdt_amount',
    'paid_at',
    'note',
])]
class CreditCardStatement extends Model
{
    /** @use HasFactory<CreditCardStatementFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CreditCard, $this>
     */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /**
     * Abonos imputados a este corte.
     *
     * @return HasMany<CreditCardPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CreditCardPayment::class, 'credit_card_statement_id');
    }

    protected function casts(): array
    {
        return [
            'cut_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
            'currency' => Currency::class,
            'closing_balance' => 'decimal:2',
            'minimum_payment' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'usd_amount' => 'decimal:2',
            'usdt_amount' => 'decimal:2',
        ];
    }
}
