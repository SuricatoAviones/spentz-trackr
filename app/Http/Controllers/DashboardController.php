<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
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

        $trackingType = $user->tracking_type;
        $showExpenses = $trackingType !== 'income';
        $showIncomes = $trackingType !== 'expenses';

        $data = [
            'month' => $today->translatedFormat('F Y'),
            'trackingType' => $trackingType,
            'rate' => $rateService->rateForUser($user),
            'monthlyBudget' => $user->monthly_budget !== null
                ? (float) $user->monthly_budget
                : null,
        ];

        if ($showExpenses) {
            $data += $this->expenseDashboard($user, $monthStart, $monthEnd);
        }

        if ($showIncomes) {
            $data += $this->incomeDashboard($user, $monthStart, $monthEnd);
        }

        if ($showExpenses && $showIncomes) {
            $data += $this->netDashboard($data);
            $data['recent'] = $this->blendedRecent($user);
        }

        return Inertia::render('dashboard', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function expenseDashboard(User $user, Carbon $monthStart, Carbon $monthEnd): array
    {
        $monthlyExpenses = Expense::query()
            ->forUser($user->id)
            ->forPeriod($monthStart->toDateString(), $monthEnd->toDateString())
            ->with('category')
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

        $trend = $this->buildTrend($user, 'expense');

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
            ->where('type', 'expense')
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

        return [
            'totals' => [
                'usd' => round($totalUsd, 2),
                'usdt' => round($totalUsdt, 2),
                'byCurrency' => $byCurrency,
            ],
            'categories' => $categories,
            'sources' => $sources,
            'trend' => $trend,
            'budgets' => $budgets,
            'recentExpenses' => $recentExpenses,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function incomeDashboard(User $user, Carbon $monthStart, Carbon $monthEnd): array
    {
        $monthlyIncomes = Income::query()
            ->forUser($user->id)
            ->forPeriod($monthStart->toDateString(), $monthEnd->toDateString())
            ->with('category')
            ->get();

        $byCurrency = [
            'usd' => 0.0,
            'ves' => 0.0,
            'usdt' => 0.0,
        ];

        foreach ($monthlyIncomes as $income) {
            $byCurrency[$income->currency->value] += (float) $income->amount;
        }

        $totalUsd = (float) $monthlyIncomes->sum('usd_amount');
        $totalUsdt = (float) $monthlyIncomes->sum('usdt_amount');

        $incomeCategories = $monthlyIncomes
            ->groupBy('category_id')
            ->map(function ($group) {
                $income = $group->first();

                return [
                    'id' => $income->category_id,
                    'name' => $income->category->name,
                    'color' => $income->category->color,
                    'icon' => $income->category->icon,
                    'total' => round($group->sum('usd_amount'), 2),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->take(6)
            ->values();

        $incomeTrend = $this->buildTrend($user, 'income');

        $recentIncomes = Income::query()
            ->forUser($user->id)
            ->with(['category', 'receipts'])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Income $income) => $this->incomeShape($income));

        return [
            'incomeTotals' => [
                'usd' => round($totalUsd, 2),
                'usdt' => round($totalUsdt, 2),
                'byCurrency' => $byCurrency,
            ],
            'incomeCategories' => $incomeCategories,
            'incomeTrend' => $incomeTrend,
            'recentIncomes' => $recentIncomes,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{net: array{usd: float, usdt: float}}
     */
    private function netDashboard(array $data): array
    {
        $expenseUsd = $data['totals']['usd'] ?? 0;
        $expenseUsdt = $data['totals']['usdt'] ?? 0;
        $incomeUsd = $data['incomeTotals']['usd'] ?? 0;
        $incomeUsdt = $data['incomeTotals']['usdt'] ?? 0;

        return [
            'net' => [
                'usd' => round($incomeUsd - $expenseUsd, 2),
                'usdt' => round($incomeUsdt - $expenseUsdt, 2),
            ],
        ];
    }

    /**
     * @return array<int, array{kind: string, date: string, expense?: array<string, mixed>, income?: array<string, mixed>}>
     */
    private function blendedRecent(User $user): array
    {
        $recentExpenses = Expense::query()
            ->forUser($user->id)
            ->with(['category', 'paymentSource'])
            ->orderByDesc('spent_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Expense $e) => [
                'kind' => 'expense',
                'date' => $e->spent_at->toDateString(),
                'expense' => $this->expenseShape($e),
            ]);

        $recentIncomes = Income::query()
            ->forUser($user->id)
            ->with(['category'])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Income $i) => [
                'kind' => 'income',
                'date' => $i->received_at->toDateString(),
                'income' => $this->incomeShape($i),
            ]);

        return $recentExpenses
            ->concat($recentIncomes)
            ->sortByDesc('date')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{month: string, total: float}>
     */
    private function buildTrend(User $user, string $type): array
    {
        $today = Carbon::today();
        $model = $type === 'income' ? Income::class : Expense::class;
        $dateColumn = $type === 'income' ? 'received_at' : 'spent_at';

        return collect(range(5, 0))
            ->map(function (int $offset) use ($user, $today, $model) {
                $month = $today->copy()->subMonths($offset);
                $total = $model::query()
                    ->forUser($user->id)
                    ->forPeriod($month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString())
                    ->sum('usd_amount');

                return [
                    'month' => $month->format('Y-m'),
                    'total' => round((float) $total, 2),
                ];
            })
            ->all();
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

    /**
     * @return array<string, mixed>
     */
    private function incomeShape(Income $income): array
    {
        return [
            'id' => $income->id,
            'description' => $income->description,
            'amount' => $income->amount,
            'currency' => $income->currency->value,
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
        ];
    }
}
