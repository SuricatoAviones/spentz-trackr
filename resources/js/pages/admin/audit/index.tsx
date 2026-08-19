import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ScrollText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

const ACTION_LABELS: Record<string, string> = {
    'user.updated': 'Usuario actualizado',
    'user.deleted': 'Usuario eliminado',
    'user.verified': 'Email verificado',
    'user.password_reset': 'Contraseña restablecida',
    'user.suspended': 'Usuario suspendido',
    'user.reactivated': 'Usuario reactivado',
    'expense.deleted': 'Gasto eliminado',
    'expenses.exported': 'Exportación CSV',
    'rate.updated': 'Tasa actualizada',
    'rate.synced': 'Sincronización de tasas',
    'category.updated': 'Categoría actualizada',
    'category.deleted': 'Categoría eliminada',
    'source.updated': 'Origen actualizado',
    'source.deleted': 'Origen eliminado',
    'backup.generated': 'Backup generado',
};

export default function AdminAuditIndex({
    actions,
}: {
    actions: ActionsPaginator;
}) {
    const actionLabel = (action: string): string =>
        ACTION_LABELS[action] ?? action;

    const formatDateTime = (value: string): string => {
        const date = new Date(value);

        return date.toLocaleDateString('es-VE', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }) + ' · ' + date.toLocaleTimeString('es-VE', {
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <>
            <Head title="Auditoría - Panel admin" />

            <div className="space-y-8 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">
                        Auditoría
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {actions.total} acciones registradas de
                        administradores.
                    </p>
                </div>

                <Card>
                    <CardContent className="px-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs tracking-wider text-muted-foreground uppercase">
                                        <th className="px-4 py-3 font-medium">
                                            Fecha
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            Administrador
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Acción
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium lg:table-cell">
                                            Objetivo
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
                                    Aún no hay acciones registradas.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {actions.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Página {actions.current_page} de{' '}
                            {actions.last_page}
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
                                    <ChevronLeft className="size-4" /> Anterior
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
                                    Siguiente{' '}
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