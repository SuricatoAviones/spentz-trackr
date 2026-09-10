import { Head, router, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ScrollText } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { getLocale } from '@/lib/format';
import { index as auditIndex } from '@/routes/admin/audit';

type AuditAction = {
    id: number;
    action: string;
    created_at: string;
    admin: {
        id: number;
        name: string;
        email: string;
    } | null;
    target: string | null;
};

type ActionsPaginator = {
    data: AuditAction[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
};

const ACTION_KEYS: Record<string, string> = {
    'user.updated': 'admin:audit.action_user_updated',
    'user.deleted': 'admin:audit.action_user_deleted',
    'user.verified': 'admin:audit.action_user_verified',
    'user.password_reset': 'admin:audit.action_user_password_reset',
    'user.suspended': 'admin:audit.action_user_suspended',
    'user.reactivated': 'admin:audit.action_user_reactivated',
    'expense.deleted': 'admin:audit.action_expense_deleted',
    'expenses.exported': 'admin:audit.action_expenses_exported',
    'rate.updated': 'admin:audit.action_rate_updated',
    'rate.synced': 'admin:audit.action_rate_synced',
    'category.updated': 'admin:audit.action_category_updated',
    'category.deleted': 'admin:audit.action_category_deleted',
    'source.updated': 'admin:audit.action_source_updated',
    'source.deleted': 'admin:audit.action_source_deleted',
    'backup.generated': 'admin:audit.action_backup_generated',
};

export default function AdminAuditIndex({
    actions,
}: {
    actions: ActionsPaginator;
}) {
    const { t } = useTranslation();

    setLayoutProps({
        title: t('admin:audit.title'),
        description: t('admin:audit.total', { count: actions.total }),
    });

    const actionLabel = (action: string): string =>
        t(ACTION_KEYS[action] ?? action);

    const formatDateTime = (value: string): string => {
        const date = new Date(value);

        return (
            date.toLocaleDateString(getLocale(), {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            }) +
            ' · ' +
            date.toLocaleTimeString(getLocale(), {
                hour: '2-digit',
                minute: '2-digit',
            })
        );
    };

    return (
        <>
            <Head title={t('admin:audit.title')} />

            <div className="space-y-8">
                <Card>
                    <CardContent className="px-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs tracking-wider text-muted-foreground uppercase">
                                        <th className="px-4 py-3 font-medium">
                                            {t('admin:expenses.col_date')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            {t('admin:audit.col_admin')}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {t('admin:audit.col_action')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium lg:table-cell">
                                            {t('admin:audit.col_target')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {actions.data.map((action) => (
                                        <tr
                                            key={action.id}
                                            className="hover:bg-accent/50"
                                        >
                                            <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                {formatDateTime(
                                                    action.created_at,
                                                )}
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                <span className="block max-w-48 truncate font-medium">
                                                    {action.admin?.name}
                                                </span>
                                                <span className="block max-w-48 truncate text-xs text-muted-foreground">
                                                    {action.admin?.email}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant="outline">
                                                    {actionLabel(action.action)}
                                                </Badge>
                                            </td>
                                            <td className="hidden max-w-56 truncate px-4 py-3 lg:table-cell">
                                                {action.target ?? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {actions.data.length === 0 && (
                            <div className="flex flex-col items-center gap-2 px-4 py-12 text-center">
                                <ScrollText className="size-8 text-muted-foreground" />
                                <p className="text-sm text-muted-foreground">
                                    {t('admin:audit.empty')}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {actions.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            {t('admin:users.page_of', {
                                current: actions.current_page,
                                total: actions.last_page,
                            })}
                        </p>
                        <div className="flex gap-2">
                            {actions.current_page > 1 && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(
                                            auditIndex().url,
                                            {
                                                page: actions.current_page - 1,
                                            },
                                            { preserveState: true },
                                        )
                                    }
                                >
                                    <ChevronLeft className="size-4" />{' '}
                                    {t('admin:users.prev')}
                                </Button>
                            )}
                            {actions.current_page < actions.last_page && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(
                                            auditIndex().url,
                                            {
                                                page: actions.current_page + 1,
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
        </>
    );
}
