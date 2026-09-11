<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\IncomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property Currency $currency
 * @property string $amount
 * @property string|null $exchange_rate
 * @property string $usd_amount
 * @property string $usdt_amount
 * @property string $description
 * @property string|null $note
 * @property string|null $rate_provider
 * @property Carbon $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Category $category
 * @property-read Collection<int, IncomeReceipt> $receipts
 */
#[Fillable([
    'category_id',
    'currency',
    'amount',
    'exchange_rate',
    'rate_provider',
    'usd_amount',
    'usdt_amount',
    'description',
    'note',
    'received_at',
])]
class Income extends Model
{
    /** @use HasFactory<IncomeFactory> */
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
     * @return HasMany<IncomeReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(IncomeReceipt::class);
    }

    /**
     * @param  Builder<Income>  $query
     * @return Builder<Income>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<Income>  $query
     * @return Builder<Income>
     */
    public function scopeForPeriod(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('received_at', [$start, $end]);
    }

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'usd_amount' => 'decimal:2',
            'usdt_amount' => 'decimal:2',
            'received_at' => 'date',
        ];
    }
}
