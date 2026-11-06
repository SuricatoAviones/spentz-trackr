import {
    Link,
    router,
    setLayoutProps,
    useHttp,
    usePage,
} from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    Download,
    Loader2,
    Printer,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Markdown } from '@/components/ai/markdown';
import { HorizontalBars } from '@/components/tracker/horizontal-bars';
import { TrackerCard } from '@/components/tracker/tracker-card';
import { aiFailure, isCancelled } from '@/lib/ai';
import { CURRENCY_META, formatAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { edit as aiSettingsEdit } from '@/routes/ai/settings';
import { analyze as statementAnalyze } from '@/routes/ai/statement';
import { statement as reportsStatement } from '@/routes/reports';
import { exportMethod as statementExport } from '@/routes/reports/statement';
import type { AccountStatement, StatementBreakdownRow } from '@/types';

type Preset = 'this_month' | 'last_month' | 'last_30' | 'this_year';

/** Fecha local en Y-m-d (toISOString usaría UTC y podría cambiar el día). */
function localIso(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

function presetRange(preset: Preset): { from: string; to: string } {
    const today = new Date();

    switch (preset) {
        case 'last_month':
            return {
                from: localIso(
                    new Date(today.getFullYear(), today.getMonth() - 1, 1),
                ),
                to: localIso(
                    new Date(today.getFullYear(), today.getMonth(), 0),
                ),
            };
        case 'last_30': {
            const from = new Date(today);
            from.setDate(from.getDate() - 29);

            return { from: localIso(from), to: localIso(today) };
        }
        case 'this_year':
            return {
                from: localIso(new Date(today.getFullYear(), 0, 1)),
                to: localIso(today),
            };
        default:
            return {
                from: localIso(
                    new Date(today.getFullYear(), today.getMonth(), 1),
                ),
                to: localIso(today),
            };
    }
}

function asBars(rows: StatementBreakdownRow[]) {
    return rows.map((row) => ({
        name: row.name,
        color: row.color,
        total: row.total_usd,
        percent: row.percent,
    }));
}

export default function Statement({
    statement,
    aiAvailable,
}: {
    statement: AccountStatement;
    aiAvailable: boolean;
}) {
    const { t } = useTranslation();
    const { features, errors } = usePage<{
        features: { ai?: boolean };
        errors: Record<string, string>;
    }>().props;
    const [from, setFrom] = useState(statement.period.from);
    const [to, setTo] = useState(statement.period.to);
    const [analysis, setAnalysis] = useState<string | null>(null);
    const [analysisError, setAnalysisError] = useState<string | null>(null);
    const analyzer = useHttp<
        { from: string; to: string },
        { data: { analysis: string } }
    >({ from: statement.period.from, to: statement.period.to });

    setLayoutProps({
        title: t('statement.title'),
        description: t('statement.period', {
            from: formatDate(statement.period.from),
            to: formatDate(statement.period.to),
        }),
    });

    const { totals, tracking, previous } = statement;
    const query = { from: statement.period.from, to: statement.period.to };

    function load(range: { from: string; to: string }): void {
        setFrom(range.from);
        setTo(range.to);
        setAnalysis(null);
        setAnalysisError(null);
        router.get(reportsStatement().url, range, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    async function analyze(): Promise<void> {
        setAnalysisError(null);
        analyzer.setData(query);

        try {
            const response = await analyzer.post(statementAnalyze().url);

            if (response) {
                setAnalysis(response.data.analysis);
            }
        } catch (failure) {
            if (!isCancelled(failure)) {
                setAnalysisError(
                    aiFailure(failure, t('statement.ai_error')).message,
                );
            }
        }
    }

    return (
        <div className="space-y-4 lg:space-y-5">
            <section className="space-y-3 rounded-xl bg-surface-low p-4 print:hidden">
                <div className="flex flex-wrap gap-2">
                    {(
                        [
                            'this_month',
                            'last_month',
                            'last_30',
                            'this_year',
                        ] as const
                    ).map((preset) => (
                        <button
                            key={preset}
                            type="button"
                            onClick={() => load(presetRange(preset))}
                            className="rounded-full bg-surface-high px-3.5 py-1.5 text-xs font-semibold text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {t(`statement.preset_${preset}`)}
                        </button>
                    ))}
                </div>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        load({ from, to });
                    }}
                    className="flex flex-wrap items-end gap-2"
                >
                    <label className="block">
                        <span className="text-[11px] font-medium text-muted-foreground">
                            {t('statement.from')}
                        </span>
                        <input
                            type="date"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                            className="mt-1 h-10 rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                        />
                    </label>
                    <label className="block">
                        <span className="text-[11px] font-medium text-muted-foreground">
                            {t('statement.to')}
                        </span>
                        <input
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                            className="mt-1 h-10 rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                        />
                    </label>
                    <button
                        type="submit"
                        className="h-10 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 text-xs font-bold text-primary-foreground"
                    >
                        {t('statement.apply')}
                    </button>
                    <div className="ml-auto flex gap-2">
                        <a
                            href={statementExport({ query }).url}
                            className="inline-flex h-10 items-center gap-1.5 rounded-lg bg-surface-high px-3 text-xs font-semibold text-foreground"
                        >
                            <Download className="size-3.5" />
                            CSV
                        </a>
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="inline-flex h-10 items-center gap-1.5 rounded-lg bg-surface-high px-3 text-xs font-semibold text-foreground"
                        >
                            <Printer className="size-3.5" />
                            {t('statement.print')}
                        </button>
                    </div>
                </form>
                {(errors.to || errors.from) && (
                    <p className="text-xs text-destructive">
                        {errors.to ?? errors.from}
                    </p>
                )}
            </section>

            <section className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {tracking.incomes && (
                    <SummaryTile
                        label={t('statement.incomes')}
                        value={totals.incomes.usd}
                        detail={t('statement.count', {
                            count: totals.incomes.count,
                        })}
                        tone="blue"
                        variation={previous.incomes_variation}
                    />
                )}
                {tracking.expenses && (
                    <SummaryTile
                        label={t('statement.expenses')}
                        value={totals.expenses.usd}
                        detail={t('statement.count', {
                            count: totals.expenses.count,
                        })}
                        tone="emerald"
                        variation={previous.expenses_variation}
                        invertVariation
                    />
                )}
                {tracking.incomes && tracking.expenses && (
                    <SummaryTile
                        label={t('statement.net')}
                        value={totals.net.usd}
                        detail={
                            totals.savings_rate !== null
                                ? t('statement.savings_rate', {
                                      rate: totals.savings_rate,
                                  })
                                : t('statement.no_incomes')
                        }
                        tone={totals.net.usd >= 0 ? 'neutral' : 'red'}
                    />
                )}
                {tracking.expenses && (
                    <SummaryTile
                        label={t('statement.daily_average')}
                        value={totals.daily_average_expense_usd}
                        detail={t('statement.days', {
                            count: statement.period.days,
                        })}
                        tone="neutral"
                    />
                )}
            </section>

            {tracking.expenses && (
                <p className="text-[11px] text-muted-foreground">
                    {t('statement.original_currencies', {
                        usd: formatAmount(statement.by_currency.expenses.usd),
                        ves: formatAmount(statement.by_currency.expenses.ves),
                        usdt: formatAmount(statement.by_currency.expenses.usdt),
                    })}
                    {totals.expenses.commissions_ves > 0 &&
                        ` ${t('statement.commissions', {
                            amount: formatAmount(
                                totals.expenses.commissions_ves,
                            ),
                        })}`}
                </p>
            )}

            {statement.budget && (
                <TrackerCard title={t('statement.budget_title')}>
                    <div className="px-4 pt-3 pb-4">
                        <div className="flex items-baseline justify-between text-xs">
                            <span className="text-muted-foreground">
                                {t('statement.budget_spent', {
                                    spent: formatAmount(statement.budget.spent),
                                    budget: formatAmount(
                                        statement.budget.monthly,
                                    ),
                                })}
                            </span>
                            <span
                                className={cn(
                                    'font-semibold tabular-nums',
                                    statement.budget.percent > 100
                                        ? 'text-destructive'
                                        : 'text-emerald-600 dark:text-emerald-400',
                                )}
                            >
                                {statement.budget.percent}%
                            </span>
                        </div>
                        <div className="mt-2 h-2.5 overflow-hidden rounded-full bg-muted">
                            <div
                                className={cn(
                                    'h-full rounded-full',
                                    statement.budget.percent > 100
                                        ? 'bg-destructive'
                                        : 'bg-emerald-500',
                                )}
                                style={{
                                    width: `${Math.min(statement.budget.percent, 100)}%`,
                                }}
                            />
                        </div>
                    </div>
                </TrackerCard>
            )}

            {features.ai && (
                <TrackerCard
                    title={t('statement.ai_title')}
                    className="print:hidden"
                >
                    <div className="px-4 pt-3 pb-4">
                        {analysis ? (
                            <Markdown content={analysis} />
                        ) : aiAvailable ? (
                            <>
                                <p className="text-xs text-muted-foreground">
                                    {t('statement.ai_desc')}
                                </p>
                                <button
                                    type="button"
                                    onClick={() => void analyze()}
                                    disabled={analyzer.processing}
                                    className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-br from-violet-500 to-violet-700 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-60"
                                >
                                    {analyzer.processing ? (
                                        <Loader2 className="size-3.5 animate-spin" />
                                    ) : (
                                        <Sparkles className="size-3.5" />
                                    )}
                                    {analyzer.processing
                                        ? t('statement.ai_running')
                                        : t('statement.ai_button')}
                                </button>
                            </>
                        ) : (
                            <Link
                                href={aiSettingsEdit().url}
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-violet-600 dark:text-violet-400"
                            >
                                <Sparkles className="size-3.5" />
                                {t('statement.ai_setup')}
                            </Link>
                        )}
                        {analysisError && (
                            <p className="mt-2 text-xs text-destructive">
                                {analysisError}
                            </p>
                        )}
                    </div>
                </TrackerCard>
            )}

            <div className="grid gap-4 lg:grid-cols-2 lg:gap-5">
                {tracking.expenses &&
                    statement.expense_categories.length > 0 && (
                        <TrackerCard title={t('statement.expense_categories')}>
                            <div className="px-4 pt-3 pb-4">
                                <HorizontalBars
                                    data={asBars(statement.expense_categories)}
                                />
                            </div>
                        </TrackerCard>
                    )}
                {tracking.expenses && statement.sources.length > 0 && (
                    <TrackerCard title={t('statement.sources')}>
                        <div className="px-4 pt-3 pb-4">
                            <HorizontalBars data={asBars(statement.sources)} />
                        </div>
                    </TrackerCard>
                )}
                {tracking.incomes && statement.income_categories.length > 0 && (
                    <TrackerCard title={t('statement.income_categories')}>
                        <div className="px-4 pt-3 pb-4">
                            <HorizontalBars
                                data={asBars(statement.income_categories)}
                            />
                        </div>
                    </TrackerCard>
                )}
                {statement.largest_expenses.length > 0 && (
                    <TrackerCard title={t('statement.largest')}>
                        <ul className="divide-y divide-border px-4 pb-2">
                            {statement.largest_expenses.map((expense) => (
                                <li
                                    key={expense.id}
                                    className="flex items-center justify-between gap-3 py-2.5"
                                >
                                    <span className="min-w-0">
                                        <span className="block truncate text-sm text-foreground">
                                            {expense.description}
                                        </span>
                                        <span className="block text-[11px] text-muted-foreground">
                                            {[
                                                expense.category,
                                                formatDate(expense.date),
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-sm font-semibold tabular-nums">
                                        ${formatAmount(expense.usd_amount)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </TrackerCard>
                )}
            </div>

            <TrackerCard title={t('statement.movements')}>
                {statement.movements.length === 0 ? (
                    <p className="px-4 pt-3 pb-4 text-sm text-muted-foreground">
                        {t('statement.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto px-2 pt-2 pb-3">
                        <table className="w-full min-w-[640px] text-left text-xs">
                            <thead className="text-[10px] tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-2 py-2 font-semibold">
                                        {t('statement.col_date')}
                                    </th>
                                    <th className="px-2 py-2 font-semibold">
                                        {t('statement.col_description')}
                                    </th>
                                    <th className="px-2 py-2 text-right font-semibold">
                                        {t('statement.col_amount')}
                                    </th>
                                    <th className="px-2 py-2 text-right font-semibold">
                                        USD
                                    </th>
                                    <th className="px-2 py-2 text-right font-semibold">
                                        {t('statement.col_running')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {statement.movements.map((movement) => {
                                    const income = movement.type === 'income';

                                    return (
                                        <tr
                                            key={`${movement.type}-${movement.id}`}
                                        >
                                            <td className="px-2 py-2 whitespace-nowrap text-muted-foreground">
                                                {formatDate(movement.date)}
                                            </td>
                                            <td className="px-2 py-2">
                                                <span className="block text-foreground">
                                                    {movement.description}
                                                </span>
                                                <span className="block text-[10px] text-muted-foreground">
                                                    {[
                                                        movement.category,
                                                        movement.source,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </td>
                                            <td className="px-2 py-2 text-right whitespace-nowrap text-muted-foreground tabular-nums">
                                                {formatAmount(movement.amount)}{' '}
                                                {
                                                    CURRENCY_META[
                                                        movement.currency
                                                    ].label
                                                }
                                            </td>
                                            <td
                                                className={cn(
                                                    'px-2 py-2 text-right font-semibold whitespace-nowrap tabular-nums',
                                                    income
                                                        ? 'text-blue-600 dark:text-blue-400'
                                                        : 'text-foreground',
                                                )}
                                            >
                                                {income ? '+' : '−'}
                                                {formatAmount(
                                                    movement.usd_amount,
                                                )}
                                            </td>
                                            <td
                                                className={cn(
                                                    'px-2 py-2 text-right whitespace-nowrap tabular-nums',
                                                    movement.running_usd < 0
                                                        ? 'text-destructive'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {formatAmount(
                                                    movement.running_usd,
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        {statement.movements_truncated && (
                            <p className="px-2 pt-2 text-[11px] text-muted-foreground">
                                {t('statement.truncated')}
                            </p>
                        )}
                    </div>
                )}
            </TrackerCard>

            <p className="text-[11px] text-muted-foreground">
                {t('statement.footnote')}
            </p>
        </div>
    );
}

function SummaryTile({
    label,
    value,
    detail,
    tone,
    variation = null,
    invertVariation = false,
}: {
    label: string;
    value: number;
    detail: string;
    tone: 'emerald' | 'blue' | 'neutral' | 'red';
    variation?: number | null;
    invertVariation?: boolean;
}) {
    const { t } = useTranslation();
    const up = variation !== null && variation > 0;
    // Más gasto es malo; más ingreso es bueno.
    const good = invertVariation ? !up : up;

    return (
        <section className="rounded-xl bg-surface-low p-4">
            <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                {label}
            </p>
            <p
                className={cn(
                    'mt-1 font-display text-2xl font-extrabold tabular-nums',
                    tone === 'emerald' &&
                        'text-emerald-600 dark:text-emerald-400',
                    tone === 'blue' && 'text-blue-600 dark:text-blue-400',
                    tone === 'red' && 'text-destructive',
                    tone === 'neutral' && 'text-foreground',
                )}
            >
                ${formatAmount(value)}
            </p>
            <p className="mt-0.5 text-[11px] text-muted-foreground">{detail}</p>
            {variation !== null && (
                <p
                    className={cn(
                        'mt-1 inline-flex items-center gap-0.5 text-[11px] font-semibold',
                        good
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-destructive',
                    )}
                >
                    {up ? (
                        <ArrowUpRight className="size-3" />
                    ) : (
                        <ArrowDownRight className="size-3" />
                    )}
                    {t('statement.vs_previous', {
                        value: Math.abs(variation),
                    })}
                </p>
            )}
        </section>
    );
}
