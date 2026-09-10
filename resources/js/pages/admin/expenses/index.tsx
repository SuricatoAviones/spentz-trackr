import { Head, router, setLayoutProps } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    Paperclip,
    Search,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatAmount, formatDate } from '@/lib/format';
import {
    destroy as expensesDestroy,
    exportMethod as expensesExport,
    index as expensesIndex,
} from '@/routes/admin/expenses';
import { show as receiptsShow } from '@/routes/admin/expenses/receipts';

type AdminExpense = {
    id: number;
    description: string;
    amount: string;
    currency: 'usd' | 'ves' | 'usdt';
    usd_amount: string;
    usdt_amount: string;
    spent_at: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
    category: string;
    source: string;
    receipts: { id: number; original_name: string }[];
};

type ExpensesPaginator = {
    data: AdminExpense[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
};

type Filters = {
    search: string;
    user_id: number | null;
    category_id: number | null;
    currency: string;
    from: string;
    to: string;
};

const CURRENCY_LABEL: Record<AdminExpense['currency'], string> = {
    usd: 'USD',
    ves: 'Bs',
    usdt: 'USDT',
};

export default function AdminExpensesIndex({
    expenses,
    filters,
    users,
    categories,
}: {
    expenses: ExpensesPaginator;
    filters: Filters;
    users: { id: number; name: string; email: string }[];
    categories: { id: number; name: string; user_name: string }[];
}) {
    const { t } = useTranslation();

    setLayoutProps({
        title: t('admin:expenses.title'),
        description: t('admin:expenses.total', { count: expenses.total }),
    });

    const [search, setSearch] = useState(filters.search ?? '');
    const [userId, setUserId] = useState(
        filters.user_id ? Number(filters.user_id) : 0,
    );
    const [categoryId, setCategoryId] = useState(
        filters.category_id ? Number(filters.category_id) : 0,
    );
    const [currency, setCurrency] = useState(filters.currency ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [receiptsFor, setReceiptsFor] = useState<AdminExpense | null>(null);
    const searchTimeout = useRef<number | null>(null);

    function applyFilters(
        overrides: {
            search?: string;
            user_id?: number;
            category_id?: number;
            currency?: string;
            from?: string;
            to?: string;
        } = {},
    ) {
        router.get(
            expensesIndex().url,
            {
                search: overrides.search ?? (search || undefined),
                user_id: (overrides.user_id ?? userId) || undefined,
                category_id: (overrides.category_id ?? categoryId) || undefined,
                currency: overrides.currency ?? (currency || undefined),
                from: overrides.from ?? (from || undefined),
                to: overrides.to ?? (to || undefined),
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function destroy(expense: AdminExpense) {
        if (
            confirm(
                t('admin:expenses.delete_confirm', {
                    description: expense.description,
                    user: expense.user.name,
                }),
            )
        ) {
            router.delete(expensesDestroy({ expense: expense.id }).url, {
                preserveScroll: true,
            });
        }
    }

    const activeFilterCount =
        (userId ? 1 : 0) +
        (categoryId ? 1 : 0) +
        (currency ? 1 : 0) +
        (from || to ? 1 : 0);

    const exportUrl = expensesExport({
        query: {
            search: search || undefined,
            user_id: userId || undefined,
            category_id: categoryId || undefined,
            currency: currency || undefined,
            from: from || undefined,
            to: to || undefined,
        },
    }).url;

    return (
        <>
            <Head title={t('admin:expenses.title')} />

            <div className="space-y-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-end">
                    <form
                        className="flex gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilters();
                        }}
                    >
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) => {
                                    const value = event.target.value;
                                    setSearch(value);

                                    if (searchTimeout.current) {
                                        window.clearTimeout(
                                            searchTimeout.current,
                                        );
                                    }

                                    searchTimeout.current = window.setTimeout(
                                        () => applyFilters({ search: value }),
                                        400,
                                    );
                                }}
                                placeholder={t(
                                    'admin:expenses.search_placeholder',
                                )}
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit">
                            {t('admin:users.search_button')}
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={exportUrl}
                                aria-label={t('admin:expenses.export_aria')}
                                title={t('admin:expenses.export_title')}
                            >
                                <Download className="size-4" />
                                {t('admin:expenses.export')}
                            </a>
                        </Button>
                    </form>
                </div>

                <Card>
                    <CardContent className="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5">
                        <label className="block">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('admin:expenses.filter_user')}
                            </span>
                            <select
                                value={userId}
                                onChange={(event) => {
                                    const value = Number(event.target.value);
                                    setUserId(value);
                                    applyFilters({ user_id: value });
                                }}
                                className="h-10 w-full rounded-lg bg-surface-low px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            >
                                <option value={0}>
                                    {t('admin:expenses.all_users')}
                                </option>
                                {users.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name} ({user.email})
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('admin:expenses.filter_currency')}
                            </span>
                            <select
                                value={currency}
                                onChange={(event) => {
                                    const value = event.target.value;
                                    setCurrency(value);
                                    applyFilters({ currency: value });
                                }}
                                className="h-10 w-full rounded-lg bg-surface-low px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            >
                                <option value="">
                                    {t('admin:expenses.all_currencies')}
                                </option>
                                <option value="usd">USD</option>
                                <option value="ves">Bs</option>
                                <option value="usdt">USDT</option>
                            </select>
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('admin:expenses.filter_category')}
                            </span>
                            <select
                                value={categoryId}
                                onChange={(event) => {
                                    const value = Number(event.target.value);
                                    setCategoryId(value);
                                    applyFilters({ category_id: value });
                                }}
                                className="h-10 w-full rounded-lg bg-surface-low px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            >
                                <option value={0}>
                                    {t('admin:expenses.all_categories')}
                                </option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {category.name} ({category.user_name})
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('admin:expenses.filter_from')}
                            </span>
                            <Input
                                type="date"
                                value={from}
                                onChange={(event) => {
                                    const value = event.target.value;
                                    setFrom(value);
                                    applyFilters({ from: value });
                                }}
                                className="h-10"
                            />
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('admin:expenses.filter_to')}
                            </span>
                            <Input
                                type="date"
                                value={to}
                                onChange={(event) => {
                                    const value = event.target.value;
                                    setTo(value);
                                    applyFilters({ to: value });
                                }}
                                className="h-10"
                            />
                        </label>

                        {activeFilterCount > 0 && (
                            <button
                                type="button"
                                onClick={() => {
                                    setUserId(0);
                                    setCategoryId(0);
                                    setCurrency('');
                                    setFrom('');
                                    setTo('');
                                    router.get(
                                        expensesIndex().url,
                                        {},
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                                className="text-xs font-medium text-muted-foreground hover:text-foreground sm:col-span-2 lg:col-span-5"
                            >
                                {t('admin:expenses.clear_filters')}
                            </button>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="px-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs tracking-wider text-muted-foreground uppercase">
                                        <th className="px-4 py-3 font-medium">
                                            {t('admin:expenses.col_expense')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            {t('admin:expenses.filter_user')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium lg:table-cell">
                                            {t(
                                                'admin:expenses.filter_category',
                                            )}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium lg:table-cell">
                                            {t('admin:expenses.col_source')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                            {t('admin:expenses.col_amount')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                            USD
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            {t('admin:expenses.col_date')}
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            {t('admin:users.col_actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {expenses.data.map((expense) => (
                                        <tr
                                            key={expense.id}
                                            className="hover:bg-accent/50"
                                        >
                                            <td className="px-4 py-3">
                                                <span className="block max-w-56 truncate font-medium">
                                                    {expense.description}
                                                </span>
                                                <span className="block max-w-56 truncate text-xs text-muted-foreground sm:hidden">
                                                    {expense.user.name} ·{' '}
                                                    {formatDate(
                                                        expense.spent_at,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                <span className="block max-w-40 truncate font-medium">
                                                    {expense.user.name}
                                                </span>
                                                <span className="block max-w-40 truncate text-xs text-muted-foreground">
                                                    {expense.user.email}
                                                </span>
                                            </td>
                                            <td className="hidden px-4 py-3 lg:table-cell">
                                                {expense.category}
                                            </td>
                                            <td className="hidden px-4 py-3 lg:table-cell">
                                                {expense.source}
                                            </td>
                                            <td className="hidden px-4 py-3 sm:table-cell">
                                                <span className="font-medium tabular-nums">
                                                    {formatAmount(
                                                        expense.amount,
                                                    )}
                                                </span>{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    {
                                                        CURRENCY_LABEL[
                                                            expense.currency
                                                        ]
                                                    }
                                                </span>
                                            </td>
                                            <td className="hidden px-4 py-3 sm:table-cell">
                                                <Badge variant="outline">
                                                    {formatAmount(
                                                        expense.usd_amount,
                                                    )}{' '}
                                                    USD
                                                </Badge>
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                {formatDate(expense.spent_at)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end">
                                                    {expense.receipts.length >
                                                        0 && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                setReceiptsFor(
                                                                    expense,
                                                                )
                                                            }
                                                            className="text-muted-foreground hover:text-foreground"
                                                            aria-label={t(
                                                                'admin:expenses.receipts_aria',
                                                                {
                                                                    description:
                                                                        expense.description,
                                                                },
                                                            )}
                                                        >
                                                            <Paperclip className="size-4" />
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            destroy(expense)
                                                        }
                                                        className="text-destructive hover:text-destructive"
                                                        aria-label={t(
                                                            'admin:expenses.delete_aria',
                                                            {
                                                                description:
                                                                    expense.description,
                                                            },
                                                        )}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {expenses.data.length === 0 && (
                            <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                                {t('admin:expenses.no_expenses')}
                            </p>
                        )}
                    </CardContent>
                </Card>

                {expenses.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            {t('admin:users.page_of', {
                                current: expenses.current_page,
                                total: expenses.last_page,
                            })}
                        </p>
                        <div className="flex gap-2">
                            {expenses.current_page > 1 && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(
                                            expensesIndex().url,
                                            {
                                                ...filters,
                                                page: expenses.current_page - 1,
                                            },
                                            { preserveState: true },
                                        )
                                    }
                                >
                                    <ChevronLeft className="size-4" />{' '}
                                    {t('admin:users.prev')}
                                </Button>
                            )}
                            {expenses.current_page < expenses.last_page && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(
                                            expensesIndex().url,
                                            {
                                                ...filters,
                                                page: expenses.current_page + 1,
                                            },
                                            { preserveState: true },
                                        )
                                    }
                                >
                                    {t('admin:users.next')}{' '}
                                    <ChevronRight className="size-4" />
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                <Dialog
                    open={receiptsFor !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setReceiptsFor(null);
                        }
                    }}
                >
                    <DialogContent className="rounded-2xl sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>
                                {t('admin:expenses.receipts_title')}
                            </DialogTitle>
                            <DialogDescription>
                                {receiptsFor
                                    ? `${receiptsFor.description} · ${receiptsFor.user.name}`
                                    : ''}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="divide-y rounded-lg border">
                            {receiptsFor?.receipts.map((receipt) => (
                                <a
                                    key={receipt.id}
                                    href={
                                        receiptsShow({
                                            receipt: receipt.id,
                                        }).url
                                    }
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center gap-3 px-4 py-3 text-sm font-medium hover:bg-accent"
                                >
                                    <Paperclip className="size-4 shrink-0 text-muted-foreground" />
                                    <span className="min-w-0 flex-1 truncate">
                                        {receipt.original_name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {t('admin:expenses.open')}
                                    </span>
                                </a>
                            ))}
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
