import { Link, usePage } from '@inertiajs/react';
import { ArrowDownRight, ChevronRight, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { DonutChart } from '@/components/tracker/donut-chart';
import { ExpenseListItem } from '@/components/tracker/expense-list-item';
import { TrackerCard } from '@/components/tracker/tracker-card';
import { formatAmount, formatRate } from '@/lib/format';
import type { Expense, RateInfo } from '@/types';

type DashboardProps = {
    month: string;
    totals: {
        usd: number;
        usdt: number;
        byCurrency: Record<'usd' | 'ves' | 'usdt', number>;
    };
    rate: RateInfo;
    categories: {
        id: number;
        name: string;
        color: string;
        icon: string;
        total: number;
        count: number;
    }[];
    sources: {
        id: number;
        name: string;
        color: string;
        icon: string;
        total: number;
    }[];
    trend: { month: string; total: number }[];
    budgets: {
        id: number;
        name: string;
        icon: string;
        color: string;
        budget: number;
        spent: number;
    }[];
    recentExpenses: Expense[];
};

export default function Dashboard({
    month,
    totals,
    rate,
    categories,
    sources,
    trend,
    budgets,
    recentExpenses,
}: DashboardProps) {
    const { auth } = usePage().props;
    const [showAllSources, setShowAllSources] = useState(false);

    const sourceBadge = {
        bcv: { label: 'BCV', className: 'bg-emerald-500/15 text-emerald-400' },
        paralelo: {
            label: 'Paralelo',
            className: 'bg-amber-500/15 text-amber-400',
        },
        user: { label: 'Manual', className: 'bg-blue-500/15 text-blue-400' },
        dolarapi: {
            label: 'BCV',
            className: 'bg-emerald-500/15 text-emerald-400',
        },
        none: {
            label: 'Sin tasa',
            className: 'bg-white/10 text-muted-foreground',
        },
    }[rate.provider] ?? {
        label: rate.provider,
        className: 'bg-white/10 text-muted-foreground',
    };

    const displayedSources = showAllSources ? sources : sources.slice(0, 3);

    return (
        <div className="space-y-5 lg:grid lg:grid-cols-2 lg:gap-5 lg:space-y-0">
            <div className="flex items-center justify-between lg:col-span-2">
                <p className="text-sm font-medium text-muted-foreground">
                    Hola, {auth.user.name.split(' ')[0]} 👋
                </p>
                <div className="flex items-center gap-1.5">
                    <span
                        className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${sourceBadge.className}`}
                    >
                        {sourceBadge.label}
                    </span>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        Bs {formatRate(rate.rate)}
                    </span>
                </div>
            </div>

            <section className="rounded-xl bg-gradient-to-br from-surface-high to-surface-low p-5 lg:col-span-2">
                <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                    Total del mes · {month}
                </p>
                <div className="mt-2 flex items-baseline gap-2">
                    <span className="font-display text-4xl font-extrabold text-emerald-400 tabular-nums">
                        {formatAmount(totals.usd)}
                    </span>
                    <span className="text-sm font-semibold text-emerald-400/80">
                        USD
                    </span>
                </div>
                <div className="mt-1 flex items-baseline gap-2">
                    <span className="font-display text-2xl font-bold text-blue-400 tabular-nums">
                        {formatAmount(totals.usdt)}
                    </span>
                    <span className="text-xs font-semibold text-blue-400/80">
                        USDT
                    </span>
                </div>
                <div className="mt-4 flex gap-4 border-t border-white/5 pt-3">
                    <div className="flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-emerald-400" />
                        <span className="text-[11px] text-muted-foreground">
                            Bs {formatAmount(totals.byCurrency.ves)}
                        </span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-amber-400" />
                        <span className="text-[11px] text-muted-foreground">
                            USD {formatAmount(totals.byCurrency.usd)}
                        </span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-blue-400" />
                        <span className="text-[11px] text-muted-foreground">
                            USDT {formatAmount(totals.byCurrency.usdt)}
                        </span>
                    </div>
                </div>
            </section>

            {categories.length > 0 && (
                <TrackerCard title="Gasto por categoría">
                    <div className="flex items-center gap-5 px-4 pt-4 pb-4">
                        <DonutChart
                            data={categories.map(({ name, color, total }) => ({
                                name,
                                color,
                                total,
                            }))}
                            centerValue={`${categories.length}`}
                            centerLabel="categorías"
                        />
                        <div className="flex-1 space-y-2">
                            {categories.slice(0, 4).map((category) => (
                                <div
                                    key={category.id}
                                    className="flex items-center justify-between gap-2"
                                >
                                    <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span
                                            className="size-2 rounded-full"
                                            style={{
                                                backgroundColor: category.color,
                                            }}
                                        />
                                        {category.name}
                                    </span>
                                    <span className="text-xs font-semibold text-foreground tabular-nums">
                                        {formatAmount(category.total)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </TrackerCard>
            )}

            {budgets.length > 0 && (
                <TrackerCard title="Presupuestos del mes">
                    <div className="space-y-4 px-4 pt-4 pb-4">
                        {budgets.map((budget) => {
                            const percent =
                                budget.budget > 0
                                    ? Math.min(
                                          (budget.spent / budget.budget) * 100,
                                          100,
                                      )
                                    : 0;
                            const over = budget.spent > budget.budget;

                            return (
                                <div key={budget.id}>
                                    <div className="mb-1.5 flex items-center justify-between gap-2">
                                        <span className="flex items-center gap-2 text-xs font-medium text-foreground">
                                            <span
                                                className="size-2.5 rounded-full"
                                                style={{
                                                    backgroundColor:
                                                        budget.color,
                                                }}
                                            />
                                            {budget.name}
                                        </span>
                                        <span
                                            className={`text-xs font-semibold tabular-nums ${
                                                over
                                                    ? 'text-destructive'
                                                    : 'text-muted-foreground'
                                            }`}
                                        >
                                            {formatAmount(budget.spent)} /{' '}
                                            {formatAmount(budget.budget)} USD
                                        </span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-white/5">
                                        <div
                                            className={`h-full rounded-full transition-all ${
                                                over
                                                    ? 'bg-gradient-to-r from-red-500 to-red-600'
                                                    : percent > 80
                                                      ? 'bg-gradient-to-r from-amber-400 to-amber-500'
                                                      : 'bg-gradient-to-r from-emerald-400 to-emerald-500'
                                            }`}
                                            style={{ width: `${percent}%` }}
                                        />
                                    </div>
                                    {over && (
                                        <p className="mt-1 text-[10px] font-medium text-destructive">
                                            Superaste el presupuesto por{' '}
                                            {formatAmount(
                                                budget.spent - budget.budget,
                                            )}{' '}
                                            USD
                                        </p>
                                    )}
                                </div>
                            );
                        })}
                        <Link
                            href="/categories"
                            className="flex items-center justify-center gap-1 border-t border-white/5 pt-3 text-xs font-semibold text-emerald-400"
                        >
                            Editar presupuestos{' '}
                            <ChevronRight className="size-3.5" />
                        </Link>
                    </div>
                </TrackerCard>
            )}

            {trend.length > 0 && (
                <TrackerCard title="Evolución últimos 6 meses">
                    <div className="px-4 pt-4 pb-4">
                        <div className="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <ArrowDownRight className="size-3.5 text-emerald-400" />
                            <span>
                                Promedio{' '}
                                {formatAmount(
                                    trend.reduce(
                                        (sum, item) => sum + item.total,
                                        0,
                                    ) / trend.length,
                                )}{' '}
                                USD
                            </span>
                        </div>
                        <svg
                            viewBox="0 0 300 120"
                            className="w-full"
                            style={{ height: 120 }}
                            preserveAspectRatio="none"
                        >
                            {trend.map((item, index, arr) => {
                                const max = Math.max(
                                    ...arr.map((i) => i.total),
                                    1,
                                );
                                const x =
                                    (index / Math.max(arr.length - 1, 1)) * 300;
                                const y = 112 - (item.total / max) * 100;
                                const prevX =
                                    (Math.max(index - 1, 0) /
                                        Math.max(arr.length - 1, 1)) *
                                    300;
                                const prevY =
                                    112 -
                                    (arr[Math.max(index - 1, 0)].total / max) *
                                        100;

                                return (
                                    <line
                                        key={item.month}
                                        x1={prevX}
                                        y1={prevY}
                                        x2={x}
                                        y2={y}
                                        stroke="#10B981"
                                        strokeWidth="2.5"
                                        strokeLinecap="round"
                                    />
                                );
                            })}
                            {trend.map((item, index, arr) => {
                                const max = Math.max(
                                    ...arr.map((i) => i.total),
                                    1,
                                );
                                const x =
                                    (index / Math.max(arr.length - 1, 1)) * 300;
                                const y = 112 - (item.total / max) * 100;

                                return (
                                    <circle
                                        key={item.month}
                                        cx={x}
                                        cy={y}
                                        r="3.5"
                                        fill="#0B1220"
                                        stroke="#10B981"
                                        strokeWidth="2"
                                    />
                                );
                            })}
                        </svg>
                        <div className="mt-1 flex justify-between px-1">
                            {trend.map((item) => (
                                <span
                                    key={item.month}
                                    className="text-[9px] font-medium text-muted-foreground"
                                >
                                    {item.month}
                                </span>
                            ))}
                        </div>
                    </div>
                </TrackerCard>
            )}

            {sources.length > 0 && (
                <TrackerCard title="Gasto por origen">
                    <div className="space-y-3 px-4 pt-4 pb-4">
                        {displayedSources.map((source) => (
                            <div
                                key={source.id}
                                className="flex items-center justify-between gap-2"
                            >
                                <span className="flex items-center gap-2.5 text-sm text-foreground">
                                    <span
                                        className="size-2.5 rounded-full"
                                        style={{
                                            backgroundColor: source.color,
                                        }}
                                    />
                                    {source.name}
                                </span>
                                <span className="text-sm font-semibold text-foreground tabular-nums">
                                    {formatAmount(source.total)}
                                </span>
                            </div>
                        ))}
                        {sources.length > 3 && (
                            <button
                                type="button"
                                onClick={() =>
                                    setShowAllSources((value) => !value)
                                }
                                className="text-xs font-medium text-emerald-400"
                            >
                                {showAllSources
                                    ? 'Ver menos'
                                    : `Ver ${sources.length - 3} más`}
                            </button>
                        )}
                    </div>
                </TrackerCard>
            )}

            <TrackerCard title="Últimos gastos" className="lg:col-span-2">
                <div className="space-y-2.5 p-3.5">
                    {recentExpenses.length === 0 && (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            Aún no tienes gastos registrados.
                        </p>
                    )}
                    {recentExpenses.map((expense) => (
                        <ExpenseListItem key={expense.id} expense={expense} />
                    ))}
                </div>
                {recentExpenses.length > 0 && (
                    <Link
                        href="/expenses"
                        className="flex items-center justify-center gap-1 border-t border-white/5 py-3 text-xs font-semibold text-emerald-400"
                    >
                        Ver todos los gastos{' '}
                        <ChevronRight className="size-3.5" />
                    </Link>
                )}
            </TrackerCard>

            <div className="flex items-center justify-center gap-1.5 pb-2 text-[10px] text-muted-foreground/70 lg:col-span-2">
                <RefreshCw className="size-3" />
                Tasa sincronizada automáticamente con dolarapi.com
            </div>
        </div>
    );
}
