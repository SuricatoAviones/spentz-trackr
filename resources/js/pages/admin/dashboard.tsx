import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronRight,
    Receipt,
    Shield,
    UserCheck,
    Users,
} from 'lucide-react';
import { HorizontalBars } from '@/components/tracker/horizontal-bars';
import { TrendChart } from '@/components/tracker/trend-chart';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatAmount } from '@/lib/format';
import { index as usersIndex, show as userShow } from '@/routes/admin/users';

const USER_PALETTE = ['#10B981', '#3B82F6', '#F59E0B', '#8B5CF6', '#EC4899'];

type Stats = {
    total_users: number;
    new_users_month: number;
    verified_users: number;
    admin_users: number;
    active_users: number;
    total_expenses: number;
    total_usd: number;
    monthly_expenses: number;
};

type MonthPoint = {
    month: string;
    total: number;
};

type TopCategory = {
    name: string;
    color: string;
    total: number;
    percent: number;
};

type TopUser = {
    id: number | null;
    name: string;
    total: number;
    percent: number;
};

type RecentUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    email_verified_at: string | null;
    expenses_count: number;
    created_at: string;
};

export default function AdminDashboard({
    stats,
    monthlyTrend,
    topCategories,
    topUsers,
    recentUsers,
}: {
    stats: Stats;
    monthlyTrend: MonthPoint[];
    topCategories: TopCategory[];
    topUsers: TopUser[];
    recentUsers: RecentUser[];
}) {
    const statCards = [
        {
            label: 'Usuarios totales',
            value: String(stats.total_users),
            icon: Users,
            accent: 'text-emerald-400',
        },
        {
            label: 'Nuevos este mes',
            value: String(stats.new_users_month),
            icon: ArrowUpRight,
            accent: 'text-sky-400',
        },
        {
            label: 'Verificados',
            value: String(stats.verified_users),
            icon: UserCheck,
            accent: 'text-emerald-400',
        },
        {
            label: 'Administradores',
            value: String(stats.admin_users),
            icon: Shield,
            accent: 'text-amber-400',
        },
        {
            label: 'Usuarios activos',
            value: String(stats.active_users),
            icon: UserCheck,
            accent: 'text-emerald-400',
        },
        {
            label: 'Gastos totales',
            value: String(stats.total_expenses),
            icon: Receipt,
            accent: 'text-sky-400',
        },
        {
            label: 'Total gastado (USD)',
            value: formatAmount(stats.total_usd),
            icon: Receipt,
            accent: 'text-emerald-400',
            wide: true,
        },
    ];

    const initials = (name: string): string =>
        name
            .split(' ')
            .map((part) => part[0])
            .join('')
            .slice(0, 2)
            .toUpperCase();

    return (
        <>
            <Head title="Panel admin" />

            <div className="space-y-8 px-4 py-6 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 className="font-display text-2xl font-bold tracking-tight">
                            Panel admin
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Resumen global de la plataforma.
                        </p>
                    </div>
                    <span className="inline-flex w-fit items-center gap-2 rounded-full bg-surface-low px-3.5 py-1.5 text-xs font-semibold text-muted-foreground">
                        <span className="size-1.5 rounded-full bg-emerald-400" />
                        {stats.monthly_expenses} gastos este mes
                    </span>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {statCards.map((stat) => (
                        <Card
                            key={stat.label}
                            className={stat.wide ? 'lg:col-span-2' : undefined}
                        >
                            <CardContent className="flex items-center gap-4 p-5">
                                <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-surface-high">
                                    <stat.icon
                                        className={`size-5 ${stat.accent}`}
                                    />
                                </div>
                                <div className="min-w-0">
                                    <p className="truncate text-xs font-medium text-muted-foreground">
                                        {stat.label}
                                    </p>
                                    <p className="mt-0.5 truncate font-display text-2xl font-bold tracking-tight tabular-nums">
                                        {stat.value}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader className="px-5 pt-5 sm:px-6">
                        <CardTitle className="text-base">
                            Tendencia 12 meses (USD)
                        </CardTitle>
                        <CardDescription>
                            Gasto global por mes en el año actual.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-5 pb-5 sm:px-6">
                        <TrendChart data={monthlyTrend} height={180} />
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="px-5 pt-5 sm:px-6">
                            <CardTitle className="text-base">
                                Top categorías
                            </CardTitle>
                            <CardDescription>
                                Categorías con mayor gasto en USD.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-5 pb-5 sm:px-6">
                            {topCategories.length === 0 ? (
                                <p className="py-10 text-center text-sm text-muted-foreground">
                                    Sin gastos registrados.
                                </p>
                            ) : (
                                <HorizontalBars data={topCategories} />
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="px-5 pt-5 sm:px-6">
                            <CardTitle className="text-base">
                                Top usuarios
                            </CardTitle>
                            <CardDescription>
                                Usuarios con mayor gasto total en USD.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-5 pb-5 sm:px-6">
                            {topUsers.length === 0 ? (
                                <p className="py-10 text-center text-sm text-muted-foreground">
                                    Sin gastos registrados.
                                </p>
                            ) : (
                                <HorizontalBars
                                    data={topUsers.map((user, index) => ({
                                        name: user.name,
                                        color: USER_PALETTE[index % USER_PALETTE.length],
                                        total: user.total,
                                        percent: user.percent,
                                    }))}
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 px-5 pt-5 sm:px-6">
                        <div>
                            <CardTitle className="text-base">
                                Usuarios recientes
                            </CardTitle>
                            <CardDescription>
                                Las últimas cuentas registradas.
                            </CardDescription>
                        </div>
                        <Link
                            href={usersIndex().url}
                            className="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary hover:text-primary/80"
                        >
                            Ver todos
                            <ChevronRight className="size-3.5" />
                        </Link>
                    </CardHeader>
                    <CardContent className="px-2 pb-2 sm:px-3">
                        <div className="divide-y">
                            {recentUsers.map((user) => (
                                <Link
                                    key={user.id}
                                    href={userShow({ user: user.id }).url}
                                    className="flex items-center gap-4 rounded-lg px-3 py-4 transition-colors hover:bg-accent"
                                >
                                    <Avatar className="size-9 shrink-0">
                                        <AvatarFallback className="bg-surface-high text-xs">
                                            {initials(user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0 flex-1">
                                        <p className="flex items-center gap-2 truncate text-sm font-medium">
                                            {user.name}
                                            {user.is_admin && (
                                                <Badge
                                                    variant="secondary"
                                                    className="shrink-0"
                                                >
                                                    Admin
                                                </Badge>
                                            )}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {user.email}
                                        </p>
                                    </div>
                                    <div className="hidden shrink-0 text-right text-xs text-muted-foreground sm:block">
                                        <p className="font-medium text-foreground">
                                            {user.expenses_count} gastos
                                        </p>
                                        <p>Registrado el {user.created_at}</p>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}