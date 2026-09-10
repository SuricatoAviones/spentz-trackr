import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    KeyRound,
    MailCheck,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { formatAmount, formatDate } from '@/lib/format';
import {
    destroy as usersDestroy,
    index as usersIndex,
    reactivate as usersReactivate,
    resetPassword as usersResetPassword,
    suspend as usersSuspend,
    update as usersUpdate,
    verifyEmail as usersVerifyEmail,
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

type RecentExpense = {
    id: number;
    description: string;
    amount: string;
    currency: 'usd' | 'ves' | 'usdt';
    usd_amount: string;
    spent_at: string;
    category: string;
    source: string;
};

const CURRENCY_LABEL: Record<RecentExpense['currency'], string> = {
    usd: 'USD',
    ves: 'Bs',
    usdt: 'USDT',
};

export default function AdminUserShow({
    user,
    stats,
    recentExpenses,
}: {
    user: AdminUser;
    stats: {
        total_usd: number;
        categories_count: number;
        sources_count: number;
    };
    recentExpenses: RecentExpense[];
}) {
    const { t } = useTranslation();

    setLayoutProps({
        title: t('admin:user_show.head_title', { name: user.name }),
    });

    const { data, setData, patch, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        is_admin: user.is_admin,
    });

    const [resetOpen, setResetOpen] = useState(false);
    const resetForm = useForm({
        password: '',
        password_confirmation: '',
    });

    function destroy() {
        if (confirm(t('admin:user_show.delete_confirm', { name: user.name }))) {
            router.delete(usersDestroy({ user: user.id }).url);
        }
    }

    function verifyEmail() {
        router.post(
            usersVerifyEmail({ user: user.id }).url,
            {},
            { preserveScroll: true },
        );
    }

    function submitResetPassword(event: React.FormEvent) {
        event.preventDefault();
        resetForm.post(usersResetPassword({ user: user.id }).url, {
            preserveScroll: true,
            onSuccess: () => {
                setResetOpen(false);
                resetForm.reset();
            },
        });
    }

    function toggleSuspension() {
        if (user.suspended_at) {
            router.post(
                usersReactivate({ user: user.id }).url,
                {},
                { preserveScroll: true },
            );

            return;
        }

        if (
            confirm(t('admin:user_show.suspend_confirm', { name: user.name }))
        ) {
            router.post(
                usersSuspend({ user: user.id }).url,
                {},
                { preserveScroll: true },
            );
        }
    }

    const initials = user.name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();

    return (
        <>
            <Head
                title={t('admin:user_show.head_title', { name: user.name })}
            />

            <div className="space-y-8">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={usersIndex().url}>
                        <ArrowLeft className="size-4" />{' '}
                        {t('admin:user_show.back_to_users')}
                    </Link>
                </Button>

                <div className="flex items-center gap-4">
                    <Avatar className="size-14">
                        <AvatarFallback className="text-lg">
                            {initials}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-xl font-semibold tracking-tight">
                                {user.name}
                            </h2>
                            {user.is_admin && (
                                <Badge variant="secondary">
                                    {t('admin:users.badge_admin')}
                                </Badge>
                            )}
                            {user.email_verified_at ? (
                                <Badge variant="default">
                                    {t('admin:user_show.badge_verified')}
                                </Badge>
                            ) : (
                                <Badge variant="outline">
                                    {t('admin:user_show.badge_unverified')}
                                </Badge>
                            )}
                            {user.suspended_at && (
                                <Badge variant="destructive">
                                    {t('admin:users.badge_suspended')}
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {user.email} ·{' '}
                            {t('admin:user_show.registered_on', {
                                date: formatDate(user.created_at),
                            })}
                        </p>
                    </div>
                </div>

                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('admin:users.col_expenses')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {user.expenses_count}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('admin:users.col_total_usd')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {formatAmount(user.total_usd)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('admin:user_show.stat_categories')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {stats.categories_count}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('admin:user_show.stat_sources')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {stats.sources_count}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('admin:user_show.edit_account')}
                            </CardTitle>
                            <CardDescription>
                                {t('admin:user_show.edit_account_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    patch(usersUpdate({ user: user.id }).url, {
                                        preserveScroll: true,
                                        onSuccess: () => {},
                                    });
                                }}
                                className="space-y-4"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="admin-edit-name">
                                        {t('common:name')}
                                    </Label>
                                    <Input
                                        id="admin-edit-name"
                                        value={data.name}
                                        onChange={(event) =>
                                            setData('name', event.target.value)
                                        }
                                        required
                                    />
                                    {errors.name && (
                                        <p className="text-xs text-destructive">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="admin-edit-email">
                                        {t('settings:email_label')}
                                    </Label>
                                    <Input
                                        id="admin-edit-email"
                                        type="email"
                                        value={data.email}
                                        onChange={(event) =>
                                            setData('email', event.target.value)
                                        }
                                        required
                                    />
                                    {errors.email && (
                                        <p className="text-xs text-destructive">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>

                                <label className="flex items-center gap-2">
                                    <Checkbox
                                        checked={data.is_admin}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'is_admin',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <span className="text-sm">
                                        {t('admin:users.admin_role')}
                                    </span>
                                </label>

                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? t('common:saving')
                                        : t('admin:users.save_changes')}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="border-destructive/40">
                        <CardHeader>
                            <CardTitle className="text-base text-destructive">
                                {t('admin:user_show.danger_zone')}
                            </CardTitle>
                            <CardDescription>
                                {t('admin:user_show.danger_zone_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="destructive" onClick={destroy}>
                                <Trash2 className="size-4" />{' '}
                                {t('admin:user_show.delete_user')}
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('admin:user_show.account_actions')}
                        </CardTitle>
                        <CardDescription>
                            {t('admin:user_show.account_actions_desc')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3">
                        {!user.email_verified_at && (
                            <Button variant="outline" onClick={verifyEmail}>
                                <MailCheck className="size-4" />{' '}
                                {t('admin:user_show.mark_verified')}
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => setResetOpen(true)}
                        >
                            <KeyRound className="size-4" />{' '}
                            {t('admin:user_show.reset_password')}
                        </Button>
                        {user.suspended_at ? (
                            <Button
                                variant="default"
                                onClick={toggleSuspension}
                            >
                                <RotateCcw className="size-4" />{' '}
                                {t('admin:user_show.reactivate')}
                            </Button>
                        ) : (
                            <Button
                                variant="destructive"
                                onClick={toggleSuspension}
                            >
                                <Ban className="size-4" />{' '}
                                {t('admin:user_show.suspend')}
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Dialog open={resetOpen} onOpenChange={setResetOpen}>
                    <DialogContent className="rounded-2xl sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>
                                {t('admin:user_show.reset_password')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('admin:user_show.reset_description', {
                                    name: user.name,
                                })}
                            </DialogDescription>
                        </DialogHeader>
                        <form
                            onSubmit={submitResetPassword}
                            className="space-y-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="admin-reset-password">
                                    {t('settings:new_password')}
                                </Label>
                                <Input
                                    id="admin-reset-password"
                                    type="password"
                                    value={resetForm.data.password}
                                    onChange={(event) =>
                                        resetForm.setData(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                {resetForm.errors.password && (
                                    <p className="text-xs text-destructive">
                                        {resetForm.errors.password}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="admin-reset-password-confirmation">
                                    {t('settings:confirm_password')}
                                </Label>
                                <Input
                                    id="admin-reset-password-confirmation"
                                    type="password"
                                    value={resetForm.data.password_confirmation}
                                    onChange={(event) =>
                                        resetForm.setData(
                                            'password_confirmation',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setResetOpen(false)}
                                >
                                    {t('settings:cancel')}
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={resetForm.processing}
                                >
                                    {resetForm.processing
                                        ? t('common:saving')
                                        : t('admin:user_show.reset_password')}
                                </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('admin:user_show.recent_expenses')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="px-2">
                        {recentExpenses.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                                {t('admin:user_show.no_expenses')}
                            </p>
                        ) : (
                            <div className="divide-y">
                                {recentExpenses.map((expense) => (
                                    <div
                                        key={expense.id}
                                        className="flex items-center justify-between gap-4 px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {expense.description}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {expense.category} ·{' '}
                                                {expense.source} ·{' '}
                                                {formatDate(expense.spent_at)}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <p className="text-sm font-medium">
                                                {formatAmount(expense.amount)}{' '}
                                                {
                                                    CURRENCY_LABEL[
                                                        expense.currency
                                                    ]
                                                }
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                ={' '}
                                                {formatAmount(
                                                    expense.usd_amount,
                                                )}{' '}
                                                USD
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
