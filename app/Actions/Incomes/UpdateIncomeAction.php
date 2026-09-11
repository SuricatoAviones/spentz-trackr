<?php

namespace App\Actions\Incomes;

use App\Actions\Incomes\Concerns\PersistsIncome;
use App\Models\Income;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Update an income, recomputing its frozen USD/USDT equivalents and optionally
 * replacing or removing its receipt.
 */
class UpdateIncomeAction
{
    use PersistsIncome;

    /**
     * @param  array<string, mixed>  $data  validated payload (see UpdateIncomeRequest)
     */
    public function handle(User $user, Income $income, array $data, ?UploadedFile $receipt = null, bool $removeReceipt = false): Income
    {
        return DB::transaction(function () use ($user, $income, $data, $receipt, $removeReceipt): Income {
            $income->update($this->incomeAttributes($user, $data));

            if ($removeReceipt) {
                $this->deleteReceipts($income);
            }

            if ($receipt !== null) {
                $this->storeReceipt($income, $receipt);
            }

            return $income;
        });
    }
}
