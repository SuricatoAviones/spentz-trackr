<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Incomes\StoreIncomeAction;
use App\Actions\Incomes\UpdateIncomeAction;
use App\Enums\CategoryType;
use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\UpdateIncomeRequest;
use App\Models\Category;
use App\Models\Income;
use App\Support\Presenters\IncomePresenter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IncomeController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    #[QueryParameter('search', description: 'Buscar en la descripción o nota del ingreso.', type: 'string', example: 'sueldo')]
    #[QueryParameter('currency', description: 'Filtrar por moneda del ingreso (usd, ves, usdt).', type: 'string', example: 'usd')]
    #[QueryParameter('category_id', description: 'Filtrar por id de categoría.', type: 'integer')]
    #[QueryParameter('from', description: 'Filtrar desde esta fecha (YYYY-MM-DD).', type: 'string', format: 'date', example: '2026-09-01')]
    #[QueryParameter('to', description: 'Filtrar hasta esta fecha (YYYY-MM-DD).', type: 'string', format: 'date', example: '2026-09-30')]
    #[QueryParameter('page', description: 'Número de página de resultados.', type: 'integer', default: 1)]
    public function index(Request $request): JsonResponse
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
            ->through(fn (Income $income) => IncomePresenter::present($income));

        $filters['currency'] = $request->string('currency')->toString();

        return $this->apiResponse([
            'incomes' => $incomes,
            'filters' => $filters,
            'totals' => $this->monthTotals($request),
            'categories' => $this->selectCategories($user->id),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIncomeRequest $request, StoreIncomeAction $action): JsonResponse
    {
        $income = $action->handle(
            $request->user(),
            $request->validated(),
            $request->file('receipt'),
        );

        return $this->apiCreated($income->id, __('messages.income_created'))
            ->header('Location', route('api.v1.incomes.show', $income));
    }

    /**
     * Display the specified resource.
     */
    public function show(Income $income): JsonResponse
    {
        $this->authorize('view', $income);

        return $this->apiResponse([
            'income' => IncomePresenter::present($income->load(['category', 'receipts'])),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateIncomeRequest $request, Income $income, UpdateIncomeAction $action): JsonResponse
    {
        $this->authorize('update', $income);

        $action->handle(
            $request->user(),
            $income,
            $request->validated(),
            $request->file('receipt'),
            $request->boolean('remove_receipt'),
        );

        return $this->apiResponse([
            'income' => IncomePresenter::present($income->fresh(['category', 'receipts'])),
        ], __('messages.income_updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Income $income): JsonResponse
    {
        $this->authorize('delete', $income);

        $this->deleteReceipts($income);
        $income->delete();

        return response()->json([
            'success' => true,
            'data' => ['id' => $income->id],
            'message' => __('messages.income_deleted'),
        ], 200);
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

    private function deleteReceipts(Income $income): void
    {
        foreach ($income->receipts as $receipt) {
            Storage::disk('public')->delete($receipt->path);
            $receipt->delete();
        }
    }
}
