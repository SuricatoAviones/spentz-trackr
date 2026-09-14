<?php

namespace App\Models;

use App\Enums\CardBrand;
use App\Enums\Currency;
use Database\Factories\CreditCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $payment_source_id
 * @property string $bank
 * @property string $name
 * @property string|null $last_four
 * @property CardBrand $brand
 * @property Currency $currency
 * @property string $credit_limit
 * @property int $cut_day
 * @property int $due_day
 * @property string|null $annual_interest_rate
 * @property string|null $minimum_payment_rate
 * @property string $icon
 * @property string $color
 * @property bool $active
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read PaymentSource|null $paymentSource
 * @property-read Collection<int, CreditCardStatement> $statements
 * @property-read Collection<int, CreditCardPayment> $payments
 */
#[Fillable([
    'bank',
    'name',
    'last_four',
    'brand',
    'currency',
    'credit_limit',
    'cut_day',
    'due_day',
    'annual_interest_rate',
    'minimum_payment_rate',
    'icon',
    'color',
    'active',
    'note',
])]
class CreditCard extends Model
{
    /** @use HasFactory<CreditCardFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * El origen de pago que representa a esta tarjeta en los gastos.
     *
     * @return BelongsTo<PaymentSource, $this>
     */
    public function paymentSource(): BelongsTo
    {
        return $this->belongsTo(PaymentSource::class);
    }

    /**
     * @return HasMany<CreditCardStatement, $this>
     */
    public function statements(): HasMany
    {
        return $this->hasMany(CreditCardStatement::class);
    }

    /**
     * @return HasMany<CreditCardPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CreditCardPayment::class);
    }

    /**
     * Los consumos de la tarjeta son gastos normales registrados contra su
     * origen de pago: no hay una tabla aparte ni doble registro.
     *
     * @return HasManyThrough<Expense, PaymentSource, $this>
     */
    public function charges(): HasManyThrough
    {
        return $this->hasManyThrough(
            Expense::class,
            PaymentSource::class,
            'id',                 // payment_sources.id
            'payment_source_id',  // expenses.payment_source_id
            'payment_source_id',  // credit_cards.payment_source_id
            'id',
        );
    }

    /**
     * @param  Builder<CreditCard>  $query
     * @return Builder<CreditCard>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Último corte cerrado, que es el punto de partida de la proyección.
     */
    public function latestStatement(): ?CreditCardStatement
    {
        return $this->statements()->orderByDesc('cut_date')->first();
    }

    protected function casts(): array
    {
        return [
            'brand' => CardBrand::class,
            'currency' => Currency::class,
            'credit_limit' => 'decimal:2',
            'annual_interest_rate' => 'decimal:2',
            'minimum_payment_rate' => 'decimal:2',
            'cut_day' => 'integer',
            'due_day' => 'integer',
            'active' => 'boolean',
        ];
    }
}
