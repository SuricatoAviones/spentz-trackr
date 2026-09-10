<?php

namespace App\Actions\Incomes;

use App\Actions\Incomes\Concerns\PersistsIncome;
use App\Models\Income;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Create an income for a user, freezing its USD/USDT equivalents and storing
 * an optional receipt.
 */
class StoreIncomeAction
{
    use PersistsIncome;

    /**
     * @param  array<string, mixed>  $data  validated payload (see StoreIncomeRequest)
     */
    public function handle(User $user, array $data, ?UploadedFile $receipt = null): Income
    {
        return DB::transaction(function () use ($user, $data, $receipt): Income {
            $income = $user->incomes()->create($this->incomeAttributes($user, $data));

            if ($receipt !== null) {
                $this->storeReceipt($income, $receipt);
            }

            return $income;
        });
    }
}
