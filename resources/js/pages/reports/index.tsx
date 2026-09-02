import { Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, Download } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { HorizontalBars } from '@/components/tracker/horizontal-bars';
import { TrackerCard } from '@/components/tracker/tracker-card';
import { formatAmount, formatIsoMonthLabel } from '@/lib/format';
import {
    exportMethod as reportsExport,
    index as reportsIndex,
} from '@/routes/reports';

type MonthSeries = {
    month: number;
    usd: number;
    usdt: number;
    byCurrency: Record<'usd' | 'ves' | 'usdt', number>;
    variation: number;
}[];

type BreakdownItem = {
    name: string;
    color: string;
    icon: string;
    total: number;
    percent: number;
}[];

type ReportsProps = {
    year: number;
    years: number[];
    annual: { usd: number; usdt: number };
    incomeAnnual: { usd: number; usdt: number };
    net: { usd: number; usdt: number };
    showIncomes: boolean;
    showExpenses: boolean;
    months: MonthSeries;
    incomeMonths: MonthSeries;
    categories: BreakdownItem;
    incomeCategories: BreakdownItem;
    sources: BreakdownItem;
};

export default function ReportsIndex({
    year,
    years,
    annual,
    incomeAnnual,
    net,
    showIncomes,
    showExpenses,
    months,
    incomeMonths,
    categories,
    incomeCategories,
    sources,
}: ReportsProps) {
    const { t } = useTranslation();
    const maxUsd = Math.max(...months.map((month) => month.usd), 1);
    const maxUsdt = Math.max(...months.map((month) => month.usdt), 1);
    const maxIncomeUsd = Math.max(...incomeMonths.map((month) => month.usd), 1);
    const maxIncomeUsdt = Math.max(
        ...incomeMonths.map((month) => month.usdt),
        1,
    );

    setLayoutProps({ title: t('reports.title') });

    function monthLabel(month: number) {
        return formatIsoMonthLabel(`${year}-${String(month).padStart(2, '0')}`);
    }

    function changeYear(nextYear: number) {
        router.get(
            reportsIndex().url,
            { year: nextYear },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <div className="space-y-5 lg:grid lg:grid-cols-2 lg:gap-5 lg:space-y-0">
            <div className="flex items-center justify-between lg:col-span-2">
                <div className="flex gap-2">
                    {years.map((availableYear) => (
                        <button
                            key={availableYear}
                            type="button"
                            onClick={() => changeYear(availableYear)}
                            className={`rounded-full px-4 py-1.5 text-xs font-semibold transition-colors ${
                                availableYear === year
                                    ? 'bg-emerald-500 text-primary-foreground'
                                    : 'bg-surface-low text-muted-foreground'
                            }`}
                        >
                            {availableYear}
                        </button>
                    ))}
                </div>
                <Link
                    href={reportsExport().url}
                    className="inline-flex items-center gap-1.5 rounded-full bg-surface-low px-3.5 py-2 text-xs font-semibold text-foreground"
                >
                    <Download className="size-3.5" />
                    CSV
                </Link>
            </div>

            <section className="rounded-xl bg-gradient-to-br from-surface-high to-surface-low p-5 lg:col-span-2">
                <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                    {t('reports.annual_total', { year })}
                </p>
                {showExpenses && (
                    <div className="mt-2 flex items-baseline gap-2">
                        <span className="font-display text-4xl font-extrabold text-emerald-400 tabular-nums">
                            {formatAmount(annual.usd)}
                        </span>
                        <span className="text-sm font-semibold text-emerald-400/80">
                            USD
                        </span>
                    </div>
                )}
                {showExpenses && (
                    <div className="mt-1 flex items-baseline gap-2">
                        <span className="font-display text-2xl font-bold text-blue-400 tabular-nums">
                            {formatAmount(annual.usdt)}
                        </span>
                        <span className="text-xs font-semibold text-blue-400/80">
                            USDT
                        </span>
                    </div>
                )}
                {showIncomes && (
                    <div className="flex flex-col gap-2 border-t border-white/5 pt-3 lg:flex-row lg:items-baseline lg:gap-6">
                        <p className="text-[11px] font-semibold tracking-wider text-blue-400 uppercase">
                            {t('reports.income_annual')}
                        </p>
                        <div className="flex items-baseline gap-2">
                            <span className="font-display text-2xl font-extrabold text-blue-400 tabular-nums">
                                {formatAmount(incomeAnnual.usd)}
                            </span>
                            <span className="text-xs font-semibold text-blue-400/70">
                                USD
                            </span>
                        </div>
                        <div className="flex items-baseline gap-2">
                            <span className="font-display text-2xl font-bold text-blue-400/80 tabular-nums">
                                {formatAmount(incomeAnnual.usdt)}
                            </span>
                            <span className="text-xs font-semibold text-blue-400/70">
                                USDT
                            </span>
                        </div>
                    </div>
                )}
                {showIncomes && showExpenses && (
                    <div className="flex flex-col gap-1 border-t border-white/5 pt-3 lg:flex-row lg:items-baseline lg:gap-3">
                        <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                            {t('reports.net_annual')}
                        </p>
                        <span
                            className={`font-display text-2xl font-extrabold tabular-nums ${
                                net.usd >= 0 ? 'text-white' : 'text-destructive'
                            }`}
                        >
                            {formatAmount(net.usd)} USD
                        </span>
                        <span
                            className={`font-display text-lg font-bold tabular-nums ${
                                net.usdt >= 0
                                    ? 'text-blue-400/80'
                                    : 'text-destructive/80'
                            }`}
                        >
                            {formatAmount(net.usdt)} USDT
                        </span>
                    </div>
                )}
            </section>

            {showExpenses && (
                <TrackerCard title={t('reports.monthly_comparison')}>
                    <MonthlyChart
                        months={months}
                        maxUsd={maxUsd}
                        maxUsdt={maxUsdt}
                        monthLabel={monthLabel}
                        legend={[
                            { color: 'bg-emerald-400', label: 'USD' },
                            { color: 'bg-blue-500', label: 'USDT' },
                        ]}
                    />
                </TrackerCard>
            )}

            {showIncomes && (
                <TrackerCard title={t('reports.income_monthly')}>
                    <MonthlyChart
                        months={incomeMonths}
                        maxUsd={maxIncomeUsd}
                        maxUsdt={maxIncomeUsdt}
                        monthLabel={monthLabel}
                        legend={[
                            { color: 'bg-blue-400', label: 'USD' },
                            { color: 'bg-sky-500', label: 'USDT' },
                        ]}
                    />
                </TrackerCard>
            )}

            {showExpenses && (
                <TrackerCard title={t('reports.vs_previous')}>
                    <VsPrevious months={months} monthLabel={monthLabel} />
                </TrackerCard>
            )}

            {showIncomes && (
                <TrackerCard title={t('reports.income_vs_previous')}>
                    <VsPrevious months={incomeMonths} monthLabel={monthLabel} />
                </TrackerCard>
            )}

            {showExpenses && categories.length > 0 && (
                <TrackerCard title={t('reports.by_category')}>
                    <div className="px-4 pt-4 pb-4">
                        <HorizontalBars data={categories} />
                    </div>
                </TrackerCard>
            )}

            {showIncomes && incomeCategories.length > 0 && (
                <TrackerCard title={t('reports.income_by_category')}>
                    <div className="px-4 pt-4 pb-4">
                        <HorizontalBars data={incomeCategories} />
                    </div>
                </TrackerCard>
            )}

            {showExpenses && sources.length > 0 && (
                <TrackerCard title={t('reports.by_source')}>
                    <div className="px-4 pt-4 pb-4">
                        <HorizontalBars data={sources} />
                    </div>
                </TrackerCard>
            )}
        </div>
    );
}

function MonthlyChart({
    months,
    maxUsd,
    maxUsdt,
    monthLabel,
    legend,
}: {
    months: MonthSeries;
    maxUsd: number;
    maxUsdt: number;
    monthLabel: (month: number) => string;
    legend: { color: string; label: string }[];
}) {
    return (
        <div className="px-4 pt-4 pb-4">
            <div className="flex h-40 items-end gap-1.5">
                {months.map((month) => (
                    <div
                        key={month.month}
                        className="flex flex-1 flex-col items-center gap-1.5"
                    >
                        <div className="flex w-full flex-1 flex-col justify-end gap-1">
                            <div
                                className="w-full rounded-t-sm transition-all duration-500"
                                style={{
                                    height: `${(month.usdt / Math.max(maxUsdt, 1)) * 100}%`,
                                    opacity: month.usdt > 0 ? 1 : 0.15,
                                    backgroundColor: legend[1].color.includes(
                                        'sky',
                                    )
                                        ? '#0EA5E9'
                                        : '#3B82F6',
                                }}
                            />
                            <div
                                className="w-full rounded-t-sm transition-all duration-500"
                                style={{
                                    height: `${(month.usd / maxUsd) * 100}%`,
                                    opacity: month.usd > 0 ? 1 : 0.15,
                                    backgroundColor: legend[0].color.includes(
                                        'blue',
                                    )
                                        ? '#60A5FA'
                                        : '#34D399',
                                }}
                            />
                        </div>
                    </div>
                ))}
            </div>
            <div className="mt-2 flex justify-between gap-1.5">
                {months.map((month) => (
                    <span
                        key={month.month}
                        className="flex-1 text-center text-[9px] font-medium text-muted-foreground"
                    >
                        {monthLabel(month.month)}
                    </span>
                ))}
            </div>
            <div className="mt-3 flex items-center justify-center gap-4 border-t border-white/5 pt-3 text-[11px] text-muted-foreground">
                {legend.map((item) => (
                    <span
                        key={item.label}
                        className="flex items-center gap-1.5"
                    >
                        <span className={`size-2 rounded-full ${item.color}`} />{' '}
                        {item.label}
                    </span>
                ))}
            </div>
        </div>
    );
}

function VsPrevious({
    months,
    monthLabel,
}: {
    months: MonthSeries;
    monthLabel: (month: number) => string;
}) {
    return (
        <div className="divide-y divide-white/5 px-4">
            {months
                .filter((month) => month.usd > 0 || month.usdt > 0)
                .slice(-6)
                .map((month) => (
                    <div
                        key={month.month}
                        className="flex items-center justify-between py-3"
                    >
                        <span className="text-sm font-medium text-foreground">
                            {monthLabel(month.month)}
                        </span>
                        <span className="flex items-center gap-1 text-sm font-medium text-foreground">
                            {formatAmount(month.usd)} USD
                        </span>
                        <span
                            className={`inline-flex items-center gap-1 text-xs font-semibold ${
                                month.variation <= 0
                                    ? 'text-emerald-400'
                                    : 'text-destructive'
                            }`}
                        >
                            {month.variation <= 0 ? (
                                <ArrowDownRight className="size-3.5" />
                            ) : (
                                <ArrowUpRight className="size-3.5" />
                            )}
                            {Math.abs(month.variation)}%
                        </span>
                    </div>
                ))}
        </div>
    );
}
