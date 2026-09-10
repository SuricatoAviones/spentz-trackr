<?php

namespace App\Actions\Expenses\Concerns;

use App\Actions\Concerns\ResolvesTransactionRate;
use App\Actions\Expenses\StoreExpenseAction;
use App\Actions\Expenses\UpdateExpenseAction;
use App\Enums\Currency;
use App\Models\Expense;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\ExpenseConversionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Shared persistence logic for {@see StoreExpenseAction}
 * and {@see UpdateExpenseAction}: rate resolution,
 * Bs commission handling, mixed-currency line items, USD/USDT freezing and
 * receipt storage. Web and API controllers call the Actions, never this trait.
 */
trait PersistsExpense
{
    use ResolvesTransactionRate;

    public function __construct(
        protected readonly ExpenseConversionService $converter,
        protected readonly ExchangeRateService $rateService,
    ) {}

    /**
     * Build the column values for the expense row, including the frozen
     * USD/USDT equivalents of the primary amount plus every line item.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function expenseAttributes(User $user, array $data): array
    {
        $currency = Currency::from($data['currency']);

        $rate = $this->resolveTransactionRate(
            $user,
            $currency,
            $data['exchange_rate'] ?? null,
            $data['rate_provider'] ?? null,
        );

        $converted = $this->converter->convert(
            $currency,
            $this->totalAmount($data),
            $rate['rate'],
        );

        $itemTotals = $this->convertItems($user, $data['items'] ?? []);

        return [
            ...$data,
            'amount' => round((float) $data['amount'], 2),
            'payment_method' => $currency === Currency::Ves ? ($data['payment_method'] ?? null) : null,
            'commission' => $currency === Currency::Ves ? ($data['commission'] ?? null) : null,
            'exchange_rate' => $rate['rate'],
            'rate_provider' => $rate['provider'],
            'usd_amount' => round($converted['usd_amount'] + $itemTotals['usd'], 2),
            'usdt_amount' => round($converted['usdt_amount'] + $itemTotals['usdt'], 2),
        ];
    }

    /**
     * Real expense total driving the conversion: base amount plus the Bs
     * commission (which is stored separately on its own column).
     *
     * @param  array<string, mixed>  $data
     */
    protected function totalAmount(array $data): float
    {
        $commission = isset($data['commission']) ? (float) $data['commission'] : 0.0;

        return round((float) $data['amount'] + $commission, 2);
    }

    /**
     * Aggregate the USD/USDT equivalents of the mixed-currency line items.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{usd: float, usdt: float}
     */
    protected function convertItems(User $user, array $items): array
    {
        $usd = 0.0;
        $usdt = 0.0;

        foreach ($items as $item) {
            $itemCurrency = Currency::from($item['currency']);
            $exchangeRate = $item['exchange_rate'] ?? null;

            if ($itemCurrency === Currency::Ves && $exchangeRate === null) {
                $dayRate = $this->rateService->rateForUser($user);

                if ((float) $dayRate['rate'] <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'No hay tasa de cambio disponible para el ítem en Bs. Regístrala en Ajustes.',
                    ]);
                }

                $exchangeRate = (float) $dayRate['rate'];
            }

            if ($itemCurrency === Currency::Ves && (float) $exchangeRate <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Cada ítem en Bs debe tener una tasa de cambio mayor a 0.',
                ]);
            }

            $convertedItem = $this->converter->convert(
                $itemCurrency,
                (float) $item['amount'],
                $exchangeRate !== null ? (float) $exchangeRate : null,
            );

            $usd += $convertedItem['usd_amount'];
            $usdt += $convertedItem['usdt_amount'];
        }

        return ['usd' => $usd, 'usdt' => $usdt];
    }

    /**
     * Replace the mixed-currency line items of an expense.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function syncItems(User $user, Expense $expense, array $items): void
    {
        $expense->items()->delete();

        foreach ($items as $item) {
            $itemCurrency = Currency::from($item['currency']);
            $exchangeRate = $item['exchange_rate'] ?? null;

            if ($itemCurrency === Currency::Ves && ($exchangeRate === null || (float) $exchangeRate <= 0)) {
                $dayRate = $this->rateService->rateForUser($user);
                $exchangeRate = (float) $dayRate['rate'];
            }

            $convertedItem = $this->converter->convert(
                $itemCurrency,
                (float) $item['amount'],
                $exchangeRate !== null ? (float) $exchangeRate : null,
            );

            $expense->items()->create([
                'currency' => $itemCurrency,
                'amount' => round((float) $item['amount'], 2),
                'exchange_rate' => $itemCurrency === Currency::Ves ? $exchangeRate : null,
                'usd_amount' => $convertedItem['usd_amount'],
                'usdt_amount' => $convertedItem['usdt_amount'],
            ]);
        }
    }

    protected function storeReceipt(Expense $expense, UploadedFile $receipt): void
    {
        $expense->receipts()->create([
            'path' => $receipt->store('receipts', 'public'),
            'original_name' => $receipt->getClientOriginalName(),
        ]);
    }

    protected function deleteReceipts(Expense $expense): void
    {
        foreach ($expense->receipts as $receipt) {
            Storage::disk('public')->delete($receipt->path);
            $receipt->delete();
        }
    }
}
