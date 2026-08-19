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
        if (
            confirm(
                `¿Eliminar al usuario "${user.name}"? Se borrarán todos sus gastos, categorías y orígenes.`,
            )
        ) {
            router.delete(usersDestroy({ user: user.id }).url);
        }
    }

    return (
        <>
            <Head title="Usuarios" />

            <div className="space-y-8 px-4 py-6 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight">
                            Usuarios
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {users.total} cuentas registradas.
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
                                placeholder="Buscar por nombre o email..."
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit">Buscar</Button>
                    </form>
                </div>

                <Card>
                    <CardContent className="px-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs tracking-wider text-muted-foreground uppercase">
                                        <th className="px-4 py-3 font-medium">
                                            Usuario
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            Rol
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            Verificado
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                            Gastos
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                            Total USD
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium lg:table-cell">
                                            Registro
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Acciones
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
                                                        Suspendido
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                {user.is_admin ? (
                                                    <Badge variant="secondary">
                                                        Admin
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        Usuario
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                {user.email_verified_at ? (
                                                    <Badge variant="default">
                                                        Sí
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        No
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
                                                        aria-label={`Editar ${user.name}`}
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
                                                        aria-label={`Eliminar ${user.name}`}
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
                                No se encontraron usuarios.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Página {users.current_page} de {users.last_page}
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
                                    <ChevronLeft className="size-4" /> Anterior
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
                                    Siguiente{' '}
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
                        <DialogTitle>Editar usuario</DialogTitle>
                        <DialogDescription>
                            Actualiza los datos de la cuenta.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitEdit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-name">Nombre</Label>
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
                            <Label htmlFor="edit-email">Email</Label>
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
                            <span className="text-sm">Administrador</span>
                        </label>

                        <Button
                            type="submit"
                            disabled={saving}
                            className="w-full"
                        >
                            {saving ? 'Guardando...' : 'Guardar cambios'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
