import { Link, router } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, Download } from 'lucide-react';
import { HorizontalBars } from '@/components/tracker/horizontal-bars';
import { TrackerCard } from '@/components/tracker/tracker-card';
import { formatAmount } from '@/lib/format';
import {
    exportMethod as reportsExport,
    index as reportsIndex,
} from '@/routes/reports';

type ReportsProps = {
    year: number;
    years: number[];
    annual: { usd: number; usdt: number };
    months: {
        month: number;
        label: string;
        usd: number;
        usdt: number;
        byCurrency: Record<'usd' | 'ves' | 'usdt', number>;
        variation: number;
    }[];
    categories: {
        name: string;
        color: string;
        icon: string;
        total: number;
        percent: number;
    }[];
    sources: {
        name: string;
        color: string;
        icon: string;
        total: number;
        percent: number;
    }[];
};

const MONTH_LABELS = [
    'Ene',
    'Feb',
    'Mar',
    'Abr',
    'May',
    'Jun',
    'Jul',
    'Ago',
    'Sep',
    'Oct',
    'Nov',
    'Dic',
];

export default function ReportsIndex({
    year,
    years,
    annual,
    months,
    categories,
    sources,
}: ReportsProps) {
    const maxUsd = Math.max(...months.map((month) => month.usd), 1);
    const maxUsdt = Math.max(...months.map((month) => month.usdt), 1);

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
                    Total anual {year}
                </p>
                <div className="mt-2 flex items-baseline gap-2">
                    <span className="font-display text-4xl font-extrabold text-emerald-400 tabular-nums">
                        {formatAmount(annual.usd)}
                    </span>
                    <span className="text-sm font-semibold text-emerald-400/80">
                        USD
                    </span>
                </div>
                <div className="mt-1 flex items-baseline gap-2">
                    <span className="font-display text-2xl font-bold text-blue-400 tabular-nums">
                        {formatAmount(annual.usdt)}
                    </span>
                    <span className="text-xs font-semibold text-blue-400/80">
                        USDT
                    </span>
                </div>
            </section>

            <TrackerCard title="Comparativo mensual">
                <div className="px-4 pt-4 pb-4">
                    <div className="flex h-40 items-end gap-1.5">
                        {months.map((month) => (
                            <div
                                key={month.month}
                                className="flex flex-1 flex-col items-center gap-1.5"
                            >
                                <div className="flex w-full flex-1 flex-col justify-end gap-1">
                                    <div
                                        className="w-full rounded-t-sm bg-blue-500/80 transition-all duration-500"
                                        style={{
                                            height: `${(month.usdt / Math.max(maxUsdt, 1)) * 100}%`,
                                            opacity: month.usdt > 0 ? 1 : 0.15,
                                        }}
                                    />
                                    <div
                                        className="w-full rounded-t-sm bg-emerald-400 transition-all duration-500"
                                        style={{
                                            height: `${(month.usd / maxUsd) * 100}%`,
                                            opacity: month.usd > 0 ? 1 : 0.15,
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
                                {MONTH_LABELS[month.month - 1]}
                            </span>
                        ))}
                    </div>
                    <div className="mt-3 flex items-center justify-center gap-4 border-t border-white/5 pt-3 text-[11px] text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <span className="size-2 rounded-full bg-emerald-400" />{' '}
                            USD
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="size-2 rounded-full bg-blue-500" />{' '}
                            USDT
                        </span>
                    </div>
                </div>
            </TrackerCard>

            <TrackerCard title="Variación vs mes anterior">
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
                                    {MONTH_LABELS[month.month - 1]}
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
            </TrackerCard>

            {categories.length > 0 && (
                <TrackerCard title="Gasto por categoría">
                    <div className="px-4 pt-4 pb-4">
                        <HorizontalBars data={categories} />
                    </div>
                </TrackerCard>
            )}

            {sources.length > 0 && (
                <TrackerCard title="Gasto por origen">
                    <div className="px-4 pt-4 pb-4">
                        <HorizontalBars data={sources} />
                    </div>
                </TrackerCard>
            )}
        </div>
    );
}
