<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\SavingsGoalFactory;
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
 * @property string $name
 * @property string $target_amount
 * @property Currency $currency
 * @property string|null $exchange_rate
 * @property string $target_usd_amount
 * @property string $icon
 * @property string $color
 * @property Carbon|null $deadline
 * @property string|null $note
 * @property Carbon|null $achieved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, SavingsContribution> $contributions
 * @property-read string|null $saved_usd aggregate helper (withSum alias)
 */
#[Fillable([
    'user_id',
    'name',
    'target_amount',
    'currency',
    'exchange_rate',
    'target_usd_amount',
    'icon',
    'color',
    'deadline',
    'note',
    'achieved_at',
])]
class SavingsGoal extends Model
{
    /** @use HasFactory<SavingsGoalFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SavingsContribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(SavingsContribution::class);
    }

    /**
     * @param  Builder<SavingsGoal>  $query
     * @return Builder<SavingsGoal>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'target_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'target_usd_amount' => 'decimal:2',
            'deadline' => 'date',
            'achieved_at' => 'datetime',
        ];
    }
}
