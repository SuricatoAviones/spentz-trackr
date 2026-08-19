<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $year = $request->integer('year', now()->year);

        $months = collect(range(1, 12))->map(function (int $month) use ($user, $year) {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = date('Y-m-t', strtotime($start));

            $expenses = Expense::query()
                ->forUser($user->id)
                ->forPeriod($start, $end)
                ->get();

            $byCurrency = ['usd' => 0.0, 'ves' => 0.0, 'usdt' => 0.0];
            foreach ($expenses as $expense) {
                $byCurrency[$expense->currency->value] += (float) $expense->amount;
            }

            return [
                'month' => $month,
                'label' => strftime('%b', strtotime($start)),
                'usd' => round((float) $expenses->sum('usd_amount'), 2),
                'usdt' => round((float) $expenses->sum('usdt_amount'), 2),
                'byCurrency' => $byCurrency,
            ];
        });

        $prevYear = $months->map(function (array $month, int $index) use ($user, $year) {
            $prevMonth = $month['month'] === 1
                ? ['year' => $year - 1, 'month' => 12]
                : ['year' => $year, 'month' => $month['month'] - 1];

            $start = sprintf('%04d-%02d-01', $prevMonth['year'], $prevMonth['month']);
            $end = date('Y-m-t', strtotime($start));

            return Expense::query()
                ->forUser($user->id)
                ->forPeriod($start, $end)
                ->sum('usd_amount');
        });

        $variations = $months->map(function (array $month, int $index) use ($prevYear): array {
            $previous = (float) $prevYear[$index];
            $current = (float) $month['usd'];

            if ($previous > 0) {
                $variation = round((($current - $previous) / $previous) * 100, 1);
            } else {
                $variation = $current > 0 ? 100.0 : 0.0;
            }

            return [
                ...$month,
                'variation' => $variation,
            ];
        });

        $annual = [
            'usd' => round((float) $variations->sum('usd'), 2),
            'usdt' => round((float) $variations->sum('usdt'), 2),
        ];

        $categories = Expense::query()
            ->forUser($user->id)
            ->forPeriod("{$year}-01-01", "{$year}-12-31")
            ->with('category')
            ->get()
            ->groupBy('category_id')
            ->map(function ($group) {
                $expense = $group->first();

                return [
                    'name' => $expense->category->name,
                    'color' => $expense->category->color,
                    'icon' => $expense->category->icon,
                    'total' => round((float) $group->sum('usd_amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $categoryTotal = (float) $categories->sum('total');
        $categories = $categories->map(fn (array $category) => [
            ...$category,
            'percent' => $categoryTotal > 0 ? round(($category['total'] / $categoryTotal) * 100, 1) : 0.0,
        ]);

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

        $sourceTotal = (float) $sources->sum('total');
        $sources = $sources->map(fn (array $source) => [
            ...$source,
            'percent' => $sourceTotal > 0 ? round(($source['total'] / $sourceTotal) * 100, 1) : 0.0,
        ]);

        $years = Expense::query()
            ->forUser($user->id)
            ->selectRaw('substr(spent_at, 1, 4) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn (int $year) => (int) $year);

        return Inertia::render('reports/index', [
            'year' => $year,
            'years' => $years,
            'annual' => $annual,
            'months' => $variations,
            'categories' => $categories,
            'sources' => $sources,
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
                        $expense->description,
                        $expense->category->name,
                        $expense->paymentSource->name,
                        $expense->currency->value,
                        number_format((float) $expense->amount, 2, ',', '.'),
                        $expense->exchange_rate !== null ? number_format((float) $expense->exchange_rate, 4, ',', '.') : '',
                        $expense->rate_provider ?? '',
                        number_format((float) $expense->usd_amount, 2, ',', '.'),
                        number_format((float) $expense->usdt_amount, 2, ',', '.'),
                        $expense->note ?? '',
                    ]);
                }
            });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
