import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Pencil,
    Search,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    destroy as usersDestroy,
    index as usersIndex,
    show as usersShow,
    update as usersUpdate,
} from '@/routes/admin/users';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    email_verified_at: string | null;
    suspended_at: string | null;
    created_at: string;
    updated_at: string;
    expenses_count: number;
    total_usd: number;
    last_expense_at: string | null;
};

type UsersPaginator = {
    data: AdminUser[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
};

export default function AdminUsersIndex({
    users,
    filters,
}: {
    users: UsersPaginator;
    filters: { search: string };
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [editing, setEditing] = useState<AdminUser | null>(null);
    const [editData, setEditData] = useState({
        name: '',
        email: '',
        is_admin: false,
    });
    const [saving, setSaving] = useState(false);
    const searchTimeout = useRef<number | null>(null);

    function submitSearch() {
        router.get(
            usersIndex().url,
            { search },
            { preserveState: true, replace: true },
        );
    }

    function openEdit(user: AdminUser) {
        setEditing(user);
        setEditData({
            name: user.name,
            email: user.email,
            is_admin: user.is_admin,
        });
    }

    function submitEdit(event: FormEvent) {
        event.preventDefault();

        if (!editing) {
            return;
        }

        setSaving(true);
        router.patch(usersUpdate({ user: editing.id }).url, editData, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
            onFinish: () => setSaving(false),
        });
    }

    function destroy(user: AdminUser) {
        if (confirm(t('admin:users.delete_confirm', { name: user.name }))) {
            router.delete(usersDestroy({ user: user.id }).url);
        }
    }

    return (
        <>
            <Head title={t('admin:users.title')} />

            <div className="space-y-8 px-4 py-6 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight">
                            {t('admin:users.title')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t('admin:users.accounts_total', {
                                count: users.total,
                            })}
                        </p>
                    </div>

                    <form
                        className="flex gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitSearch();
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
                                        submitSearch,
                                        400,
                                    );
                                }}
                                placeholder={t(
                                    'admin:users.search_placeholder',
                                )}
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit">
                            {t('admin:users.search_button')}
                        </Button>
                    </form>
                </div>

                <Card>
                    <CardContent className="px-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs tracking-wider text-muted-foreground uppercase">
                                        <th className="px-4 py-3 font-medium">
                                            {t('admin:users.col_user')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            {t('admin:users.col_role')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            {t('admin:users.col_verified')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                            {t('admin:users.col_expenses')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                            {t('admin:users.col_total_usd')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium lg:table-cell">
                                            {t('admin:users.col_registered')}
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            {t('admin:users.col_actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="hover:bg-accent/50"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={
                                                        usersShow({
                                                            user: user.id,
                                                        }).url
                                                    }
                                                    className="block max-w-56 truncate font-medium hover:underline"
                                                >
                                                    {user.name}
                                                </Link>
                                                <span className="block max-w-56 truncate text-xs text-muted-foreground">
                                                    {user.email}
                                                </span>
                                                {user.suspended_at && (
                                                    <Badge
                                                        variant="destructive"
                                                        className="mt-1"
                                                    >
                                                        {t(
                                                            'admin:users.badge_suspended',
                                                        )}
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                {user.is_admin ? (
                                                    <Badge variant="secondary">
                                                        {t(
                                                            'admin:users.badge_admin',
                                                        )}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        {t(
                                                            'admin:users.badge_user',
                                                        )}
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                {user.email_verified_at ? (
                                                    <Badge variant="default">
                                                        {t(
                                                            'admin:users.badge_yes',
                                                        )}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        {t(
                                                            'admin:users.badge_no',
                                                        )}
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="hidden px-4 py-3 sm:table-cell">
                                                {user.expenses_count}
                                            </td>
                                            <td className="hidden px-4 py-3 sm:table-cell">
                                                {formatAmount(user.total_usd)}
                                            </td>
                                            <td className="hidden px-4 py-3 lg:table-cell">
                                                {user.created_at}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEdit(user)
                                                        }
                                                        aria-label={t(
                                                            'admin:users.edit_aria',
                                                            { name: user.name },
                                                        )}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            destroy(user)
                                                        }
                                                        className="text-destructive hover:text-destructive"
                                                        aria-label={t(
                                                            'admin:users.delete_aria',
                                                            {
                                                                name: user.name,
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

                        {users.data.length === 0 && (
                            <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                                {t('admin:users.no_users')}
                            </p>
                        )}
                    </CardContent>
                </Card>

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            {t('admin:users.page_of', {
                                current: users.current_page,
                                total: users.last_page,
                            })}
                        </p>
                        <div className="flex gap-2">
                            {users.current_page > 1 && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(
                                            usersIndex().url,
                                            {
                                                search,
                                                page: users.current_page - 1,
                                            },
                                            { preserveState: true },
                                        )
                                    }
                                >
                                    <ChevronLeft className="size-4" />{' '}
                                    {t('admin:users.prev')}
                                </Button>
                            )}
                            {users.current_page < users.last_page && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(
                                            usersIndex().url,
                                            {
                                                search,
                                                page: users.current_page + 1,
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
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent className="rounded-2xl sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('admin:users.edit_title')}</DialogTitle>
                        <DialogDescription>
                            {t('admin:users.edit_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitEdit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-name">
                                {t('common:name')}
                            </Label>
                            <Input
                                id="edit-name"
                                value={editData.name}
                                onChange={(event) =>
                                    setEditData({
                                        ...editData,
                                        name: event.target.value,
                                    })
                                }
                                required
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-email">
                                {t('settings:email_label')}
                            </Label>
                            <Input
                                id="edit-email"
                                type="email"
                                value={editData.email}
                                onChange={(event) =>
                                    setEditData({
                                        ...editData,
                                        email: event.target.value,
                                    })
                                }
                                required
                            />
                        </div>

                        <label className="flex items-center gap-2">
                            <Checkbox
                                checked={editData.is_admin}
                                onCheckedChange={(checked) =>
                                    setEditData({
                                        ...editData,
                                        is_admin: checked === true,
                                    })
                                }
                            />
                            <span className="text-sm">
                                {t('admin:users.admin_role')}
                            </span>
                        </label>

                        <Button
                            type="submit"
                            disabled={saving}
                            className="w-full"
                        >
                            {saving
                                ? t('common:saving')
                                : t('admin:users.save_changes')}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
