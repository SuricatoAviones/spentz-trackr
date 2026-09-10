<?php

namespace App\Actions\Incomes\Concerns;

use App\Actions\Concerns\ResolvesTransactionRate;
use App\Enums\Currency;
use App\Models\Income;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\ExpenseConversionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Shared persistence logic for the income Store/Update actions: rate
 * resolution, USD/USDT freezing and receipt storage. Incomes have no
 * commission or line items. Web and API controllers call the Actions.
 */
trait PersistsIncome
{
    use ResolvesTransactionRate;

    public function __construct(
        protected readonly ExpenseConversionService $converter,
        protected readonly ExchangeRateService $rateService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function incomeAttributes(User $user, array $data): array
    {
        $currency = Currency::from($data['currency']);

        $rate = $this->resolveTransactionRate(
            $user,
            $currency,
            $data['exchange_rate'] ?? null,
            $data['rate_provider'] ?? null,
        );

        $converted = $this->converter->convert($currency, (float) $data['amount'], $rate['rate']);

        return [
            ...$data,
            'exchange_rate' => $rate['rate'],
            'rate_provider' => $rate['provider'],
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
        ];
    }

    protected function storeReceipt(Income $income, UploadedFile $receipt): void
    {
        $income->receipts()->create([
            'path' => $receipt->store('receipts', 'public'),
            'original_name' => $receipt->getClientOriginalName(),
        ]);
    }

    protected function deleteReceipts(Income $income): void
    {
        foreach ($income->receipts as $receipt) {
            Storage::disk('public')->delete($receipt->path);
            $receipt->delete();
        }
    }
}
