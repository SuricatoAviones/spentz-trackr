<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CategoryType;
use App\Enums\Currency;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Services\ExchangeRateService;
use App\Services\ExpenseConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ExpenseController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Expense::query()
            ->forUser($user->id)
            ->with(['category', 'paymentSource', 'receipts'])
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%");
                });
            })
            ->when($request->string('currency')->toString(), function ($query, string $currency) {
                $query->where('currency', $currency);
            })
            ->when($request->integer('category_id'), function ($query, int $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->integer('payment_source_id'), function ($query, int $sourceId) {
                $query->where('payment_source_id', $sourceId);
            })
            ->when($request->string('from')->toString(), function ($query, string $from) {
                $query->whereDate('spent_at', '>=', $from);
            })
            ->when($request->string('to')->toString(), function ($query, string $to) {
                $query->whereDate('spent_at', '<=', $to);
            });

        $filters = $request->only(['search', 'currency', 'category_id', 'payment_source_id', 'from', 'to']);

        $expenses = $query
            ->orderByDesc('spent_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Expense $expense) => $this->shape($expense));

        $filters['currency'] = $request->string('currency')->toString();

        return $this->apiResponse([
            'expenses' => $expenses,
            'filters' => $filters,
            'totals' => $this->monthTotals($request),
            'categories' => $this->selectCategories($user->id),
            'sources' => $this->selectSources($user->id),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseRequest $request, ExpenseConversionService $converter, ExchangeRateService $rateService): JsonResponse
    {
        $validated = $request->validated();

        $currency = Currency::from($validated['currency']);

        $exchangeRate = $validated['exchange_rate'] ?? null;
        $rateProvider = $validated['rate_provider'] ?? null;
        if ($currency === Currency::Ves && $exchangeRate === null) {
            $rate = $rateService->rateForUser($request->user());
            $exchangeRate = (float) $rate['rate'];
            $rateProvider = $rate['provider'] !== 'none' ? $rate['provider'] : null;
        }

        if ($currency === Currency::Ves && (float) $exchangeRate <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'No hay tasa de cambio disponible. Regístrala en Ajustes.',
            ]);
        }

        $converted = $converter->convert($currency, $this->totalAmount($validated), $exchangeRate !== null ? (float) $exchangeRate : null);

        $expense = $request->user()->expenses()->create([
            ...$validated,
            'amount' => $this->baseAmount($validated),
            'payment_method' => $this->paymentMethod($currency, $validated),
            'commission' => $this->commission($currency, $validated),
            'exchange_rate' => $currency === Currency::Ves ? $exchangeRate : null,
            'rate_provider' => $currency === Currency::Ves ? $rateProvider : null,
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
        ]);

        if ($request->hasFile('receipt')) {
            $this->storeReceipt($expense, $request);
        }

        return $this->apiCreated(
            $expense->id,
            __('messages.expense_created')
        )->header('Location', route('api.v1.expenses.show', $expense));
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        return $this->apiResponse([
            'expense' => $this->shape($expense->load('receipts')),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense, ExpenseConversionService $converter, ExchangeRateService $rateService): JsonResponse
    {
        $this->authorize('update', $expense);

        $validated = $request->validated();

        $currency = Currency::from($validated['currency']);

        $exchangeRate = $validated['exchange_rate'] ?? null;
        $rateProvider = $validated['rate_provider'] ?? null;
        if ($currency === Currency::Ves && $exchangeRate === null) {
            $rate = $rateService->rateForUser($request->user());
            $exchangeRate = (float) $rate['rate'];
            $rateProvider = $rate['provider'] !== 'none' ? $rate['provider'] : null;
        }

        if ($currency === Currency::Ves && (float) $exchangeRate <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'No hay tasa de cambio disponible. Regístrala en Ajustes.',
            ]);
        }

        $converted = $converter->convert($currency, $this->totalAmount($validated), $exchangeRate !== null ? (float) $exchangeRate : null);

        $expense->update([
            ...$validated,
            'amount' => $this->baseAmount($validated),
            'payment_method' => $this->paymentMethod($currency, $validated),
            'commission' => $this->commission($currency, $validated),
            'exchange_rate' => $currency === Currency::Ves ? $exchangeRate : null,
            'rate_provider' => $currency === Currency::Ves ? $rateProvider : null,
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
        ]);

        if ($request->boolean('remove_receipt')) {
            $this->deleteReceipts($expense);
        }

        if ($request->hasFile('receipt')) {
            $this->storeReceipt($expense, $request);
        }

        return $this->apiResponse([
            'expense' => $this->shape($expense->load('receipts')),
        ], __('messages.expense_updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        $this->deleteReceipts($expense);
        $expense->delete();

        return response()->json([
            'success' => true,
            'data' => ['id' => $expense->id],
            'message' => __('messages.expense_deleted'),
        ], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'description' => $expense->description,
            'note' => $expense->note,
            'amount' => $expense->amount,
            'currency' => $expense->currency->value,
            'payment_method' => $expense->payment_method?->value,
            'commission' => $expense->commission,
            'exchange_rate' => $expense->exchange_rate,
            'rate_provider' => $expense->rate_provider,
            'usd_amount' => $expense->usd_amount,
            'usdt_amount' => $expense->usdt_amount,
            'spent_at' => $expense->spent_at->toDateString(),
            'category' => [
                'id' => $expense->category_id,
                'name' => $expense->category->name,
                'icon' => $expense->category->icon,
                'color' => $expense->category->color,
            ],
            'source' => [
                'id' => $expense->payment_source_id,
                'name' => $expense->paymentSource->name,
                'icon' => $expense->paymentSource->icon,
                'color' => $expense->paymentSource->color,
            ],
            'has_receipt' => $expense->receipts->isNotEmpty(),
            'receipts' => $expense->receipts
                ->map(fn ($receipt) => [
                    'id' => $receipt->id,
                    'url' => Storage::disk('public')->url($receipt->path),
                    'original_name' => $receipt->original_name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, float|array<string, float>>
     */
    private function monthTotals(Request $request): array
    {
        $user = $request->user();

        $monthly = Expense::query()
            ->forUser($user->id)
            ->forPeriod(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
            ->get();

        $byCurrency = ['usd' => 0.0, 'ves' => 0.0, 'usdt' => 0.0];
        foreach ($monthly as $expense) {
            $byCurrency[$expense->currency->value] += (float) $expense->amount;
        }

        return [
            'usd' => round((float) $monthly->sum('usd_amount'), 2),
            'usdt' => round((float) $monthly->sum('usdt_amount'), 2),
            'byCurrency' => $byCurrency,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, icon: string, color: string}>
     */
    private function selectCategories(int $userId): array
    {
        return Category::query()
            ->forType(CategoryType::Expense)
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'color'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, icon: string, color: string}>
     */
    private function selectSources(int $userId): array
    {
        return PaymentSource::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'color'])
            ->map(fn (PaymentSource $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'icon' => $source->icon,
                'color' => $source->color,
            ])
            ->all();
    }

    private function storeReceipt(Expense $expense, Request $request): void
    {
        $path = $request->file('receipt')->store('receipts', 'public');

        $expense->receipts()->create([
            'path' => $path,
            'original_name' => $request->file('receipt')->getClientOriginalName(),
        ]);
    }

    private function deleteReceipts(Expense $expense): void
    {
        foreach ($expense->receipts as $receipt) {
            Storage::disk('public')->delete($receipt->path);
            $receipt->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function baseAmount(array $validated): float
    {
        return round((float) $validated['amount'], 2);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function totalAmount(array $validated): float
    {
        $base = (float) $validated['amount'];
        $commission = isset($validated['commission']) ? (float) $validated['commission'] : 0.0;

        return round($base + $commission, 2);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function commission(Currency $currency, array $validated): ?string
    {
        if ($currency !== Currency::Ves) {
            return null;
        }

        return $validated['commission'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function paymentMethod(Currency $currency, array $validated): ?string
    {
        if ($currency !== Currency::Ves) {
            return null;
        }

        return $validated['payment_method'] ?? null;
    }
}
