<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Expenses\StoreExpenseAction;
use App\Actions\Expenses\UpdateExpenseAction;
use App\Enums\CategoryType;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Support\Presenters\ExpensePresenter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    #[QueryParameter('search', description: 'Buscar en la descripción o nota del gasto.', type: 'string', example: 'mercado')]
    #[QueryParameter('currency', description: 'Filtrar por moneda del gasto (usd, ves, usdt).', type: 'string', example: 'usd')]
    #[QueryParameter('category_id', description: 'Filtrar por id de categoría.', type: 'integer')]
    #[QueryParameter('payment_source_id', description: 'Filtrar por id de origen de pago.', type: 'integer')]
    #[QueryParameter('from', description: 'Filtrar desde esta fecha (YYYY-MM-DD).', type: 'string', format: 'date', example: '2026-09-01')]
    #[QueryParameter('to', description: 'Filtrar hasta esta fecha (YYYY-MM-DD).', type: 'string', format: 'date', example: '2026-09-30')]
    #[QueryParameter('page', description: 'Número de página de resultados.', type: 'integer', default: 1)]
    public function index(Request $request): JsonResponse
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
    public function store(StoreExpenseRequest $request, StoreExpenseAction $action): JsonResponse
    {
        $expense = $action->handle(
            $request->user(),
            $request->validated(),
            $request->file('receipt'),
        );

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
            'expense' => ExpensePresenter::present($expense->load(['category', 'paymentSource', 'receipts', 'items'])),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense, UpdateExpenseAction $action): JsonResponse
    {
        $this->authorize('update', $expense);

        $action->handle(
            $request->user(),
            $expense,
            $request->validated(),
            $request->file('receipt'),
            $request->boolean('remove_receipt'),
        );

        return $this->apiResponse([
            'expense' => ExpensePresenter::present($expense->fresh(['category', 'paymentSource', 'receipts', 'items'])),
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
}
