import { Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    Search,
    SlidersHorizontal,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { CurrencyChip } from '@/components/tracker/currency-chip';
import { ExpenseListItem } from '@/components/tracker/expense-list-item';
import { TrackerCard } from '@/components/tracker/tracker-card';
import {
    formatAmount,
    formatDate,
    formatMonthLabel,
    isToday,
    isYesterday,
} from '@/lib/format';
import { destroy as expensesDestroy, index as expensesIndex } from '@/routes/expenses';
import { exportMethod as reportsExport } from '@/routes/reports';
import type { CategoryOption, Expense, SourceOption } from '@/types';

type ExpensesIndexProps = {
    expenses: {
        data: Expense[];
        links: { url: string | null; label: string; active: boolean }[];
        next_page_url: string | null;
        prev_page_url: string | null;
        total: number;
    };
    filters: {
        search: string;
        currency: string;
        category_id: string | number;
        payment_source_id: string | number;
        from: string;
        to: string;
    };
    totals: {
        usd: number;
        usdt: number;
        byCurrency: Record<'usd' | 'ves' | 'usdt', number>;
    };
    categories: CategoryOption[];
    sources: SourceOption[];
};

export default function ExpensesIndex({
    expenses,
    filters,
    totals,
    categories,
    sources,
}: ExpensesIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [currency, setCurrency] = useState(filters.currency ?? '');
    const [categoryId, setCategoryId] = useState(
        filters.category_id ? Number(filters.category_id) : 0,
    );
    const [sourceId, setSourceId] = useState(
        filters.payment_source_id ? Number(filters.payment_source_id) : 0,
    );
    const [showFilters, setShowFilters] = useState(
        Boolean(
            currency || categoryId || sourceId || filters.from || filters.to,
        ),
    );

    const activeFilterCount =
        (currency ? 1 : 0) +
        (categoryId ? 1 : 0) +
        (sourceId ? 1 : 0) +
        (filters.from || filters.to ? 1 : 0);

    function applyFilters() {
        router.get(
            expensesIndex().url,
            {
                search: search || undefined,
                currency: currency || undefined,
                category_id: categoryId || undefined,
                payment_source_id: sourceId || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function clearFilters() {
        setSearch('');
        setCurrency('');
        setCategoryId(0);
        setSourceId(0);
        router.get(
            expensesIndex().url,
            {},
            { preserveState: true, preserveScroll: true },
        );
    }

    const exportUrl = reportsExport({
        query: {
            search: search || undefined,
            currency: currency || undefined,
            category_id: categoryId || undefined,
            payment_source_id: sourceId || undefined,
            from: filters.from || undefined,
            to: filters.to || undefined,
        },
    }).url;

    const grouped = expenses.data.reduce<Record<string, Expense[]>>(
        (acc, expense) => {
            (acc[expense.spent_at] ??= []).push(expense);

            return acc;
        },
        {},
    );

    const groupLabel = (date: string): string => {
        if (isToday(date)) {
            return 'HOY';
        }

        if (isYesterday(date)) {
            return 'AYER';
        }

        return formatMonthLabel(date).replace(',', '');
    };

    return (
        <div className="space-y-4">
            <TrackerCard>
                <div className="px-4 py-3">
                    <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        Este mes
                    </p>
                    <div className="mt-1.5 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <span className="font-display text-2xl font-extrabold text-emerald-400 tabular-nums">
                            {formatAmount(totals.usd)} USD
                        </span>
                        <span className="font-display text-lg font-bold text-blue-400 tabular-nums">
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
                        placeholder="Buscar gasto..."
                        className="h-11 w-full rounded-xl bg-surface-low pr-10 pl-10 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                        aria-label="Buscar gasto"
                    />
                    {search && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                router.get(
                                    expensesIndex().url,
                                    {},
                                    { preserveState: true, preserveScroll: true },
                                );
                            }}
                            className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground"
                            aria-label="Limpiar búsqueda"
                        >
                            <X className="size-4" />
                        </button>
                    )}
                </div>

                <div className="flex items-center justify-between lg:justify-start lg:gap-3">
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setShowFilters((value) => !value)}
                            className="relative inline-flex items-center gap-2 rounded-full bg-surface-low px-3.5 py-2 text-xs font-semibold text-foreground"
                        >
                            <SlidersHorizontal className="size-3.5" />
                            Filtros
                            {activeFilterCount > 0 && (
                                <span className="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-emerald-500 text-[9px] font-bold text-primary-foreground">
                                    {activeFilterCount}
                                </span>
                            )}
                        </button>

                        <a
                            href={exportUrl}
                            className="inline-flex items-center gap-2 rounded-full bg-surface-low px-3.5 py-2 text-xs font-semibold text-foreground"
                            aria-label="Exportar gastos a CSV"
                            title="Exportar a CSV con los filtros actuales"
                        >
                            <Download className="size-3.5" />
                            Exportar
                        </a>
                    </div>

                    {activeFilterCount > 0 && (
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="text-xs font-medium text-muted-foreground hover:text-foreground"
                        >
                            Limpiar filtros
                        </button>
                    )}
                </div>
            </div>

            {showFilters && (
                <div className="space-y-3 rounded-xl bg-surface-low p-4">
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                        <label className="block">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                Moneda
                            </span>
                            <select
                                value={currency}
                                onChange={(event) => {
                                    setCurrency(event.target.value);
                                    router.get(
                                        expensesIndex().url,
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
                                className="h-10 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            >
                                <option value="">Todas</option>
                                <option value="usd">USD</option>
                                <option value="ves">Bs</option>
                                <option value="usdt">USDT</option>
                            </select>
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                Categoría
                            </span>
                            <select
                                value={categoryId}
                                onChange={(event) => {
                                    setCategoryId(Number(event.target.value));
                                    router.get(
                                        expensesIndex().url,
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
                                className="h-10 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            >
                                <option value={0}>Todas</option>
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

                        <label className="block">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                Origen
                            </span>
                            <select
                                value={sourceId}
                                onChange={(event) => {
                                    setSourceId(Number(event.target.value));
                                    router.get(
                                        expensesIndex().url,
                                        {
                                            payment_source_id:
                                                event.target.value || undefined,
                                        },
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                                className="h-10 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            >
                                <option value={0}>Todos</option>
                                {sources.map((source) => (
                                    <option key={source.id} value={source.id}>
                                        {source.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>

                    <button
                        type="button"
                        onClick={applyFilters}
                        className="w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-2.5 text-sm font-semibold text-primary-foreground lg:w-auto lg:px-8"
                    >
                        Aplicar filtros
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
                                        expensesIndex().url,
                                        { currency: undefined },
                                        { preserveState: true },
                                    );
                                }}
                                aria-label="Quitar filtro de moneda"
                            >
                                <X className="size-3" />
                            </button>
                        </span>
                    )}
                    {categoryId > 0 && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-400">
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
                                        expensesIndex().url,
                                        { category_id: undefined },
                                        { preserveState: true },
                                    );
                                }}
                                aria-label="Quitar filtro de categoría"
                            >
                                <X className="size-3" />
                            </button>
                        </span>
                    )}
                    {sourceId > 0 && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-500/15 px-3 py-1 text-xs font-semibold text-blue-400">
                            {
                                sources.find((source) => source.id === sourceId)
                                    ?.name
                            }
                            <button
                                type="button"
                                onClick={() => {
                                    setSourceId(0);
                                    router.get(
                                        expensesIndex().url,
                                        { payment_source_id: undefined },
                                        { preserveState: true },
                                    );
                                }}
                                aria-label="Quitar filtro de origen"
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
                        Sin resultados
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        No hay gastos que coincidan con tu búsqueda.
                    </p>
                </div>
            ) : (
                <>
                    <div className="hidden lg:block">
                        <table className="w-full border-separate border-spacing-y-2">
                            <thead>
                                <tr className="text-left text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                                    <th className="px-4 py-1 font-bold">
                                        Fecha
                                    </th>
                                    <th className="px-4 py-1 font-bold">
                                        Gasto
                                    </th>
                                    <th className="px-4 py-1 font-bold">
                                        Origen
                                    </th>
                                    <th className="px-4 py-1 font-bold">
                                        Moneda
                                    </th>
                                    <th className="px-4 py-1 text-right font-bold">
                                        Monto
                                    </th>
                                    <th className="w-12 px-2 py-1" />
                                </tr>
                            </thead>
                            <tbody>
                                {expenses.data.map((expense) => (
                                    <tr key={expense.id} className="bg-surface-low">
                                        <td className="px-4 py-3 text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                                            {formatDate(expense.spent_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/expenses/${expense.id}`}
                                                className="block max-w-xs truncate text-sm font-semibold text-foreground hover:text-emerald-400"
                                            >
                                                {expense.description}
                                            </Link>
                                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <span
                                                    className="size-1.5 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            expense.category
                                                                .color,
                                                    }}
                                                />
                                                {expense.category.name}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-muted-foreground">
                                            {expense.source.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            <CurrencyChip
                                                currency={expense.currency}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-right font-display text-sm font-bold text-foreground tabular-nums">
                                            {formatAmount(expense.amount)}
                                        </td>
                                        <td className="rounded-r-xl px-2 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            `¿Eliminar "${expense.description}"? Esta acción no se puede deshacer.`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            expensesDestroy(
                                                                expense.id,
                                                            ).url,
                                                        );
                                                    }
                                                }}
                                                className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-destructive/15 hover:text-destructive"
                                                aria-label={`Eliminar ${expense.description}`}
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
                                    {groupLabel(date)} · {formatMonthLabel(date)}
                                </p>
                                <div className="space-y-2.5">
                                    {items.map((expense) => (
                                        <ExpenseListItem
                                            key={expense.id}
                                            expense={expense}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}

            {expenses.total > 20 && (
                <div className="flex items-center justify-center gap-3 pt-2">
                    <button
                        type="button"
                        disabled={!expenses.prev_page_url}
                        onClick={() =>
                            expenses.prev_page_url &&
                            router.get(expenses.prev_page_url)
                        }
                        className="inline-flex size-9 items-center justify-center rounded-lg bg-surface-low text-foreground disabled:opacity-40"
                        aria-label="Página anterior"
                    >
                        <ChevronLeft className="size-4" />
                    </button>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {expenses.total} gastos
                    </span>
                    <button
                        type="button"
                        disabled={!expenses.next_page_url}
                        onClick={() =>
                            expenses.next_page_url &&
                            router.get(expenses.next_page_url)
                        }
                        className="inline-flex size-9 items-center justify-center rounded-lg bg-surface-low text-foreground disabled:opacity-40"
                        aria-label="Página siguiente"
                    >
                        <ChevronRight className="size-4" />
                    </button>
                </div>
            )}
        </div>
    );
}
