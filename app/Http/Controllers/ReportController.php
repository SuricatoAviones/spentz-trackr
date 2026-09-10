<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentSource;
use App\Models\User;
use App\Support\CsvExporter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $year = $request->integer('year', now()->year);

        $showIncomes = $user->tracking_type !== 'expenses';
        $showExpenses = $user->tracking_type !== 'income';

        $expenseMonths = $showExpenses
            ? $this->monthlySeries($user, $year, Expense::class)
            : collect(range(1, 12))->map(fn (int $month) => [
                'month' => $month,
                'usd' => 0.0,
                'usdt' => 0.0,
                'byCurrency' => ['usd' => 0.0, 'ves' => 0.0, 'usdt' => 0.0],
            ]);

        $incomeMonths = $showIncomes
            ? $this->monthlySeries($user, $year, Income::class)
            : collect(range(1, 12))->map(fn (int $month) => [
                'month' => $month,
                'usd' => 0.0,
                'usdt' => 0.0,
                'byCurrency' => ['usd' => 0.0, 'ves' => 0.0, 'usdt' => 0.0],
            ]);

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

        return Inertia::render('reports/index', [
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

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        $query = Expense::query()
            ->forUser($user->id)
            ->with(['category', 'paymentSource'])
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
            })
            ->orderByDesc('spent_at');

        $filename = 'gastos_'.now()->format('Y-m').'.csv';

        $columns = [
            'fecha', 'descripcion', 'categoria', 'origen', 'moneda', 'monto',
            'tasa_bs_usd', 'tasa_fuente', 'equivalente_usd', 'equivalente_usdt', 'nota',
        ];

        return CsvExporter::download($filename, $columns, function (\Closure $writeRow) use ($query): void {
            $query->chunk(500, function (EloquentCollection $expenses) use ($writeRow): void {
                foreach ($expenses as $expense) {
                    $writeRow([
                        $expense->spent_at->toDateString(),
                        CsvExporter::cell($expense->description),
                        CsvExporter::cell($expense->category->name),
                        CsvExporter::cell($expense->paymentSource->name),
                        $expense->currency->value,
                        number_format((float) $expense->amount, 2, ',', '.'),
                        $expense->exchange_rate !== null ? number_format((float) $expense->exchange_rate, 4, ',', '.') : '',
                        CsvExporter::cell($expense->rate_provider ?? ''),
                        number_format((float) $expense->usd_amount, 2, ',', '.'),
                        number_format((float) $expense->usdt_amount, 2, ',', '.'),
                        CsvExporter::cell($expense->note ?? ''),
                    ]);
                }
            });
        });
    }
}
