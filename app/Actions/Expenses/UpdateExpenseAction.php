<?php

namespace App\Actions\Expenses;

use App\Actions\Expenses\Concerns\PersistsExpense;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Update an expense, recomputing its frozen USD/USDT equivalents and line
 * items, and optionally replacing or removing its receipt.
 */
class UpdateExpenseAction
{
    use PersistsExpense;

    /**
     * @param  array<string, mixed>  $data  validated payload (see UpdateExpenseRequest)
     */
    public function handle(User $user, Expense $expense, array $data, ?UploadedFile $receipt = null, bool $removeReceipt = false): Expense
    {
        return DB::transaction(function () use ($user, $expense, $data, $receipt, $removeReceipt): Expense {
            $expense->update($this->expenseAttributes($user, $data));

            $this->syncItems($user, $expense, $data['items'] ?? []);

            if ($removeReceipt) {
                $this->deleteReceipts($expense);
            }

            if ($receipt !== null) {
                $this->storeReceipt($expense, $receipt);
            }

            return $expense;
        });
    }
}
