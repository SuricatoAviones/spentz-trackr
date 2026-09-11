<?php

namespace App\Models;

use App\Enums\CategoryType;
use Database\Factories\CategoryFactory;
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
 * @property string $icon
 * @property string $color
 * @property CategoryType $type
 * @property string|null $budget
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Expense> $expenses
 * @property-read Collection<int, Income> $incomes
 * @property-read int|null $expenses_count aggregate helper (withCount)
 * @property-read int|null $incomes_count aggregate helper (withCount)
 * @property-read string|null $expenses_sum_usd_amount aggregate helper (withSum)
 * @property-read string|null $incomes_sum_usd_amount aggregate helper (withSum)
 * @property-read string|null $monthly_spent aggregate helper (withSum alias)
 */
#[Fillable(['name', 'icon', 'color', 'type', 'budget', 'is_system'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
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

    /**
     * @return HasMany<Income, $this>
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeForType(Builder $query, CategoryType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'is_system' => 'boolean',
            'budget' => 'decimal:2',
        ];
    }
}
