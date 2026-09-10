<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    /**
     * Return the dashboard data for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = today();
        $monthStart = $today->startOfMonth()->toDateString();
        $monthEnd = $today->endOfMonth()->toDateString();

        if ($user->tracking_type === 'income') {
            return $this->apiResponse([
                'mode' => 'income',
                'month' => $today->translatedFormat('F Y'),
                'incomes' => $this->incomeData($user, $monthStart, $monthEnd),
            ]);
        }

        if ($user->tracking_type === 'expenses') {
            return $this->apiResponse([
                'mode' => 'expenses',
                'month' => $today->translatedFormat('F Y'),
                'expenses' => $this->expenseData($user, $monthStart, $monthEnd),
            ]);
        }

        return $this->apiResponse([
            'mode' => 'both',
            'month' => $today->translatedFormat('F Y'),
            'expenses' => $this->expenseData($user, $monthStart, $monthEnd),
            'incomes' => $this->incomeData($user, $monthStart, $monthEnd),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function expenseData(User $user, string $monthStart, string $monthEnd): array
    {
        $expenses = Expense::query()
            ->forUser($user->id)
            ->forPeriod($monthStart, $monthEnd)
            ->with(['category', 'paymentSource'])
            ->get();

        $byCurrency = ['usd' => 0.0, 'ves' => 0.0, 'usdt' => 0.0];
        foreach ($expenses as $expense) {
            $byCurrency[$expense->currency->value] += (float) $expense->amount;
        }

        $categories = $expenses
            ->groupBy('category_id')
            ->map(fn ($group) => [
                'id' => $group->first()->category_id,
                'name' => $group->first()->category->name,
                'color' => $group->first()->category->color,
                'icon' => $group->first()->category->icon,
                'total' => round((float) $group->sum('usd_amount'), 2),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->take(6)
            ->values();

        $sources = $expenses
            ->groupBy('payment_source_id')
            ->map(fn ($group) => [
                'id' => $group->first()->payment_source_id,
                'name' => $group->first()->paymentSource->name,
                'color' => $group->first()->paymentSource->color,
                'icon' => $group->first()->paymentSource->icon,
                'total' => round((float) $group->sum('usd_amount'), 2),
            ])
            ->sortByDesc('total')
            ->values();

        $trend = collect(range(5, 0))
            ->map(function (int $offset) use ($user) {
                $month = today()->subMonths($offset);
                $total = Expense::query()
                    ->forUser($user->id)
                    ->forPeriod($month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString())
                    ->sum('usd_amount');

                return [
                    'month' => $month->format('Y-m'),
                    'total' => round((float) $total, 2),
                ];
            });

        $budgets = Category::query()
            ->where('user_id', $user->id)
            ->whereNotNull('budget')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'amount' => (float) $category->budget,
                'spent' => round((float) $category->expenses()
                    ->forPeriod(today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString())
                    ->sum('usd_amount'), 2),
            ])
            ->sortByDesc('spent')
            ->values();

        $recent = $expenses
            ->sortByDesc('spent_at')
            ->sortByDesc('id')
            ->take(10);

        return [
            'totals' => [
                'usd' => round((float) $expenses->sum('usd_amount'), 2),
                'usdt' => round((float) $expenses->sum('usdt_amount'), 2),
                'byCurrency' => $byCurrency,
            ],
            'categories' => $categories,
            'sources' => $sources,
            'trend' => $trend,
            'budgets' => $budgets,
            'recent' => $recent
                ->map(fn (Expense $expense) => [
                    'id' => $expense->id,
                    'description' => $expense->description,
                    'amount' => $expense->amount,
                    'currency' => $expense->currency->value,
                    'usd_amount' => $expense->usd_amount,
                    'spent_at' => $expense->spent_at->toDateString(),
                    'category' => [
                        'id' => $expense->category_id,
                        'name' => $expense->category->name,
                        'icon' => $expense->category->icon,
                        'color' => $expense->category->color,
                    ],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function incomeData(User $user, string $monthStart, string $monthEnd): array
    {
        $incomes = Income::query()
            ->forUser($user->id)
            ->forPeriod($monthStart, $monthEnd)
            ->with('category')
            ->get();

        $byCurrency = ['usd' => 0.0, 'ves' => 0.0, 'usdt' => 0.0];
        foreach ($incomes as $income) {
            $byCurrency[$income->currency->value] += (float) $income->amount;
        }

        $categories = $incomes
            ->groupBy('category_id')
            ->map(fn ($group) => [
                'id' => $group->first()->category_id,
                'name' => $group->first()->category->name,
                'color' => $group->first()->category->color,
                'icon' => $group->first()->category->icon,
                'total' => round((float) $group->sum('usd_amount'), 2),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->take(6)
            ->values();

        $trend = collect(range(5, 0))
            ->map(function (int $offset) use ($user) {
                $month = today()->subMonths($offset);
                $total = Income::query()
                    ->forUser($user->id)
                    ->forPeriod($month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString())
                    ->sum('usd_amount');

                return [
                    'month' => $month->format('Y-m'),
                    'total' => round((float) $total, 2),
                ];
            });

        $recent = $incomes
            ->sortByDesc('received_at')
            ->sortByDesc('id')
            ->take(10);

        return [
            'totals' => [
                'usd' => round((float) $incomes->sum('usd_amount'), 2),
                'usdt' => round((float) $incomes->sum('usdt_amount'), 2),
                'byCurrency' => $byCurrency,
            ],
            'categories' => $categories,
            'trend' => $trend,
            'recent' => $recent
                ->map(fn (Income $income) => [
                    'id' => $income->id,
                    'description' => $income->description,
                    'amount' => $income->amount,
                    'currency' => $income->currency->value,
                    'usd_amount' => $income->usd_amount,
                    'received_at' => $income->received_at->toDateString(),
                    'category' => [
                        'id' => $income->category_id,
                        'name' => $income->category->name,
                        'icon' => $income->category->icon,
                        'color' => $income->category->color,
                    ],
                ])
                ->values()
                ->all(),
        ];
    }
}
