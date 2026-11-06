<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentSource;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Estado de cuenta de un periodo: totales, desgloses y movimientos en orden
 * cronológico con el neto acumulado.
 *
 * Es determinista a propósito. La página del estado de cuenta, el export CSV,
 * la API y la IA leen de aquí: el asistente comenta estas cifras pero **nunca
 * las calcula él**, porque un modelo de lenguaje suma mal y lo haría con
 * aplomo. Todo sale de los equivalentes USD/USDT congelados (ADR-001); nada se
 * recalcula con la tasa de hoy.
 *
 * No hay saldo de apertura: la app no lleva saldos por cuenta (ver ADR-012),
 * así que el "acumulado" es el neto dentro del periodo, no un saldo bancario.
 */
class AccountStatementService
{
    public const MAX_MOVEMENTS = 1000;

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, CarbonInterface $from, CarbonInterface $to, int $movementLimit = self::MAX_MOVEMENTS): array
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();

        $withExpenses = $user->tracking_type !== 'income';
        $withIncomes = $user->tracking_type !== 'expenses';

        $expenses = $withExpenses ? $this->expenses($user, $from, $to) : new Collection;
        $incomes = $withIncomes ? $this->incomes($user, $from, $to) : new Collection;

        $expenseUsd = round((float) $expenses->sum('usd_amount'), 2);
        $incomeUsd = round((float) $incomes->sum('usd_amount'), 2);
        $days = (int) $from->diffInDays($to) + 1;

        [$movements, $truncated] = $this->movements($expenses, $incomes, $movementLimit);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
            ],
            'tracking' => [
                'expenses' => $withExpenses,
                'incomes' => $withIncomes,
            ],
            'totals' => [
                'incomes' => [
                    'usd' => $incomeUsd,
                    'usdt' => round((float) $incomes->sum('usdt_amount'), 2),
                    'count' => $incomes->count(),
                ],
                'expenses' => [
                    'usd' => $expenseUsd,
                    'usdt' => round((float) $expenses->sum('usdt_amount'), 2),
                    'count' => $expenses->count(),
                    'commissions_ves' => round((float) $expenses->sum('commission'), 2),
                ],
                'net' => [
                    'usd' => round($incomeUsd - $expenseUsd, 2),
                    'usdt' => round((float) $incomes->sum('usdt_amount') - (float) $expenses->sum('usdt_amount'), 2),
                ],
                'savings_rate' => $incomeUsd > 0 ? round((($incomeUsd - $expenseUsd) / $incomeUsd) * 100, 1) : null,
                'daily_average_expense_usd' => $days > 0 ? round($expenseUsd / $days, 2) : 0.0,
            ],
            'by_currency' => [
                'expenses' => $this->byCurrency($expenses),
                'incomes' => $this->byCurrency($incomes),
            ],
            'expense_categories' => $this->categoryBreakdown($expenses),
            'income_categories' => $this->categoryBreakdown($incomes),
            'sources' => $this->sourceBreakdown($expenses),
            'largest_expenses' => $expenses
                ->sortByDesc(fn (Expense $expense): float => (float) $expense->usd_amount)
                ->take(5)
                ->map(fn (Expense $expense): array => [
                    'id' => $expense->id,
                    'date' => $expense->spent_at->toDateString(),
                    'description' => $expense->description,
                    'category' => $expense->category->name,
                    'usd_amount' => round((float) $expense->usd_amount, 2),
                ])
                ->values()
                ->all(),
            'previous' => $this->previousPeriod($user, $from, $days, $withExpenses, $withIncomes, $expenseUsd, $incomeUsd),
            'budget' => $this->budget($user, $from, $to),
            'movements' => $movements,
            'movements_truncated' => $truncated,
        ];
    }

    /**
     * @return Collection<int, Expense>
     */
    private function expenses(User $user, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Expense::query()
            ->forUser($user->id)
            ->whereDate('spent_at', '>=', $from->toDateString())
            ->whereDate('spent_at', '<=', $to->toDateString())
            ->with(['category:id,name,color,icon', 'paymentSource:id,name,color,icon'])
            ->orderBy('spent_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Income>
     */
    private function incomes(User $user, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Income::query()
            ->forUser($user->id)
            ->whereDate('received_at', '>=', $from->toDateString())
            ->whereDate('received_at', '<=', $to->toDateString())
            ->with(['category:id,name,color,icon'])
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     * @param  Collection<int, Income>  $incomes
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private function movements(Collection $expenses, Collection $incomes, int $limit): array
    {
        $rows = [];

        foreach ($incomes as $income) {
            $rows[] = [
                'type' => 'income',
                'id' => $income->id,
                'date' => $income->received_at->toDateString(),
                'description' => $income->description,
                'category' => $income->category->name,
                'source' => null,
                'currency' => $income->currency->value,
                'amount' => round((float) $income->amount, 2),
                'usd_amount' => round((float) $income->usd_amount, 2),
            ];
        }

        foreach ($expenses as $expense) {
            $rows[] = [
                'type' => 'expense',
                'id' => $expense->id,
                'date' => $expense->spent_at->toDateString(),
                'description' => $expense->description,
                'category' => $expense->category->name,
                'source' => $expense->paymentSource->name,
                'currency' => $expense->currency->value,
                'amount' => round((float) $expense->amount + (float) $expense->commission, 2),
                'usd_amount' => round((float) $expense->usd_amount, 2),
            ];
        }

        // Mismo día: primero los ingresos, como en un extracto bancario.
        usort($rows, fn (array $a, array $b): int => [$a['date'], $a['type'] === 'income' ? 0 : 1, $a['id']]
            <=> [$b['date'], $b['type'] === 'income' ? 0 : 1, $b['id']]);

        $running = 0.0;

        foreach ($rows as $index => $row) {
            $running += $row['type'] === 'income' ? $row['usd_amount'] : -$row['usd_amount'];
            $rows[$index]['running_usd'] = round($running, 2);
        }

        if ($limit <= 0) {
            return [[], $rows !== []];
        }

        $truncated = count($rows) > $limit;

        return [$truncated ? array_slice($rows, -$limit) : $rows, $truncated];
    }

    /**
     * @param  Collection<int, Expense>|Collection<int, Income>  $records
     * @return array{usd: float, ves: float, usdt: float}
     */
    private function byCurrency(Collection $records): array
    {
        $totals = ['usd' => 0.0, 'ves' => 0.0, 'usdt' => 0.0];

        foreach ($records as $record) {
            $commission = $record instanceof Expense ? (float) $record->commission : 0.0;
            $totals[$record->currency->value] += (float) $record->amount + $commission;
        }

        return array_map(fn (float $total): float => round($total, 2), $totals);
    }

    /**
     * @param  Collection<int, Expense>|Collection<int, Income>  $records
     * @return list<array<string, mixed>>
     */
    private function categoryBreakdown(Collection $records): array
    {
        $grand = (float) $records->sum('usd_amount');

        return array_values($records
            ->groupBy('category_id')
            ->map(function ($group) use ($grand): array {
                $category = $group->first()?->category;
                $total = round((float) $group->sum('usd_amount'), 2);

                return [
                    'id' => $category instanceof Category ? $category->id : null,
                    'name' => $category instanceof Category ? $category->name : '—',
                    'color' => $category instanceof Category ? $category->color : '#6B7280',
                    'icon' => $category instanceof Category ? $category->icon : 'circle',
                    'total_usd' => $total,
                    'count' => $group->count(),
                    'percent' => $grand > 0 ? round(($total / $grand) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total_usd')
            ->all());
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     * @return list<array<string, mixed>>
     */
    private function sourceBreakdown(Collection $expenses): array
    {
        $grand = (float) $expenses->sum('usd_amount');

        return array_values($expenses
            ->groupBy('payment_source_id')
            ->map(function ($group) use ($grand): array {
                $source = $group->first()?->paymentSource;
                $total = round((float) $group->sum('usd_amount'), 2);

                return [
                    'id' => $source instanceof PaymentSource ? $source->id : null,
                    'name' => $source instanceof PaymentSource ? $source->name : '—',
                    'color' => $source instanceof PaymentSource ? $source->color : '#6B7280',
                    'icon' => $source instanceof PaymentSource ? $source->icon : 'wallet',
                    'total_usd' => $total,
                    'count' => $group->count(),
                    'percent' => $grand > 0 ? round(($total / $grand) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total_usd')
            ->all());
    }

    /**
     * El periodo inmediatamente anterior de la misma longitud.
     *
     * @return array<string, mixed>
     */
    private function previousPeriod(User $user, CarbonInterface $from, int $days, bool $withExpenses, bool $withIncomes, float $expenseUsd, float $incomeUsd): array
    {
        $previousTo = $from->subDay();
        $previousFrom = $previousTo->subDays($days - 1);

        $previousExpenses = $withExpenses ? round((float) Expense::query()
            ->forUser($user->id)
            ->whereDate('spent_at', '>=', $previousFrom->toDateString())
            ->whereDate('spent_at', '<=', $previousTo->toDateString())
            ->sum('usd_amount'), 2) : 0.0;

        $previousIncomes = $withIncomes ? round((float) Income::query()
            ->forUser($user->id)
            ->whereDate('received_at', '>=', $previousFrom->toDateString())
            ->whereDate('received_at', '<=', $previousTo->toDateString())
            ->sum('usd_amount'), 2) : 0.0;

        return [
            'from' => $previousFrom->toDateString(),
            'to' => $previousTo->toDateString(),
            'expenses_usd' => $previousExpenses,
            'incomes_usd' => $previousIncomes,
            'expenses_variation' => $this->variation($previousExpenses, $expenseUsd),
            'incomes_variation' => $this->variation($previousIncomes, $incomeUsd),
        ];
    }

    private function variation(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * El presupuesto mensual solo tiene sentido si el periodo cae dentro de un
     * mes natural.
     *
     * @return array{monthly: float, spent: float, percent: float, remaining: float}|null
     */
    private function budget(User $user, CarbonInterface $from, CarbonInterface $to): ?array
    {
        $monthly = (float) $user->monthly_budget;

        if ($monthly <= 0 || $from->format('Y-m') !== $to->format('Y-m')) {
            return null;
        }

        $monthStart = Date::parse($from->format('Y-m-01'));
        $spent = round((float) Expense::query()
            ->forUser($user->id)
            ->whereDate('spent_at', '>=', $monthStart->toDateString())
            ->whereDate('spent_at', '<=', $monthStart->endOfMonth()->toDateString())
            ->sum('usd_amount'), 2);

        return [
            'monthly' => round($monthly, 2),
            'spent' => $spent,
            'percent' => round(($spent / $monthly) * 100, 1),
            'remaining' => round($monthly - $spent, 2),
        ];
    }
}
