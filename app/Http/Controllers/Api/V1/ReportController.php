<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentSource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends BaseApiController
{
    /**
     * Annual report: monthly series, variations, category and source breakdown.
     */
    #[QueryParameter('year', description: 'Año del reporte (por defecto el año actual).', type: 'integer', example: 2026)]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $year = $request->integer('year', now()->year);

        $showIncomes = $user->tracking_type !== 'expenses';
        $showExpenses = $user->tracking_type !== 'income';

        $expenseMonths = $showExpenses
            ? $this->monthlySeries($user, $year, Expense::class)
            : collect(range(1, 12))->map(fn (int $month) => $this->emptyMonth($month));

        $incomeMonths = $showIncomes
            ? $this->monthlySeries($user, $year, Income::class)
            : collect(range(1, 12))->map(fn (int $month) => $this->emptyMonth($month));

        $prevExpense = $expenseMonths->map(fn (array $month, int $index) => $this->previousPeriodTotal($user, $year, $month['month'], Expense::class, $index));

        $prevIncome = $incomeMonths->map(fn (array $month, int $index) => $this->previousPeriodTotal($user, $year, $month['month'], Income::class, $index));

        $variations = $expenseMonths->map(function (array $month, int $index) use ($prevExpense): array {
            return [
                ...$month,
                'variation' => $this->variation((float) $prevExpense[$index], (float) $month['usd']),
            ];
        });

        $incomeVariations = $incomeMonths->map(function (array $month, int $index) use ($prevIncome): array {
            return [
                ...$month,
                'variation' => $this->variation((float) $prevIncome[$index], (float) $month['usd']),
            ];
        });

        $annual = [
            'usd' => round((float) $variations->sum('usd'), 2),
            'usdt' => round((float) $variations->sum('usdt'), 2),
        ];

        $incomeAnnual = [
            'usd' => round((float) $incomeVariations->sum('usd'), 2),
            'usdt' => round((float) $incomeVariations->sum('usdt'), 2),
        ];

        $net = [
            'usd' => round($incomeAnnual['usd'] - $annual['usd'], 2),
            'usdt' => round($incomeAnnual['usdt'] - $annual['usdt'], 2),
        ];

        $categories = $showExpenses ? $this->categoryBreakdown($user, $year, Expense::class) : collect();
        $incomeCategories = $showIncomes ? $this->categoryBreakdown($user, $year, Income::class) : collect();
        $sources = $showExpenses ? $this->sourceBreakdown($user, $year) : collect();

        $years = Expense::query()
            ->forUser($user->id)
            ->selectRaw('substr(spent_at, 1, 4) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn (int $year) => (int) $year)
            ->concat(
                Income::query()
                    ->forUser($user->id)
                    ->selectRaw('substr(received_at, 1, 4) as year')
                    ->distinct()
                    ->orderByDesc('year')
                    ->pluck('year')
                    ->map(fn (int $year) => (int) $year)
            )
            ->unique()
            ->sortDesc()
            ->values();

        return $this->apiResponse([
            'year' => $year,
            'years' => $years,
            'annual' => $annual,
            'incomeAnnual' => $incomeAnnual,
            'net' => $net,
            'showIncomes' => $showIncomes,
            'showExpenses' => $showExpenses,
            'months' => $variations,
            'incomeMonths' => $incomeVariations,
            'categories' => $categories,
            'incomeCategories' => $incomeCategories,
            'sources' => $sources,
        ]);
    }

    /**
     * Aggregate monthly totals over the last N months.
     */
    #[QueryParameter('months', description: 'Cantidad de meses a incluir en la tendencia (0-24).', type: 'integer', default: 12, example: 6)]
    public function monthlySummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $months = $request->integer('months', 12);

        $trend = collect(range(0, max(0, min(24, $months)) - 1))
            ->map(function (int $i) use ($user) {
                $month = today()->subMonths($i);
                $start = $month->copy()->startOfMonth()->toDateString();
                $end = $month->copy()->endOfMonth()->toDateString();

                $expenses = Expense::query()
                    ->forUser($user->id)
                    ->forPeriod($start, $end)
                    ->get();

                return [
                    'month' => $month->format('Y-m'),
                    'total_usd' => round((float) $expenses->sum('usd_amount'), 2),
                    'total_usdt' => round((float) $expenses->sum('usdt_amount'), 2),
                    'count' => $expenses->count(),
                ];
            })
            ->reverse()
            ->values();

        return $this->apiResponse([
            'trend' => $trend,
            'months' => $months,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMonth(int $month): array
    {
        return [
            'month' => $month,
            'usd' => 0.0,
            'usdt' => 0.0,
            'byCurrency' => ['usd' => 0.0, 'ves' => 0.0, 'usdt' => 0.0],
        ];
    }

    /**
     * @param  class-string<Expense|Income>  $model
     * @return Collection<int, array{month: int, usd: float, usdt: float, byCurrency: array{usd: float, ves: float, usdt: float}}>
     */
    private function monthlySeries(User $user, int $year, string $model): Collection
    {
        return collect(range(1, 12))->map(function (int $month) use ($user, $year, $model): array {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = CarbonImmutable::parse($start)->endOfMonth()->toDateString();

            $records = $model::query()
                ->forUser($user->id)
                ->forPeriod($start, $end)
                ->get();

            $byCurrency = ['usd' => 0.0, 'ves' => 0.0, 'usdt' => 0.0];
            foreach ($records as $record) {
                $byCurrency[$record->currency->value] += (float) $record->amount;
            }

            return [
                'month' => $month,
                'usd' => round((float) $records->sum('usd_amount'), 2),
                'usdt' => round((float) $records->sum('usdt_amount'), 2),
                'byCurrency' => $byCurrency,
            ];
        });
    }

    /**
     * @param  class-string<Expense|Income>  $model
     */
    private function previousPeriodTotal(User $user, int $year, int $month, string $model, int $index): float
    {
        $prevMonth = $month === 1
            ? ['year' => $year - 1, 'month' => 12]
            : ['year' => $year, 'month' => $month - 1];

        $start = sprintf('%04d-%02d-01', $prevMonth['year'], $prevMonth['month']);
        $end = CarbonImmutable::parse($start)->endOfMonth()->toDateString();

        return (float) $model::query()
            ->forUser($user->id)
            ->forPeriod($start, $end)
            ->sum('usd_amount');
    }

    private function variation(float $previous, float $current): float
    {
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 1);
        }

        return $current > 0 ? 100.0 : 0.0;
    }

    /**
     * @param  class-string<Expense|Income>  $model
     * @return Collection<int, array{name: string, color: string, icon: string, total: float, percent: float}>
     */
    private function categoryBreakdown(User $user, int $year, string $model): Collection
    {
        $totals = $model::query()
            ->forUser($user->id)
            ->forPeriod("{$year}-01-01", "{$year}-12-31")
            ->selectRaw('category_id, sum(usd_amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $categories = Category::query()->whereKey($totals->keys()->all())->get(['id', 'name', 'color', 'icon']);
        $grandTotal = (float) $totals->sum();

        return $totals
            ->map(function (mixed $rawTotal, int $categoryId) use ($categories, $grandTotal): array {
                $category = $categories->firstWhere('id', $categoryId);
                $total = round((float) $rawTotal, 2);

                return [
                    'name' => $category instanceof Category ? $category->name : '—',
                    'color' => $category instanceof Category ? $category->color : '#6B7280',
                    'icon' => $category instanceof Category ? $category->icon : 'circle',
                    'total' => $total,
                    'percent' => $grandTotal > 0 ? round(($total / $grandTotal) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * @return Collection<int, array{name: string, color: string, icon: string, total: float, percent: float}>
     */
    private function sourceBreakdown(User $user, int $year): Collection
    {
        $totals = Expense::query()
            ->forUser($user->id)
            ->forPeriod("{$year}-01-01", "{$year}-12-31")
            ->selectRaw('payment_source_id, sum(usd_amount) as total')
            ->groupBy('payment_source_id')
            ->pluck('total', 'payment_source_id');

        $sources = PaymentSource::query()->whereKey($totals->keys()->all())->get(['id', 'name', 'color', 'icon']);
        $grandTotal = (float) $totals->sum();

        return $totals
            ->map(function (mixed $rawTotal, int $sourceId) use ($sources, $grandTotal): array {
                $source = $sources->firstWhere('id', $sourceId);
                $total = round((float) $rawTotal, 2);

                return [
                    'name' => $source instanceof PaymentSource ? $source->name : '—',
                    'color' => $source instanceof PaymentSource ? $source->color : '#6B7280',
                    'icon' => $source instanceof PaymentSource ? $source->icon : 'wallet',
                    'total' => $total,
                    'percent' => $grandTotal > 0 ? round(($total / $grandTotal) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }
}
