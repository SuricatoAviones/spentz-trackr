<?php

namespace App\Http\Controllers;

use App\Actions\Expenses\StoreExpenseAction;
use App\Actions\Expenses\UpdateExpenseAction;
use App\Enums\CategoryType;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Support\Presenters\ExpensePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Expense::query()
            ->forUser($user->id)
            ->with(['category', 'paymentSource', 'receipts', 'items'])
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
            ->through(fn (Expense $expense) => ExpensePresenter::present($expense));

        $filters['currency'] = $request->string('currency')->toString();

        return Inertia::render('expenses/index', [
            'expenses' => $expenses,
            'filters' => $filters,
            'totals' => $this->monthTotals($request),
            'categories' => $this->selectCategories($user->id),
            'sources' => $this->selectSources($user->id),
        ]);
    }

    public function create(Request $request, ExchangeRateService $rateService): Response
    {
        $user = $request->user();

        $rateService->ensureFreshRate($user);

        return Inertia::render('expenses/create', [
            'categories' => $this->selectCategories($user->id),
            'sources' => $this->selectSources($user->id),
            'rate' => $rateService->rateForUser($user),
            'rates' => $rateService->ratesForUser($user),
            'commissionDefaults' => $this->commissionDefaults($user),
        ]);
    }

    public function store(StoreExpenseRequest $request, StoreExpenseAction $action): RedirectResponse
    {
        $expense = $action->handle(
            $request->user(),
            $request->validated(),
            $request->file('receipt'),
        );

        return redirect()
            ->route('expenses.show', $expense)
            ->with('success', __('messages.expense_created'));
    }

    public function show(Request $request, Expense $expense): Response
    {
        $this->authorize('view', $expense);

        return Inertia::render('expenses/show', [
            'expense' => $expense->load(['category', 'paymentSource', 'receipts']),
        ]);
    }

    public function edit(Request $request, Expense $expense, ExchangeRateService $rateService): Response
    {
        $this->authorize('update', $expense);

        $user = $request->user();

        $rateService->ensureFreshRate($user);

        return Inertia::render('expenses/edit', [
            'expense' => $expense->load(['receipts', 'items']),
            'categories' => $this->selectCategories($user->id),
            'sources' => $this->selectSources($user->id),
            'rate' => $rateService->rateForUser($user),
            'rates' => $rateService->ratesForUser($user),
            'commissionDefaults' => $this->commissionDefaults($user),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense, UpdateExpenseAction $action): RedirectResponse
    {
        $this->authorize('update', $expense);

        $action->handle(
            $request->user(),
            $expense,
            $request->validated(),
            $request->file('receipt'),
            $request->boolean('remove_receipt'),
        );

        return redirect()
            ->route('expenses.show', $expense)
            ->with('success', __('messages.expense_updated'));
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $this->deleteReceipts($expense);
        $expense->delete();

        return redirect()
            ->route('expenses.index')
            ->with('success', __('messages.expense_deleted'));
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

    private function deleteReceipts(Expense $expense): void
    {
        foreach ($expense->receipts as $receipt) {
            Storage::disk('public')->delete($receipt->path);
            $receipt->delete();
        }
    }

    /**
     * @return array{min_commission: string|null, commission_rate: string|null}
     */
    private function commissionDefaults(User $user): array
    {
        return [
            'min_commission' => $user->min_commission,
            'commission_rate' => $user->commission_rate,
        ];
    }
}
