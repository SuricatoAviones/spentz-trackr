<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseApiController
{
    /**
     * Display the user's categories with usage metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->withCount('expenses')
            ->withCount('incomes')
            ->withSum('expenses', 'usd_amount')
            ->withSum('incomes', 'usd_amount')
            ->withSum(['expenses as monthly_spent' => fn ($query) => $query->forPeriod($monthStart, $monthEnd)], 'usd_amount')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'type' => $category->type->value,
                'budget' => $category->budget,
                'is_system' => $category->is_system,
                'expenses_count' => $category->expenses_count,
                'incomes_count' => $category->incomes_count,
                'total_usd' => round((float) $category->expenses_sum_usd_amount, 2),
                'income_total_usd' => round((float) $category->incomes_sum_usd_amount, 2),
                'monthly_spent' => round((float) $category->monthly_spent, 2),
            ]);

        $monthlyCount = $user->expenses()
            ->forPeriod($monthStart, $monthEnd)
            ->count();

        $monthlyIncomeCount = $user->incomes()
            ->forPeriod($monthStart, $monthEnd)
            ->count();

        return $this->apiResponse([
            'categories' => $categories,
            'monthlyCount' => $monthlyCount,
            'monthlyIncomeCount' => $monthlyIncomeCount,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($validated['type'] !== 'expense') {
            $validated['budget'] = null;
        }

        $category = $request->user()->categories()->create($validated);

        return $this->apiCreated($category->id, __('messages.category_created'));
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return $this->apiResponse([
            'id' => $category->id,
        ], __('messages.category_updated'));
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json([
            'success' => true,
            'data' => ['id' => $category->id],
            'message' => __('messages.category_deleted'),
        ], 200);
    }
}
