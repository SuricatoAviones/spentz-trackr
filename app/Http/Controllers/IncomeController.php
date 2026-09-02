<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\Currency;
use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\UpdateIncomeRequest;
use App\Models\Category;
use App\Models\Income;
use App\Services\ExchangeRateService;
use App\Services\ExpenseConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IncomeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Income::query()
            ->forUser($user->id)
            ->with(['category', 'receipts'])
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
            ->when($request->string('from')->toString(), function ($query, string $from) {
                $query->whereDate('received_at', '>=', $from);
            })
            ->when($request->string('to')->toString(), function ($query, string $to) {
                $query->whereDate('received_at', '<=', $to);
            });

        $filters = $request->only(['search', 'currency', 'category_id', 'from', 'to']);

        $incomes = $query
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Income $income) => $this->shape($income));

        $filters['currency'] = $request->string('currency')->toString();

        return Inertia::render('incomes/index', [
            'incomes' => $incomes,
            'filters' => $filters,
            'totals' => $this->monthTotals($request),
            'categories' => $this->selectCategories($user->id),
        ]);
    }

    public function create(Request $request, ExchangeRateService $rateService): Response
    {
        $user = $request->user();

        $rateService->ensureFreshRate($user);

        return Inertia::render('incomes/create', [
            'categories' => $this->selectCategories($user->id),
            'rate' => $rateService->rateForUser($user),
            'rates' => $rateService->ratesForUser($user),
        ]);
    }

    public function store(StoreIncomeRequest $request, ExpenseConversionService $converter, ExchangeRateService $rateService): RedirectResponse
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

        $converted = $converter->convert($currency, (float) $validated['amount'], $exchangeRate !== null ? (float) $exchangeRate : null);

        $income = $request->user()->incomes()->create([
            ...$validated,
            'exchange_rate' => $currency === Currency::Ves ? $exchangeRate : null,
            'rate_provider' => $currency === Currency::Ves ? $rateProvider : null,
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
        ]);

        if ($request->hasFile('receipt')) {
            $this->storeReceipt($income, $request);
        }

        return redirect()
            ->route('incomes.show', $income)
            ->with('success', __('messages.income_created'));
    }

    public function show(Request $request, Income $income): Response
    {
        $this->authorize('view', $income);

        return Inertia::render('incomes/show', [
            'income' => $this->shape($income->load(['category', 'receipts'])),
        ]);
    }

    public function edit(Request $request, Income $income, ExchangeRateService $rateService): Response
    {
        $this->authorize('update', $income);

        $user = $request->user();

        $rateService->ensureFreshRate($user);

        return Inertia::render('incomes/edit', [
            'income' => $income->load('receipts'),
            'categories' => $this->selectCategories($user->id),
            'rate' => $rateService->rateForUser($user),
            'rates' => $rateService->ratesForUser($user),
        ]);
    }

    public function update(UpdateIncomeRequest $request, Income $income, ExpenseConversionService $converter, ExchangeRateService $rateService): RedirectResponse
    {
        $this->authorize('update', $income);

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

        $converted = $converter->convert($currency, (float) $validated['amount'], $exchangeRate !== null ? (float) $exchangeRate : null);

        $income->update([
            ...$validated,
            'exchange_rate' => $currency === Currency::Ves ? $exchangeRate : null,
            'rate_provider' => $currency === Currency::Ves ? $rateProvider : null,
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
        ]);

        if ($request->boolean('remove_receipt')) {
            $this->deleteReceipts($income);
        }

        if ($request->hasFile('receipt')) {
            $this->storeReceipt($income, $request);
        }

        return redirect()
            ->route('incomes.show', $income)
            ->with('success', __('messages.income_updated'));
    }

    public function destroy(Request $request, Income $income): RedirectResponse
    {
        $this->authorize('delete', $income);

        $this->deleteReceipts($income);
        $income->delete();

        return redirect()
            ->route('incomes.index')
            ->with('success', __('messages.income_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Income $income): array
    {
        return [
            'id' => $income->id,
            'description' => $income->description,
            'note' => $income->note,
            'amount' => $income->amount,
            'currency' => $income->currency->value,
            'exchange_rate' => $income->exchange_rate,
            'rate_provider' => $income->rate_provider,
            'usd_amount' => $income->usd_amount,
            'usdt_amount' => $income->usdt_amount,
            'received_at' => $income->received_at->toDateString(),
            'category' => [
                'id' => $income->category_id,
                'name' => $income->category->name,
                'icon' => $income->category->icon,
                'color' => $income->category->color,
            ],
            'has_receipt' => $income->receipts->isNotEmpty(),
            'receipts' => $income->receipts
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

        $monthly = Income::query()
            ->forUser($user->id)
            ->forPeriod(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
            ->get();

        $byCurrency = ['usd' => 0.0, 'ves' => 0.0, 'usdt' => 0.0];
        foreach ($monthly as $income) {
            $byCurrency[$income->currency->value] += (float) $income->amount;
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
            ->forType(CategoryType::Income)
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

    private function storeReceipt(Income $income, Request $request): void
    {
        $path = $request->file('receipt')->store('receipts', 'public');

        $income->receipts()->create([
            'path' => $path,
            'original_name' => $request->file('receipt')->getClientOriginalName(),
        ]);
    }

    private function deleteReceipts(Income $income): void
    {
        foreach ($income->receipts as $receipt) {
            Storage::disk('public')->delete($receipt->path);
            $receipt->delete();
        }
    }
}
