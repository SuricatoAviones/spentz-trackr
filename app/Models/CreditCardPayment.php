<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\CreditCardPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Abono a la tarjeta. NO es un gasto: ver la migración que crea la tabla.
 *
 * @property int $id
 * @property int $credit_card_id
 * @property int|null $credit_card_statement_id
 * @property string $amount
 * @property Currency $currency
 * @property string|null $exchange_rate
 * @property string $usd_amount
 * @property string $usdt_amount
 * @property Carbon $paid_at
 * @property string|null $note
 * @property-read CreditCard $creditCard
 * @property-read CreditCardStatement|null $statement
 */
#[Fillable([
    'credit_card_statement_id',
    'amount',
    'currency',
    'exchange_rate',
    'usd_amount',
    'usdt_amount',
    'paid_at',
    'note',
])]
class CreditCardPayment extends Model
{
    /** @use HasFactory<CreditCardPaymentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CreditCard, $this>
     */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /**
     * @return BelongsTo<CreditCardStatement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(CreditCardStatement::class, 'credit_card_statement_id');
    }

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'usd_amount' => 'decimal:2',
            'usdt_amount' => 'decimal:2',
        ];
    }
}
