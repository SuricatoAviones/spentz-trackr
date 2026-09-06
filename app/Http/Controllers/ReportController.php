<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Income;
use App\Support\CsvExporter;
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
     * @return Collection<int, array<string, mixed>>
     */
    private function monthlySeries(object $user, int $year, string $model)
    {
        $dateColumn = $model === Income::class ? 'received_at' : 'spent_at';

        return collect(range(1, 12))->map(function (int $month) use ($user, $year, $model): array {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = date('Y-m-t', strtotime($start));

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
    private function previousPeriodTotal(object $user, int $year, int $month, string $model, int $index): float
    {
        $prevMonth = $month === 1
            ? ['year' => $year - 1, 'month' => 12]
            : ['year' => $year, 'month' => $month - 1];

        $start = sprintf('%04d-%02d-01', $prevMonth['year'], $prevMonth['month']);
        $end = date('Y-m-t', strtotime($start));

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
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryBreakdown(object $user, int $year, string $model)
    {
        $categories = $model::query()
            ->forUser($user->id)
            ->forPeriod("{$year}-01-01", "{$year}-12-31")
            ->with('category')
            ->get()
            ->groupBy('category_id')
            ->map(function ($group) {
                $record = $group->first();

                return [
                    'name' => $record->category->name,
                    'color' => $record->category->color,
                    'icon' => $record->category->icon,
                    'total' => round((float) $group->sum('usd_amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $total = (float) $categories->sum('total');

        return $categories->map(fn (array $category) => [
            ...$category,
            'percent' => $total > 0 ? round(($category['total'] / $total) * 100, 1) : 0.0,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sourceBreakdown(object $user, int $year)
    {
        $sources = Expense::query()
            ->forUser($user->id)
            ->forPeriod("{$year}-01-01", "{$year}-12-31")
            ->with('paymentSource')
            ->get()
            ->groupBy('payment_source_id')
            ->map(function ($group) {
                $expense = $group->first();

                return [
                    'name' => $expense->paymentSource->name,
                    'color' => $expense->paymentSource->color,
                    'icon' => $expense->paymentSource->icon,
                    'total' => round((float) $group->sum('usd_amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $total = (float) $sources->sum('total');

        return $sources->map(fn (array $source) => [
            ...$source,
            'percent' => $total > 0 ? round(($source['total'] / $total) * 100, 1) : 0.0,
        ]);
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

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'fecha',
                'descripcion',
                'categoria',
                'origen',
                'moneda',
                'monto',
                'tasa_bs_usd',
                'tasa_fuente',
                'equivalente_usd',
                'equivalente_usdt',
                'nota',
            ]);

            $query->chunk(500, function ($expenses) use ($output): void {
                foreach ($expenses as $expense) {
                    fputcsv($output, [
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

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
