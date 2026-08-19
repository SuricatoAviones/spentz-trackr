<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expense;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ExchangeRateService $rateService): Response
    {
        $user = $request->user();
        $today = Carbon::today();

        $rateService->ensureFreshRate($user);

        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $monthlyExpenses = Expense::query()
            ->forUser($user->id)
            ->forPeriod($monthStart->toDateString(), $monthEnd->toDateString())
            ->get();

        $byCurrency = [
            'usd' => 0.0,
            'ves' => 0.0,
            'usdt' => 0.0,
        ];

        foreach ($monthlyExpenses as $expense) {
            $byCurrency[$expense->currency->value] += (float) $expense->amount;
        }

        $totalUsd = (float) $monthlyExpenses->sum('usd_amount');
        $totalUsdt = (float) $monthlyExpenses->sum('usdt_amount');

        $categories = $monthlyExpenses
            ->groupBy('category_id')
            ->map(function ($group) {
                $expense = $group->first();

                return [
                    'id' => $expense->category_id,
                    'name' => $expense->category->name,
                    'color' => $expense->category->color,
                    'icon' => $expense->category->icon,
                    'total' => round($group->sum('usd_amount'), 2),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->take(6)
            ->values();

        $sources = $monthlyExpenses
            ->groupBy('payment_source_id')
            ->map(function ($group) {
                $expense = $group->first();

                return [
                    'id' => $expense->payment_source_id,
                    'name' => $expense->paymentSource->name,
                    'color' => $expense->paymentSource->color,
                    'icon' => $expense->paymentSource->icon,
                    'total' => round($group->sum('usd_amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $trend = collect(range(5, 0))
            ->map(function (int $offset) use ($user, $today) {
                $month = $today->copy()->subMonths($offset);
                $total = Expense::query()
                    ->forUser($user->id)
                    ->forPeriod($month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString())
                    ->sum('usd_amount');

                return [
                    'month' => $month->translatedFormat('M'),
                    'total' => round((float) $total, 2),
                ];
            });

        $recentExpenses = Expense::query()
            ->forUser($user->id)
            ->with(['category', 'paymentSource', 'receipts'])
            ->orderByDesc('spent_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Expense $expense) => $this->expenseShape($expense));

        $budgets = Category::query()
            ->where('user_id', $user->id)
            ->whereNotNull('budget')
            ->withSum(['expenses as monthly_spent' => fn ($query) => $query->forPeriod($monthStart->toDateString(), $monthEnd->toDateString())], 'usd_amount')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'budget' => (float) $category->budget,
                'spent' => round((float) $category->monthly_spent, 2),
            ])
            ->sortByDesc('spent')
            ->values();

        return Inertia::render('dashboard', [
            'month' => $today->translatedFormat('F Y'),
            'totals' => [
                'usd' => round($totalUsd, 2),
                'usdt' => round($totalUsdt, 2),
                'byCurrency' => $byCurrency,
            ],
            'rate' => $rateService->rateForUser($user),
            'categories' => $categories,
            'sources' => $sources,
            'trend' => $trend,
            'budgets' => $budgets,
            'recentExpenses' => $recentExpenses,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function expenseShape(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'description' => $expense->description,
            'amount' => $expense->amount,
            'currency' => $expense->currency->value,
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
        ];
    }
}
