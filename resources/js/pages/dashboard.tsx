import { Link, setLayoutProps, usePage } from '@inertiajs/react';
import { ChevronRight, RefreshCw, TrendingUp, Wallet } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DonutChart } from '@/components/tracker/donut-chart';
import { ExpenseListItem } from '@/components/tracker/expense-list-item';
import { IncomeListItem } from '@/components/tracker/income-list-item';
import { TrackerCard } from '@/components/tracker/tracker-card';
import { formatAmount, formatIsoMonthLabel, formatRate } from '@/lib/format';
import type { Expense, Income, RateInfo } from '@/types';

type CategorySlice = {
    id: number;
    name: string;
    color: string;
    icon: string;
    total: number;
    count: number;
};

type Trending = { month: string; total: number }[];

type RecentItem = {
    kind: 'expense' | 'income';
    date: string;
    expense?: Expense;
    income?: Income;
};

type DashboardProps = {
    month: string;
    trackingType: 'expenses' | 'income' | 'both';
    rate: RateInfo;
    monthlyBudget?: number | null;
    totals?: {
        usd: number;
        usdt: number;
        byCurrency: Record<'usd' | 'ves' | 'usdt', number>;
    };
    incomeTotals?: {
        usd: number;
        usdt: number;
        byCurrency: Record<'usd' | 'ves' | 'usdt', number>;
    };
    net?: { usd: number; usdt: number };
    categories?: CategorySlice[];
    incomeCategories?: CategorySlice[];
    sources?: {
        id: number;
        name: string;
        color: string;
        icon: string;
        total: number;
    }[];
    trend?: Trending;
    incomeTrend?: Trending;
    budgets?: {
        id: number;
        name: string;
        icon: string;
        color: string;
        budget: number;
        spent: number;
    }[];
    recentExpenses?: Expense[];
    recentIncomes?: Income[];
    recent?: RecentItem[];
};

