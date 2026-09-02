<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
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
            ->forPeriod(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
            ->count();

        $monthlyIncomeCount = $user->incomes()
            ->forPeriod(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
            ->count();

        return Inertia::render('categories/index', [
            'categories' => $categories,
            'monthlyCount' => $monthlyCount,
            'monthlyIncomeCount' => $monthlyIncomeCount,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['type'] !== 'expense') {
            $validated['budget'] = null;
        }

        $request->user()->categories()->create($validated);

        return redirect()
            ->route('categories.index')
            ->with('success', __('messages.category_created'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return redirect()
            ->route('categories.index')
            ->with('success', __('messages.category_updated'));
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', __('messages.category_deleted'));
    }
}
