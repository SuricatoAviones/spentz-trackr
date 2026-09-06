<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\SavingsContributionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $savings_goal_id
 * @property int|null $income_id
 * @property string $amount
 * @property Currency $currency
 * @property string|null $exchange_rate
 * @property string $usd_amount
 * @property string $usdt_amount
 * @property Carbon $contributed_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'savings_goal_id',
    'income_id',
    'amount',
    'currency',
    'exchange_rate',
    'usd_amount',
    'usdt_amount',
    'contributed_at',
    'note',
])]
class SavingsContribution extends Model
{
    /** @use HasFactory<SavingsContributionFactory> */
    use HasFactory;

    public function goal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class, 'savings_goal_id');
    }

    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'usd_amount' => 'decimal:2',
            'usdt_amount' => 'decimal:2',
            'contributed_at' => 'date',
        ];
    }
}