export default function Dashboard({
    month,
    trackingType,
    rate,
    monthlyBudget,
    totals,
    incomeTotals,
    net,
    categories = [],
    incomeCategories = [],
    sources = [],
    trend = [],
    incomeTrend = [],
    budgets = [],
    recentExpenses = [],
    recentIncomes = [],
    recent = [],
}: DashboardProps) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [showAllSources, setShowAllSources] = useState(false);

    setLayoutProps({
        title: t('dashboard.title'),
        description: t('dashboard.subtitle'),
    });

    const sourceBadge = {
        bcv: {
            label: t('rates.provider_bcv'),
            className: 'bg-emerald-500/15 text-emerald-400',
        },
        paralelo: {
            label: t('rates.provider_paralelo'),
            className: 'bg-amber-500/15 text-amber-400',
        },
        user: {
            label: t('rates.provider_manual'),
            className: 'bg-blue-500/15 text-blue-400',
        },
        dolarapi: {
            label: t('rates.provider_bcv'),
            className: 'bg-emerald-500/15 text-emerald-400',
        },
        none: {
            label: t('rates.provider_none'),
            className: 'bg-white/10 text-muted-foreground',
        },
    }[rate.provider] ?? {
        label: rate.provider,
        className: 'bg-white/10 text-muted-foreground',
    };

    const displayedSources = showAllSources ? sources : sources.slice(0, 3);
    const recentItems =
        recent.length > 0
            ? recent
            : [
                  ...recentExpenses.map((expense): RecentItem => ({
                      kind: 'expense',
                      date: expense.spent_at,
                      expense,
                  })),
                  ...recentIncomes.map((income): RecentItem => ({
                      kind: 'income',
                      date: income.received_at,
                      income,
                  })),
              ].sort((a, b) => b.date.localeCompare(a.date));

    const showExpenses = () => trackingType === 'expenses' || trackingType === 'both';
    const activeBudget = monthlyBudget ?? 0;
    const spentForBudget = () => (showExpenses() ? totals?.usd ?? 0 : 0);
    const budgetPercent = () =>
        activeBudget > 0 ? Math.min((spentForBudget() / activeBudget) * 100, 100) : 0;
    const budgetOver = () => spentForBudget() > activeBudget;
    const budgetRemaining = () => activeBudget - spentForBudget();
    const ajustesUrl = () => '/ajustes';
    const hasActiveBudget = monthlyBudget !== null && monthlyBudget !== undefined && monthlyBudget > 0;

    return (
        <div className="space-y-5 lg:grid lg:grid-cols-2 lg:gap-5 lg:space-y-0">
            <div className="flex items-center justify-between lg:col-span-2">
                <p className="text-sm font-medium text-muted-foreground">
                    {t('dashboard.greeting', {
                        name: auth.user.name.split(' ')[0],
                    })}
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

            {trackingType === 'both' && totals && incomeTotals ? (
                <section className="rounded-xl bg-gradient-to-br from-surface-high to-surface-low p-5 lg:col-span-2">
                    <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('dashboard.overview_month', { month })}
                    </p>
                    <div className="mt-3 grid grid-cols-3 gap-3">
                        <div>
                            <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('dashboard.spent_label')}
                            </p>
                            <p className="mt-1 font-display text-2xl font-extrabold text-emerald-400 tabular-nums lg:text-3xl">
                                {formatAmount(totals.usd)}
                                <span className="ml-1 text-xs font-semibold text-emerald-400/70">
                                    USD
                                </span>
                            </p>
                            <p className="text-sm font-semibold text-blue-400 tabular-nums">
                                {formatAmount(totals.usdt)}{' '}
                                <span className="text-[10px] font-medium text-blue-400/70">
                                    USDT
                                </span>
                            </p>
                        </div>
                        <div className="border-x border-white/5 px-3">
                            <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('dashboard.income_label')}
                            </p>
                            <p className="mt-1 font-display text-2xl font-extrabold text-blue-400 tabular-nums lg:text-3xl">
                                {formatAmount(incomeTotals.usd)}
                                <span className="ml-1 text-xs font-semibold text-blue-400/70">
                                    USD
                                </span>
                            </p>
                            <p className="text-sm font-semibold text-blue-400/80 tabular-nums">
                                {formatAmount(incomeTotals.usdt)}{' '}
                                <span className="text-[10px] font-medium text-blue-400/70">
                                    USDT
                                </span>
                            </p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('dashboard.net_label')}
                            </p>
                            <p
                                className={`mt-1 font-display text-2xl font-extrabold tabular-nums lg:text-3xl ${
                                    (net?.usd ?? 0) >= 0
                                        ? 'text-white'
                                        : 'text-destructive'
                                }`}
                            >
                                {formatAmount(net?.usd ?? 0)}
                                <span className="ml-1 text-xs font-semibold text-muted-foreground">
                                    USD
                                </span>
                            </p>
                            <p
                                className={`text-sm font-semibold tabular-nums ${
                                    (net?.usdt ?? 0) >= 0
                                        ? 'text-blue-400/80'
                                        : 'text-destructive/80'
                                }`}
                            >
                                {formatAmount(net?.usdt ?? 0)}{' '}
                                <span className="text-[10px] font-medium text-muted-foreground">
                                    USDT
                                </span>
                            </p>
                        </div>
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
            ) : (
                <section className="rounded-xl bg-gradient-to-br from-surface-high to-surface-low p-5 lg:col-span-2">
                    <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('dashboard.total_month', { month })}
                    </p>
                    <div className="mt-2 flex items-baseline gap-2">
                        <span className="font-display text-4xl font-extrabold text-emerald-400 tabular-nums">
                            {formatAmount((totals ?? incomeTotals)?.usd ?? 0)}
                        </span>
                        <span className="text-sm font-semibold text-emerald-400/80">
                            USD
                        </span>
                    </div>
                    <div className="mt-1 flex items-baseline gap-2">
                        <span className="font-display text-2xl font-bold text-blue-400 tabular-nums">
                            {formatAmount((totals ?? incomeTotals)?.usdt ?? 0)}
                        </span>
                        <span className="text-xs font-semibold text-blue-400/80">
                            USDT
                        </span>
                    </div>
                    <div className="mt-4 flex gap-4 border-t border-white/5 pt-3">
                        <div className="flex items-center gap-1.5">
                            <span className="size-2 rounded-full bg-emerald-400" />
                            <span className="text-[11px] text-muted-foreground">
                                Bs{' '}
                                {formatAmount(
                                    (totals ?? incomeTotals)?.byCurrency.ves ??
                                        0,
                                )}
                            </span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className="size-2 rounded-full bg-amber-400" />
                            <span className="text-[11px] text-muted-foreground">
                                USD{' '}
                                {formatAmount(
                                    (totals ?? incomeTotals)?.byCurrency.usd ??
                                        0,
                                )}
                            </span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className="size-2 rounded-full bg-blue-400" />
                            <span className="text-[11px] text-muted-foreground">
                                USDT{' '}
                                {formatAmount(
                                    (totals ?? incomeTotals)?.byCurrency.usdt ??
                                        0,
                                )}
                            </span>
                        </div>
                    </div>
                </section>
            )}

            {showExpenses() && hasActiveBudget && (
                <TrackerCard
                    title={t('dashboard.global_budget')}
                    className="lg:col-span-2"
                >
                    <div className="px-4 pt-4 pb-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                <Wallet className="size-4 text-emerald-400" />
                                {formatAmount(spentForBudget())}{' '}
                                <span className="text-xs font-medium text-muted-foreground">
                                    /
                                </span>{' '}
                                {formatAmount(activeBudget)}{' '}
                                <span className="text-xs font-medium text-muted-foreground">
                                    USD
                                </span>
                            </span>
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-[11px] font-bold tabular-nums ${
                                    budgetOver()
                                        ? 'bg-destructive/15 text-destructive'
                                        : 'bg-emerald-500/15 text-emerald-400'
                                }`}
                            >
                                {budgetPercent().toFixed(0)}%
                            </span>
                        </div>
                        <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-white/5">
                            <div
                                className={`h-full rounded-full transition-all ${
                                    budgetOver()
                                        ? 'bg-gradient-to-r from-red-500 to-red-600'
                                        : budgetPercent() > 80
                                          ? 'bg-gradient-to-r from-amber-400 to-amber-500'
                                          : 'bg-gradient-to-r from-emerald-400 to-emerald-500'
                                }`}
                                style={{ width: `${budgetPercent()}%` }}
                            />
                        </div>
                        <div className="mt-2 flex items-center justify-between">
                            <span className="text-[11px] text-muted-foreground">
                                {budgetRemaining() >= 0
                                    ? t('dashboard.budget_remaining', {
                                          amount: formatAmount(
                                              budgetRemaining(),
                                          ),
                                      })
                                    : t('dashboard.budget_over_amount', {
                                          amount: formatAmount(
                                              Math.abs(budgetRemaining()),
                                          ),
                                      })}
                            </span>
                            <Link
                                href={ajustesUrl()}
                                className="text-[11px] font-semibold text-emerald-400"
                            >
                                {t('dashboard.edit_global_budget')}
                            </Link>
                        </div>
                    </div>
                </TrackerCard>
            )}

            {categories.length > 0 && (
                <TrackerCard title={t('dashboard.by_category')}>
                    <CategoryDonut
                        slices={categories}
                        centerLabel={t('dashboard.categories_center')}
                    />
                </TrackerCard>
            )}

            {incomeCategories.length > 0 && (
                <TrackerCard title={t('dashboard.income_by_category')}>
                    <CategoryDonut
                        slices={incomeCategories}
                        centerLabel={t('dashboard.income_categories_center')}
                    />
                </TrackerCard>
            )}

            {budgets.length > 0 && (
                <TrackerCard title={t('dashboard.budgets_month')}>
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
                                            {t('dashboard.budget_over', {
                                                amount: formatAmount(
                                                    budget.spent -
                                                        budget.budget,
                                                ),
                                            })}
                                        </p>
                                    )}
                                </div>
                            );
                        })}
                        <Link
                            href="/categories"
                            className="flex items-center justify-center gap-1 border-t border-white/5 pt-3 text-xs font-semibold text-emerald-400"
                        >
                            {t('dashboard.edit_budgets')}{' '}
                            <ChevronRight className="size-3.5" />
                        </Link>
                    </div>
                </TrackerCard>
            )}

            {trend.length > 0 && (
                <TrackerCard title={t('dashboard.trend_6m')}>
                    <TrendChart
                        data={trend}
                        color="#10B981"
                        averageLabel={t('dashboard.trend_average', {
                            amount: formatAmount(
                                trend.reduce(
                                    (sum, item) => sum + item.total,
                                    0,
                                ) / trend.length,
                            ),
                        })}
                    />
                </TrackerCard>
            )}

            {incomeTrend.length > 0 && (
                <TrackerCard title={t('dashboard.income_trend_6m')}>
                    <TrendChart
                        data={incomeTrend}
                        color="#60A5FA"
                        averageLabel={t('dashboard.trend_average', {
                            amount: formatAmount(
                                incomeTrend.reduce(
                                    (sum, item) => sum + item.total,
                                    0,
                                ) / incomeTrend.length,
                            ),
                        })}
                    />
                </TrackerCard>
            )}

            {sources.length > 0 && (
                <TrackerCard title={t('dashboard.by_source')}>
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
                                    ? t('dashboard.show_less')
                                    : t('dashboard.show_more', {
                                          count: sources.length - 3,
                                      })}
                            </button>
                        )}
                    </div>
                </TrackerCard>
            )}

            <TrackerCard
                title={t('dashboard.recent')}
                className="lg:col-span-2"
            >
                <div className="space-y-2.5 p-3.5">
                    {recentItems.length === 0 && (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            {t(
                                trackingType === 'expenses'
                                    ? 'dashboard.no_expenses'
                                    : 'dashboard.no_incomes',
                            )}
                        </p>
                    )}
                    {recentItems.map((item, index) =>
                        item.kind === 'expense' && item.expense ? (
                            <ExpenseListItem
                                key={`expense-${item.expense.id}`}
                                expense={item.expense}
                            />
                        ) : item.income ? (
                            <IncomeListItem
                                key={`income-${index}-${item.income.id}`}
                                income={item.income}
                            />
                        ) : null,
                    )}
                </div>
                {recentItems.length > 0 && (
                    <Link
                        href={
                            trackingType === 'expenses'
                                ? '/expenses'
                                : trackingType === 'income'
                                  ? '/incomes'
                                  : '/expenses'
                        }
                        className="flex items-center justify-center gap-1 border-t border-white/5 py-3 text-xs font-semibold text-emerald-400"
                    >
                        {t('dashboard.view_all')}{' '}
                        <ChevronRight className="size-3.5" />
                    </Link>
                )}
            </TrackerCard>

            <div className="flex items-center justify-center gap-1.5 pb-2 text-[10px] text-muted-foreground/70 lg:col-span-2">
                <RefreshCw className="size-3" />
                {t('dashboard.rate_sync')}
            </div>
        </div>
    );
}

function CategoryDonut({
    slices,
    centerLabel,
}: {
    slices: CategorySlice[];
    centerLabel: string;
}) {
    return (
        <div className="flex items-center gap-5 px-4 pt-4 pb-4">
            <DonutChart
                data={slices.map(({ name, color, total }) => ({
                    name,
                    color,
                    total,
                }))}
                centerValue={`${slices.length}`}
                centerLabel={centerLabel}
            />
            <div className="flex-1 space-y-2">
                {slices.slice(0, 4).map((category) => (
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
    );
}

function TrendChart({
    data,
    color,
    averageLabel,
}: {
    data: Trending;
    color: string;
    averageLabel: string;
}) {
    return (
        <div className="px-4 pt-4 pb-4">
            <div className="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <TrendingUp className="size-3.5" style={{ color }} />
                <span>{averageLabel}</span>
            </div>
            <svg
                viewBox="0 0 300 120"
                className="w-full"
                style={{ height: 120 }}
                preserveAspectRatio="none"
            >
                {data.map((item, index, arr) => {
                    const max = Math.max(...arr.map((i) => i.total), 1);
                    const x = (index / Math.max(arr.length - 1, 1)) * 300;
                    const y = 112 - (item.total / max) * 100;
                    const prevX =
                        (Math.max(index - 1, 0) / Math.max(arr.length - 1, 1)) *
                        300;
                    const prevY =
                        112 - (arr[Math.max(index - 1, 0)].total / max) * 100;

                    return (
                        <line
                            key={item.month}
                            x1={prevX}
                            y1={prevY}
                            x2={x}
                            y2={y}
                            stroke={color}
                            strokeWidth="2.5"
                            strokeLinecap="round"
                        />
                    );
                })}
                {data.map((item, index, arr) => {
                    const max = Math.max(...arr.map((i) => i.total), 1);
                    const x = (index / Math.max(arr.length - 1, 1)) * 300;
                    const y = 112 - (item.total / max) * 100;

                    return (
                        <circle
                            key={item.month}
                            cx={x}
                            cy={y}
                            r="3.5"
                            fill="#0B1220"
                            stroke={color}
                            strokeWidth="2"
                        />
                    );
                })}
            </svg>
            <div className="mt-1 flex justify-between px-1">
                {data.map((item) => (
                    <span
                        key={item.month}
                        className="text-[9px] font-medium text-muted-foreground"
                    >
                        {formatIsoMonthLabel(item.month)}
                    </span>
                ))}
            </div>
        </div>
    );
}
