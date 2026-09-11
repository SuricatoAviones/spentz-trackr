<?php

namespace App\Models;

use Database\Factories\IncomeReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $income_id
 * @property string $path
 * @property string $original_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['path', 'original_name'])]
class IncomeReceipt extends Model
{
    /** @use HasFactory<IncomeReceiptFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Income, $this>
     */
    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }
}
