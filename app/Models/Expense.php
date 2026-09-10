<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use Database\Factories\ExpenseFactory;
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
 * @property int $payment_source_id
 * @property Currency $currency
 * @property PaymentMethod|null $payment_method
 * @property string|null $commission
 * @property string $amount
 * @property string|null $exchange_rate
 * @property string|null $rate_provider
 * @property string $usd_amount
 * @property string $usdt_amount
 * @property string $description
 * @property string|null $note
 * @property Carbon $spent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Category $category
 * @property-read PaymentSource $paymentSource
 * @property-read Collection<int, ExpenseReceipt> $receipts
 * @property-read Collection<int, ExpenseItem> $items
 */
#[Fillable([
    'category_id',
    'payment_source_id',
    'currency',
    'payment_method',
    'commission',
    'amount',
    'exchange_rate',
    'rate_provider',
    'usd_amount',
    'usdt_amount',
    'description',
    'note',
    'spent_at',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
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
     * @return BelongsTo<PaymentSource, $this>
     */
    public function paymentSource(): BelongsTo
    {
        return $this->belongsTo(PaymentSource::class);
    }

    /**
     * @return HasMany<ExpenseReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(ExpenseReceipt::class);
    }

    /**
     * Additional currency portions of a mixed expense.
     * The primary portion lives on the expense itself.
     *
     * @return HasMany<ExpenseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    /**
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopeForPeriod(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('spent_at', [$start, $end]);
    }

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'payment_method' => PaymentMethod::class,
            'commission' => 'decimal:2',
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'usd_amount' => 'decimal:2',
            'usdt_amount' => 'decimal:2',
            'spent_at' => 'date',
        ];
    }
}
