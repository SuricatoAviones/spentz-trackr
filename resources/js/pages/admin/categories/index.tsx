import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Pencil, Search, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CategoryIcon } from '@/components/tracker/category-icon';
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
import { Label } from '@/components/ui/label';
import { formatAmount } from '@/lib/format';
import { ICONS } from '@/lib/icons';
import {
    destroy as categoriesDestroy,
    index as categoriesIndex,
    update as categoriesUpdate,
} from '@/routes/admin/categories';

const PRESET_COLORS = [
    '#10B981',
    '#3B82F6',
    '#F59E0B',
    '#EF4444',
    '#8B5CF6',
    '#EC4899',
    '#06B6D4',
    '#6B7280',
];

const ICON_OPTIONS = Object.keys(ICONS);

type AdminCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    budget: string | null;
    is_system: boolean;
    expenses_count: number;
    total_usd: number;
    can_delete: boolean;
    user: {
        id: number;
        name: string;
        email: string;
    };
};

type CategoriesPaginator = {
    data: AdminCategory[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
};

export default function AdminCategoriesIndex({
    categories,
    filters,
    users,
}: {
    categories: CategoriesPaginator;
    filters: { search: string; user_id: number | null };
    users: { id: number; name: string; email: string }[];
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [userId, setUserId] = useState(
        filters.user_id ? Number(filters.user_id) : 0,
    );
    const [editing, setEditing] = useState<AdminCategory | null>(null);
    const searchTimeout = useRef<number | null>(null);

    setLayoutProps({
        title: t('admin.categories.title'),
        description: t('admin.categories.total', { count: categories.total }),
    });

    const { data, setData, put, processing, errors } = useForm({
        name: '',
        icon: 'tag',
        color: PRESET_COLORS[0],
        budget: '',
    });

    function openEdit(category: AdminCategory) {
        setEditing(category);
        setData({
            name: category.name,
            icon: category.icon,
            color: category.color,
            budget: category.budget ? String(category.budget) : '',
        });
    }

    function submitEdit(event: React.FormEvent) {
        event.preventDefault();

        if (!editing) {
            return;
        }

        put(categoriesUpdate({ category: editing.id }).url, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    }

    function destroy(category: AdminCategory) {
        if (
            confirm(
                t('admin.categories.delete_confirm', {
                    name: category.name,
                    user: category.user.name,
                }),
            )
        ) {
            router.delete(categoriesDestroy({ category: category.id }).url, {
                preserveScroll: true,
            });
        }
    }

    function applyFilters() {
        router.get(
            categoriesIndex().url,
            {
                search: search || undefined,
                user_id: userId || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={t('admin.categories.title')} />

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
                                    setSearch(event.target.value);

                                    if (searchTimeout.current) {
                                        window.clearTimeout(
                                            searchTimeout.current,
                                        );
                                    }

                                    searchTimeout.current = window.setTimeout(
                                        () => applyFilters(),
                                        400,
                                    );
                                }}
                                placeholder={t(
                                    'admin.categories.search_placeholder',
                                )}
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit">
                            {t('admin.users.search_button')}
                        </Button>
                    </form>
                </div>

                <Card>
                    <CardContent className="p-4">
                        <label className="block max-w-xs">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('admin.expenses.filter_user')}
                            </span>
                            <select
                                value={userId}
                                onChange={(event) => {
                                    const value = Number(event.target.value);
                                    setUserId(value);
                                    router.get(
                                        categoriesIndex().url,
                                        {
                                            search: search || undefined,
                                            user_id: value || undefined,
                                        },
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                                className="h-10 w-full rounded-lg bg-surface-low px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            >
                                <option value={0}>
                                    {t('admin.expenses.all_users')}
                                </option>
                                {users.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name} ({user.email})
                                    </option>
                                ))}
                            </select>
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="px-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs tracking-wider text-muted-foreground uppercase">
                                        <th className="px-4 py-3 font-medium">
                                            {t('admin.categories.col_category')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            {t('admin.expenses.filter_user')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                            {t('admin.users.col_expenses')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                            {t('admin.users.col_total_usd')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium lg:table-cell">
                                            {t('admin.categories.col_budget')}
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            {t('admin.users.col_actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {categories.data.map((category) => (
                                        <tr
                                            key={category.id}
                                            className="hover:bg-accent/50"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <CategoryIcon
                                                        icon={category.icon}
                                                        color={category.color}
                                                        size="sm"
                                                    />
                                                    <div className="min-w-0">
                                                        <p className="flex items-center gap-2 truncate font-medium">
                                                            {category.name}
                                                            {category.is_system && (
                                                                <Badge variant="outline">
                                                                    {t(
                                                                        'admin.categories.badge_system',
                                                                    )}
                                                                </Badge>
                                                            )}
                                                        </p>
                                                        <p className="truncate text-xs text-muted-foreground md:hidden">
                                                            {category.user.name}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                <span className="block max-w-40 truncate">
                                                    {category.user.name}
                                                </span>
                                                <span className="block max-w-40 truncate text-xs text-muted-foreground">
                                                    {category.user.email}
                                                </span>
                                            </td>
                                            <td className="hidden px-4 py-3 sm:table-cell">
                                                {category.expenses_count}
                                            </td>
                                            <td className="hidden px-4 py-3 sm:table-cell">
                                                {formatAmount(
                                                    category.total_usd,
                                                )}
                                            </td>
                                            <td className="hidden px-4 py-3 lg:table-cell">
                                                {category.budget
                                                    ? formatAmount(
                                                          category.budget,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEdit(category)
                                                        }
                                                        aria-label={t(
                                                            'admin.users.edit_aria',
                                                            {
                                                                name: category.name,
                                                            },
                                                        )}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            destroy(category)
                                                        }
                                                        disabled={
                                                            !category.can_delete
                                                        }
                                                        className="text-destructive hover:text-destructive disabled:opacity-40"
                                                        aria-label={t(
                                                            'admin.users.delete_aria',
                                                            {
                                                                name: category.name,
                                                            },
                                                        )}
                                                        title={
                                                            category.can_delete
                                                                ? undefined
                                                                : t(
                                                                      'common.delete_blocked',
                                                                  )
                                                        }
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

                        {categories.data.length === 0 && (
                            <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                                {t('admin.categories.no_categories')}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Dialog
                    open={editing !== null}
                    onOpenChange={(open) => !open && setEditing(null)}
                >
                    <DialogContent className="rounded-2xl sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>
                                {t('admin.categories.edit_title')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('admin.categories.edit_description', {
                                    name: editing?.user.name,
                                })}
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={submitEdit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="edit-category-name">
                                    {t('common.name')}
                                </Label>
                                <Input
                                    id="edit-category-name"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    required
                                    maxLength={50}
                                />
                                {errors.name && (
                                    <p className="text-xs text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="edit-category-icon">
                                    {t('common.icon')}
                                </Label>
                                <select
                                    id="edit-category-icon"
                                    value={data.icon}
                                    onChange={(event) =>
                                        setData('icon', event.target.value)
                                    }
                                    className="h-10 w-full rounded-lg bg-surface-low px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                >
                                    {ICON_OPTIONS.map((icon) => (
                                        <option key={icon} value={icon}>
                                            {icon}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('common.color')}</Label>
                                <div className="flex flex-wrap gap-2">
                                    {PRESET_COLORS.map((color) => (
                                        <button
                                            key={color}
                                            type="button"
                                            onClick={() =>
                                                setData('color', color)
                                            }
                                            className={`size-8 rounded-full transition-transform ${
                                                data.color === color
                                                    ? 'scale-110 ring-2 ring-foreground ring-offset-2 ring-offset-background'
                                                    : 'hover:scale-105'
                                            }`}
                                            style={{ backgroundColor: color }}
                                            aria-label={t('common.color_aria', {
                                                color,
                                            })}
                                        />
                                    ))}
                                </div>
                                {errors.color && (
                                    <p className="text-xs text-destructive">
                                        {errors.color}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="edit-category-budget">
                                    {t('categories.budget_label')}
                                </Label>
                                <Input
                                    id="edit-category-budget"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={data.budget}
                                    onChange={(event) =>
                                        setData('budget', event.target.value)
                                    }
                                    placeholder={t(
                                        'admin.categories.budget_optional',
                                    )}
                                />
                                {errors.budget && (
                                    <p className="text-xs text-destructive">
                                        {errors.budget}
                                    </p>
                                )}
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                            >
                                {processing
                                    ? t('common.saving')
                                    : t('admin.users.save_changes')}
                            </Button>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
