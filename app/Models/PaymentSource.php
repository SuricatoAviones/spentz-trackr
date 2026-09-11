<?php

namespace App\Models;

use Database\Factories\PaymentSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $icon
 * @property string $color
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Expense> $expenses
 * @property-read int|null $expenses_count aggregate helper (withCount)
 * @property-read string|null $expenses_sum_usd_amount aggregate helper (withSum)
 */
#[Fillable(['name', 'icon', 'color', 'is_system'])]
class PaymentSource extends Model
{
    /** @use HasFactory<PaymentSourceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
