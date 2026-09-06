import { Head, router, useForm } from '@inertiajs/react';
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
    destroy as sourcesDestroy,
    index as sourcesIndex,
    update as sourcesUpdate,
} from '@/routes/admin/sources';

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

type AdminSource = {
    id: number;
    name: string;
    icon: string;
    color: string;
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

type SourcesPaginator = {
    data: AdminSource[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
};

export default function AdminSourcesIndex({
    sources,
    filters,
    users,
}: {
    sources: SourcesPaginator;
    filters: { search: string; user_id: number | null };
    users: { id: number; name: string; email: string }[];
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [userId, setUserId] = useState(filters.user_id ? Number(filters.user_id) : 0);
    const [editing, setEditing] = useState<AdminSource | null>(null);
    const searchTimeout = useRef<number | null>(null);

    const { data, setData, put, processing, errors } = useForm({
        name: '',
        icon: 'wallet',
        color: PRESET_COLORS[0],
    });

    function openEdit(source: AdminSource) {
        setEditing(source);
        setData({
            name: source.name,
            icon: source.icon,
            color: source.color,
        });
    }

    function submitEdit(event: React.FormEvent) {
        event.preventDefault();

        if (!editing) {
            return;
        }

        put(sourcesUpdate({ source: editing.id }).url, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    }

    function destroy(source: AdminSource) {
        if (
            confirm(
                t('admin:sources.delete_confirm', {
                    name: source.name,
                    user: source.user.name,
                }),
            )
        ) {
            router.delete(sourcesDestroy({ source: source.id }).url, {
                preserveScroll: true,
            });
        }
    }

    function applyFilters() {
        router.get(
            sourcesIndex().url,
            {
                search: search || undefined,
                user_id: userId || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={t('admin:sources.title')} />

            <div className="space-y-8 px-4 py-6 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight">
                            {t('sources:title')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t('admin:sources.total', {
                                count: sources.total,
                            })}
                        </p>
                    </div>

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
                                placeholder={t('admin:sources.search_placeholder')}
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit">
                            {t('admin:users.search_button')}
                        </Button>
                    </form>
                </div>

                <Card>
                    <CardContent className="p-4">
                        <label className="block max-w-xs">
                            <span className="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('admin:expenses.filter_user')}
                            </span>
                            <select
                                value={userId}
                                onChange={(event) => {
                                    const value = Number(event.target.value);
                                    setUserId(value);
                                    router.get(
                                        sourcesIndex().url,
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
                                <option value={0}>{t('admin:expenses.all_users')}</option>
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
                                            {t('admin:expenses.col_source')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            {t('admin:expenses.filter_user')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                            {t('admin:users.col_expenses')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                            {t('admin:users.col_total_usd')}
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            {t('admin:users.col_actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {sources.data.map((source) => (
                                        <tr
                                            key={source.id}
                                            className="hover:bg-accent/50"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <CategoryIcon
                                                        icon={source.icon}
                                                        color={source.color}
                                                        size="sm"
                                                    />
                                                    <div className="min-w-0">
                                                        <p className="flex items-center gap-2 truncate font-medium">
                                                            {source.name}
                                                            {source.is_system && (
                                                                <Badge variant="outline">
                                                                    {t('admin:categories.badge_system')}
                                                                </Badge>
                                                            )}
                                                        </p>
                                                        <p className="truncate text-xs text-muted-foreground md:hidden">
                                                            {source.user.name}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                <span className="block max-w-40 truncate">
                                                    {source.user.name}
                                                </span>
                                                <span className="block max-w-40 truncate text-xs text-muted-foreground">
                                                    {source.user.email}
                                                </span>
                                            </td>
                                            <td className="hidden px-4 py-3 sm:table-cell">
                                                {source.expenses_count}
                                            </td>
                                            <td className="hidden px-4 py-3 sm:table-cell">
                                                {formatAmount(
                                                    source.total_usd,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEdit(source)
                                                        }
                                                        aria-label={t('admin:users.edit_aria', {
                                                            name: source.name,
                                                        })}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            destroy(source)
                                                        }
                                                        disabled={
                                                            !source.can_delete
                                                        }
                                                        className="text-destructive hover:text-destructive disabled:opacity-40"
                                                        aria-label={t('admin:users.delete_aria', {
                                                            name: source.name,
                                                        })}
                                                        title={
                                                            source.can_delete
                                                                ? undefined
                                                                : t('common:delete_blocked')
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

                        {sources.data.length === 0 && (
                            <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                                {t('admin:sources.no_sources')}
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
                            <DialogTitle>{t('admin:sources.edit_title')}</DialogTitle>
                            <DialogDescription>
                                {t('admin:sources.edit_description', {
                                    name: editing?.user.name,
                                })}
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={submitEdit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="edit-source-name">
                                    {t('common:name')}
                                </Label>
                                <Input
                                    id="edit-source-name"
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
                                <Label htmlFor="edit-source-icon">
                                    {t('common:icon')}
                                </Label>
                                <select
                                    id="edit-source-icon"
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
                                <Label>{t('common:color')}</Label>
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
                                            aria-label={t('common:color_aria', { color })}
                                        />
                                    ))}
                                </div>
                                {errors.color && (
                                    <p className="text-xs text-destructive">
                                        {errors.color}
                                    </p>
                                )}
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                            >
                                {processing
                                    ? t('common:saving')
                                    : t('admin:users.save_changes')}
                            </Button>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}