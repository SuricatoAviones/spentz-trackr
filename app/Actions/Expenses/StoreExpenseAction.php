<?php

namespace App\Actions\Expenses;

use App\Actions\Expenses\Concerns\PersistsExpense;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Create an expense for a user, freezing its USD/USDT equivalents (including
 * any mixed-currency line items) and storing an optional receipt.
 */
class StoreExpenseAction
{
    use PersistsExpense;

    /**
     * @param  array<string, mixed>  $data  validated payload (see StoreExpenseRequest)
     */
    public function handle(User $user, array $data, ?UploadedFile $receipt = null): Expense
    {
        return DB::transaction(function () use ($user, $data, $receipt): Expense {
            $expense = $user->expenses()->create($this->expenseAttributes($user, $data));

            $this->syncItems($user, $expense, $data['items'] ?? []);

            if ($receipt !== null) {
                $this->storeReceipt($expense, $receipt);
            }

            return $expense;
        });
    }
}
