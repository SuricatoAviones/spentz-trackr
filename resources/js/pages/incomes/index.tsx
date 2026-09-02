import { Link, router, setLayoutProps } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Search,
    SlidersHorizontal,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CurrencyChip } from '@/components/tracker/currency-chip';
import { IncomeListItem } from '@/components/tracker/income-list-item';
import { TrackerCard } from '@/components/tracker/tracker-card';
import {
    formatAmount,
    formatDate,
    formatMonthLabel,
    isToday,
    isYesterday,
} from '@/lib/format';
import {
    destroy as incomesDestroy,
    index as incomesIndex,
} from '@/routes/incomes';
import type { CategoryOption, Income } from '@/types';

type IncomesIndexProps = {
    incomes: {
        data: Income[];
        links: { url: string | null; label: string; active: boolean }[];
        next_page_url: string | null;
        prev_page_url: string | null;
        total: number;
    };
    filters: {
        search: string;
        currency: string;
        category_id: string | number;
        from: string;
        to: string;
    };
    totals: {
        usd: number;
        usdt: number;
        byCurrency: Record<'usd' | 'ves' | 'usdt', number>;
    };
    categories: CategoryOption[];
};

export default function IncomesIndex({
    incomes,
    filters,
    totals,
    categories,
}: IncomesIndexProps) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');

    setLayoutProps({ title: t('incomes.title') });
    const [currency, setCurrency] = useState(filters.currency ?? '');
    const [categoryId, setCategoryId] = useState(
        filters.category_id ? Number(filters.category_id) : 0,
    );
    const [showFilters, setShowFilters] = useState(
        Boolean(currency || categoryId || filters.from || filters.to),
    );

    const activeFilterCount = (currency ? 1 : 0) + (categoryId ? 1 : 0);

    function applyFilters() {
        router.get(
            incomesIndex().url,
            {
                search: search || undefined,
                currency: currency || undefined,
                category_id: categoryId || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function clearFilters() {
        setSearch('');
        setCurrency('');
        setCategoryId(0);
        router.get(
            incomesIndex().url,
            {},
            { preserveState: true, preserveScroll: true },
        );
    }

    const grouped = incomes.data.reduce<Record<string, Income[]>>(
        (acc, income) => {
            (acc[income.received_at] ??= []).push(income);

            return acc;
        },
        {},
    );

    const groupLabel = (date: string): string => {
        if (isToday(date)) {
            return t('incomes.today');
        }

        if (isYesterday(date)) {
            return t('incomes.yesterday');
        }

        return formatMonthLabel(date).replace(',', '');
    };

    return (
        <div className="space-y-4">
            <TrackerCard>
                <div className="px-4 py-3">
                    <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('incomes.this_month')}
                    </p>
                    <div className="mt-1.5 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <span className="font-display text-2xl font-extrabold text-blue-400 tabular-nums">
                            {formatAmount(totals.usd)} USD
                        </span>
                        <span className="font-display text-lg font-bold text-sky-400 tabular-nums">
                            {formatAmount(totals.usdt)} USDT
                        </span>
                    </div>
                    <div className="mt-2 flex gap-3 text-[11px] text-muted-foreground">
                        <span>USD {formatAmount(totals.byCurrency.usd)}</span>
                        <span>Bs {formatAmount(totals.byCurrency.ves)}</span>
                        <span>USDT {formatAmount(totals.byCurrency.usdt)}</span>
                    </div>
                </div>
            </TrackerCard>

            <div className="space-y-3 lg:flex lg:items-center lg:gap-3 lg:space-y-0">
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) =>
                            event.key === 'Enter' && applyFilters()
                        }
                        placeholder={t('incomes.search_placeholder')}
                        className="h-11 w-full rounded-xl bg-surface-low pr-10 pl-10 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-blue-500/50 focus:outline-none"
                        aria-label={t('incomes.search_aria')}
                    />
                    {search && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                router.get(
                                    incomesIndex().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                    },
                                );
                            }}
                            className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground"
                            aria-label={t('incomes.clear_search_aria')}
                        >
                            <X className="size-4" />
                        </button>
                    )}
                </div>

                <div className="flex items-center justify-between lg:justify-start lg:gap-3">
                    <button
                        type="button"
                        onClick={() => setShowFilters((value) => !value)}
                        className="relative inline-flex items-center gap-2 rounded-full bg-surface-low px-3.5 py-2 text-xs font-semibold text-foreground"
                    >
                        <SlidersHorizontal className="size-3.5" />
                        {t('incomes.filters')}
                        {activeFilterCount > 0 && (
                            <span className="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-blue-500 text-[9px] font-bold text-primary-foreground">
                                {activeFilterCount}
                            </span>
                        )}
                    </button>

                    {activeFilterCount > 0 && (
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="text-xs font-medium text-muted-foreground hover:text-foreground"
                        >
                            {t('incomes.clear_filters')}
                        </button>
                    )}
                </div>
            </div>

            {showFilters && (
                <div className="space-y-3 rounded-xl bg-surface-low p-4">
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('incomes.currency')}
                            </span>
                            <select
                                value={currency}
                                onChange={(event) => {
                                    setCurrency(event.target.value);
                                    router.get(
                                        incomesIndex().url,
                                        {
                                            currency:
                                                event.target.value || undefined,
                                        },
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                                className="h-10 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-blue-500/50 focus:outline-none"
                            >
                                <option value="">{t('incomes.all')}</option>
                                <option value="usd">USD</option>
                                <option value="ves">Bs</option>
                                <option value="usdt">USDT</option>
                            </select>
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('incomes.category')}
                            </span>
                            <select
                                value={categoryId}
                                onChange={(event) => {
                                    setCategoryId(Number(event.target.value));
                                    router.get(
                                        incomesIndex().url,
                                        {
                                            category_id:
                                                event.target.value || undefined,
                                        },
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                                className="h-10 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-blue-500/50 focus:outline-none"
                            >
                                <option value={0}>{t('incomes.all')}</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>

                    <button
                        type="button"
                        onClick={applyFilters}
                        className="w-full rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 py-2.5 text-sm font-semibold text-primary-foreground lg:w-auto lg:px-8"
                    >
                        {t('incomes.apply_filters')}
                    </button>
                </div>
            )}

            {activeFilterCount > 0 && (
                <div className="flex flex-wrap gap-2">
                    {currency && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/15 px-3 py-1 text-xs font-semibold text-amber-400">
                            {currency.toUpperCase()}
                            <button
                                type="button"
                                onClick={() => {
                                    setCurrency('');
                                    router.get(
                                        incomesIndex().url,
                                        { currency: undefined },
                                        { preserveState: true },
                                    );
                                }}
                                aria-label={t('incomes.remove_currency_filter')}
                            >
                                <X className="size-3" />
                            </button>
                        </span>
                    )}
                    {categoryId > 0 && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-500/15 px-3 py-1 text-xs font-semibold text-blue-400">
                            {
                                categories.find(
                                    (category) => category.id === categoryId,
                                )?.name
                            }
                            <button
                                type="button"
                                onClick={() => {
                                    setCategoryId(0);
                                    router.get(
                                        incomesIndex().url,
                                        { category_id: undefined },
                                        { preserveState: true },
                                    );
                                }}
                                aria-label={t('incomes.remove_category_filter')}
                            >
                                <X className="size-3" />
                            </button>
                        </span>
                    )}
                </div>
            )}

            {Object.keys(grouped).length === 0 ? (
                <div className="rounded-xl bg-surface-low py-12 text-center">
                    <p className="text-sm font-medium text-foreground">
                        {t('incomes.no_results')}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {t('incomes.no_results_hint')}
                    </p>
                </div>
            ) : (
                <>
                    <div className="hidden lg:block">
                        <table className="w-full border-separate border-spacing-y-2">
                            <thead>
                                <tr className="text-left text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                                    <th className="px-4 py-1 font-bold">
                                        {t('incomes.col_date')}
                                    </th>
                                    <th className="px-4 py-1 font-bold">
                                        {t('incomes.col_income')}
                                    </th>
                                    <th className="px-4 py-1 font-bold">
                                        {t('incomes.col_currency')}
                                    </th>
                                    <th className="px-4 py-1 text-right font-bold">
                                        {t('incomes.col_amount')}
                                    </th>
                                    <th className="w-12 px-2 py-1" />
                                </tr>
                            </thead>
                            <tbody>
                                {incomes.data.map((income) => (
                                    <tr
                                        key={income.id}
                                        className="bg-surface-low"
                                    >
                                        <td className="px-4 py-3 text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                                            {formatDate(income.received_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/incomes/${income.id}`}
                                                className="block max-w-xs truncate text-sm font-semibold text-foreground hover:text-blue-400"
                                            >
                                                {income.description}
                                            </Link>
                                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <span
                                                    className="size-1.5 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            income.category
                                                                .color,
                                                    }}
                                                />
                                                {income.category.name}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <CurrencyChip
                                                currency={income.currency}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-right font-display text-sm font-bold text-blue-400 tabular-nums">
                                            {formatAmount(income.amount)}
                                        </td>
                                        <td className="rounded-r-xl px-2 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            t(
                                                                'incomes.delete_confirm',
                                                                {
                                                                    description:
                                                                        income.description,
                                                                },
                                                            ),
                                                        )
                                                    ) {
                                                        router.delete(
                                                            incomesDestroy(
                                                                income.id,
                                                            ).url,
                                                        );
                                                    }
                                                }}
                                                className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-destructive/15 hover:text-destructive"
                                                aria-label={t(
                                                    'incomes.delete_aria',
                                                    {
                                                        description:
                                                            income.description,
                                                    },
                                                )}
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="space-y-4 lg:hidden">
                        {Object.entries(grouped).map(([date, items]) => (
                            <div key={date}>
                                <p className="mb-2 px-1 text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                                    {groupLabel(date)} ·{' '}
                                    {formatMonthLabel(date)}
                                </p>
                                <div className="space-y-2.5">
                                    {items.map((income) => (
                                        <IncomeListItem
                                            key={income.id}
                                            income={income}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}

            {incomes.total > 20 && (
                <div className="flex items-center justify-center gap-3 pt-2">
                    <button
                        type="button"
                        disabled={!incomes.prev_page_url}
                        onClick={() =>
                            incomes.prev_page_url &&
                            router.get(incomes.prev_page_url)
                        }
                        className="inline-flex size-9 items-center justify-center rounded-lg bg-surface-low text-foreground disabled:opacity-40"
                        aria-label={t('incomes.prev_page')}
                    >
                        <ChevronLeft className="size-4" />
                    </button>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {t('incomes.total_count', { count: incomes.total })}
                    </span>
                    <button
                        type="button"
                        disabled={!incomes.next_page_url}
                        onClick={() =>
                            incomes.next_page_url &&
                            router.get(incomes.next_page_url)
                        }
                        className="inline-flex size-9 items-center justify-center rounded-lg bg-surface-low text-foreground disabled:opacity-40"
                        aria-label={t('incomes.next_page')}
                    >
                        <ChevronRight className="size-4" />
                    </button>
                </div>
            )}
        </div>
    );
}
