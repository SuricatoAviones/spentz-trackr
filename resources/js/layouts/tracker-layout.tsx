import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    ChartPie,
    LogOut,
    Plus,
    Settings,
    Shield,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { ajustes, dashboard, logout } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminUsersIndex } from '@/routes/admin/users';
import {
    create as expensesCreate,
    index as expensesIndex,
} from '@/routes/expenses';
import {
    create as incomesCreate,
    index as incomesIndex,
} from '@/routes/incomes';
import { index as reportsIndex } from '@/routes/reports';

export default function TrackerLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const { url } = usePage();
    const { auth } = usePage().props;

    const trackingType = auth.user.tracking_type;
    const showExpenses = trackingType !== 'income';
    const showIncomes = trackingType !== 'expenses';

    const createTarget: 'income' | 'expense' =
        url.startsWith('/incomes') && showIncomes
            ? 'income'
            : url.startsWith('/expenses') && showExpenses
              ? 'expense'
              : showIncomes
                ? 'income'
                : 'expense';

    const navItems = [
        { title: t('shell.tracker.nav_home'), href: dashboard, icon: ChartPie },
        ...(showExpenses
            ? [
                  {
                      title: t('shell.tracker.nav_expenses'),
                      href: expensesIndex,
                      icon: Wallet,
                  },
              ]
            : []),
        ...(showIncomes
            ? [
                  {
                      title: t('shell.tracker.nav_incomes'),
                      href: incomesIndex,
                      icon: TrendingUp,
                  },
              ]
            : []),
        {
            title: t('shell.tracker.nav_reports'),
            href: reportsIndex,
            icon: BarChart3,
        },
        {
            title: t('shell.tracker.nav_settings'),
            href: ajustes,
            icon: Settings,
        },
    ];

    function isActive(href: string): boolean {
        return url === href || url.startsWith(`${href}/`);
    }

    function logoutUser() {
        router.post(logout().url);
    }

    const initials = auth.user.name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return (
        <div className="flex min-h-screen w-full">
            <Head title={title} />

            <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-white/5 bg-[#0d1526] lg:flex">
                <div className="flex h-16 items-center gap-3 px-5">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 font-display text-lg font-extrabold text-primary-foreground">
                        S
                    </span>
                    <div className="min-w-0">
                        <p className="font-display text-sm leading-tight font-bold text-foreground">
                            Spentz Trackr
                        </p>
                        <p className="text-[10px] text-muted-foreground">
                            {t('shell.tracker.subtitle')}
                        </p>
                    </div>
                </div>

                <nav
                    className="mt-2 flex-1 space-y-1 overflow-y-auto px-3"
                    aria-label={t('shell.tracker.nav_aria')}
                >
                    <p className="px-3 pt-2 pb-1 text-[10px] font-bold tracking-widest text-muted-foreground/70 uppercase">
                        {t('shell.tracker.main_section')}
                    </p>
                    {navItems.map((item) => {
                        const href = item.href().url;
                        const active = isActive(href);

                        return (
                            <Link
                                key={item.title}
                                href={href}
                                className={`relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                                    active
                                        ? 'bg-emerald-500/15 text-emerald-400'
                                        : 'text-muted-foreground hover:bg-white/5 hover:text-foreground'
                                }`}
                            >
                                {active && (
                                    <span className="absolute top-1/2 left-0 h-5 w-1 -translate-y-1/2 rounded-r-full bg-emerald-400" />
                                )}
                                <item.icon
                                    className="size-4.5 shrink-0"
                                    strokeWidth={active ? 2.4 : 2}
                                />
                                {item.title}
                            </Link>
                        );
                    })}

                    {auth.user.is_admin && (
                        <>
                            <p className="px-3 pt-4 pb-1 text-[10px] font-bold tracking-widest text-muted-foreground/70 uppercase">
                                {t('shell.tracker.admin_section')}
                            </p>
                            <Link
                                href={adminDashboard().url}
                                className={`relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                                    isActive(adminDashboard().url)
                                        ? 'bg-emerald-500/15 text-emerald-400'
                                        : 'text-muted-foreground hover:bg-white/5 hover:text-foreground'
                                }`}
                            >
                                {isActive(adminDashboard().url) && (
                                    <span className="absolute top-1/2 left-0 h-5 w-1 -translate-y-1/2 rounded-r-full bg-emerald-400" />
                                )}
                                <Shield className="size-4.5 shrink-0" />
                                {t('shell.tracker.admin_panel')}
                            </Link>
                            <Link
                                href={adminUsersIndex().url}
                                className={`relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                                    isActive(adminUsersIndex().url)
                                        ? 'bg-emerald-500/15 text-emerald-400'
                                        : 'text-muted-foreground hover:bg-white/5 hover:text-foreground'
                                }`}
                            >
                                {isActive(adminUsersIndex().url) && (
                                    <span className="absolute top-1/2 left-0 h-5 w-1 -translate-y-1/2 rounded-r-full bg-emerald-400" />
                                )}
                                <Users className="size-4.5 shrink-0" />
                                {t('shell.tracker.users')}
                            </Link>
                        </>
                    )}
                </nav>

                <div className="border-t border-white/5 p-4">
                    <div className="flex items-center gap-3">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 text-xs font-bold text-primary-foreground">
                            {initials}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold text-foreground">
                                {auth.user.name}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {auth.user.email}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={logoutUser}
                            className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-white/5 hover:text-destructive"
                            aria-label={t('shell.tracker.logout_aria')}
                        >
                            <LogOut className="size-4" />
                        </button>
                    </div>
                </div>
            </aside>

            <div className="flex w-full flex-col lg:pl-64">
                <header className="sticky top-0 z-40 border-b border-border/60 bg-background/80 backdrop-blur-xl">
                    <div className="mx-auto flex h-16 w-full max-w-md items-center justify-between px-4 lg:max-w-6xl lg:px-8">
                        <div>
                            <h1 className="font-display text-lg font-bold text-foreground">
                                {title}
                            </h1>
                            {description !== undefined && (
                                <p className="text-xs text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                        <Link
                            href={
                                createTarget === 'income'
                                    ? incomesCreate().url
                                    : expensesCreate().url
                            }
                            className={`inline-flex items-center justify-center rounded-xl text-primary-foreground shadow-lg transition-transform active:scale-95 lg:h-10 lg:gap-2 lg:px-4 lg:text-sm lg:font-semibold ${
                                createTarget === 'income'
                                    ? 'bg-gradient-to-br from-blue-400 to-blue-600 shadow-blue-500/25'
                                    : 'bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-emerald-500/25'
                            }`}
                            aria-label={
                                createTarget === 'income'
                                    ? t('shell.tracker.new_income')
                                    : t('shell.tracker.new_expense')
                            }
                        >
                            <span className="flex size-9 items-center justify-center lg:size-auto">
                                <Plus className="size-4" strokeWidth={2.5} />
                            </span>
                            <span className="hidden lg:inline">
                                {createTarget === 'income'
                                    ? t('shell.tracker.new_income')
                                    : t('shell.tracker.new_expense')}
                            </span>
                        </Link>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-md flex-1 px-4 pt-4 pb-28 lg:max-w-6xl lg:px-8 lg:pb-12">
                    {children}
                </main>
            </div>

            <nav
                className="fixed inset-x-0 bottom-0 z-40 border-t border-white/5 bg-[#0b1220]/80 backdrop-blur-2xl lg:hidden"
                aria-label={t('shell.tracker.nav_aria')}
            >
                <div
                    className={`mx-auto grid w-full max-w-md ${
                        navItems.length >= 5 ? 'grid-cols-5' : 'grid-cols-4'
                    }`}
                >
                    {navItems.map((item) => {
                        const href = item.href().url;
                        const active = isActive(href);

                        return (
                            <Link
                                key={item.title}
                                href={href}
                                className="relative flex flex-col items-center gap-1 py-3"
                            >
                                <span
                                    className={`flex h-8 w-14 items-center justify-center rounded-full transition-colors ${
                                        active ? 'bg-emerald-500/15' : ''
                                    }`}
                                >
                                    <item.icon
                                        className={`size-5 transition-colors ${
                                            active
                                                ? 'text-emerald-400'
                                                : 'text-muted-foreground'
                                        }`}
                                        strokeWidth={active ? 2.4 : 2}
                                    />
                                </span>
                                <span
                                    className={`text-[10px] font-medium ${
                                        active
                                            ? 'text-emerald-400'
                                            : 'text-muted-foreground'
                                    }`}
                                >
                                    {item.title}
                                </span>
                                {active && (
                                    <span className="absolute -top-px h-0.5 w-8 rounded-full bg-emerald-400" />
                                )}
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </div>
    );
}
