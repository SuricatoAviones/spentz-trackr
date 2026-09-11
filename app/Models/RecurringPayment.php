<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\Frequency;
use Database\Factories\RecurringPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $category_id
 * @property string $name
 * @property string $amount
 * @property Currency $currency
 * @property string|null $exchange_rate
 * @property string $usd_amount
 * @property string $usdt_amount
 * @property Frequency $frequency
 * @property Carbon|null $next_due_date
 * @property Carbon|null $last_paid_at
 * @property string $icon
 * @property string $color
 * @property bool $active
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Category|null $category
 */
#[Fillable([
    'user_id',
    'category_id',
    'name',
    'amount',
    'currency',
    'exchange_rate',
    'usd_amount',
    'usdt_amount',
    'frequency',
    'next_due_date',
    'last_paid_at',
    'icon',
    'color',
    'active',
    'note',
])]
class RecurringPayment extends Model
{
    /** @use HasFactory<RecurringPaymentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<RecurringPayment>  $query
     * @return Builder<RecurringPayment>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<RecurringPayment>  $query
     * @return Builder<RecurringPayment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'frequency' => Frequency::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'usd_amount' => 'decimal:2',
            'usdt_amount' => 'decimal:2',
            'next_due_date' => 'date',
            'last_paid_at' => 'date',
            'active' => 'boolean',
        ];
    }
}
