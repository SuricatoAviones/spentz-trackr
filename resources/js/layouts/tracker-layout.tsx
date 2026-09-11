import { Head, Link, router, usePage } from '@inertiajs/react';
import { LogOut, Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { MobileMenuTrigger } from '@/components/mobile-menu';
import {
    useIsNavItemActive,
    useNavSections,
    usePrimaryNav,
} from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { create as expensesCreate } from '@/routes/expenses';
import { create as incomesCreate } from '@/routes/incomes';

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

    const sections = useNavSections();
    const primaryNav = usePrimaryNav();
    const isActive = useIsNavItemActive();

    const trackingType = auth.user.tracking_type;
    const showExpenses = trackingType !== 'income';
    const showIncomes = trackingType !== 'expenses';

    // Quick-create shortcuts belong to the tracking experience, not to the
    // admin panel or the account settings screens.
    const showQuickActions =
        !url.startsWith('/admin') && !url.startsWith('/settings');

    const quickActions = [
        showExpenses && {
            key: 'expense' as const,
            href: expensesCreate().url,
            label: t('shell.tracker.new_expense'),
            className:
                'bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-emerald-500/25',
        },
        showIncomes && {
            key: 'income' as const,
            href: incomesCreate().url,
            label: t('shell.tracker.new_income'),
            className:
                'bg-gradient-to-br from-blue-400 to-blue-600 shadow-blue-500/25',
        },
    ].filter((action): action is Exclude<typeof action, false> =>
        Boolean(action),
    );

    function logoutUser() {
        router.post(logout().url);
    }

    const initials = auth.user.name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    // Bottom bar: primary destinations + the "More" button.
    const bottomBarColumns = primaryNav.length + 1;

    return (
        <div className="flex min-h-screen w-full">
            <Head title={title} />

            <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-white/5 bg-[#0d1526] md:flex">
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
                    className="mt-2 flex-1 space-y-1 overflow-y-auto px-3 pb-4"
                    aria-label={t('shell.tracker.nav_aria')}
                >
                    {sections.map((section) => (
                        <div key={section.id}>
                            <p className="px-3 pt-3 pb-1 text-[10px] font-bold tracking-widest text-muted-foreground/70 uppercase">
                                {section.label}
                            </p>
                            {section.items.map((item) => {
                                const active = isActive(item.href);

                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={cn(
                                            'relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            active
                                                ? 'bg-emerald-500/15 text-emerald-400'
                                                : 'text-muted-foreground hover:bg-white/5 hover:text-foreground',
                                        )}
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
                        </div>
                    ))}
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

            <div className="flex w-full flex-col md:pl-64">
                <header className="sticky top-0 z-40 border-b border-border/60 bg-background/80 backdrop-blur-xl">
                    <div className="mx-auto flex h-16 w-full max-w-md items-center justify-between gap-3 px-4 md:max-w-3xl md:px-6 lg:max-w-6xl lg:px-8">
                        <div className="min-w-0">
                            <h1 className="truncate font-display text-lg font-bold text-foreground">
                                {title}
                            </h1>
                            {description && (
                                <p className="truncate text-xs text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                        {showQuickActions && quickActions.length > 0 && (
                            <div className="flex shrink-0 items-center gap-2">
                                {quickActions.map((action) => (
                                    <Link
                                        key={action.key}
                                        href={action.href}
                                        className={cn(
                                            'inline-flex shrink-0 items-center justify-center rounded-xl text-primary-foreground shadow-lg transition-transform active:scale-95 lg:h-10 lg:gap-2 lg:px-4 lg:text-sm lg:font-semibold',
                                            action.className,
                                        )}
                                        aria-label={action.label}
                                    >
                                        <span className="flex size-9 items-center justify-center lg:size-auto">
                                            <Plus
                                                className="size-4"
                                                strokeWidth={2.5}
                                            />
                                        </span>
                                        <span className="hidden lg:inline">
                                            {action.label}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </header>

                <main className="mx-auto w-full max-w-md flex-1 px-4 pt-4 pb-28 md:max-w-3xl md:px-6 md:pb-12 lg:max-w-6xl lg:px-8">
                    {children}
                </main>
            </div>

            <nav
                className="fixed inset-x-0 bottom-0 z-40 border-t border-white/5 bg-[#0b1220]/80 backdrop-blur-2xl md:hidden"
                aria-label={t('shell.tracker.nav_aria')}
            >
                <div
                    className="mx-auto grid w-full max-w-md"
                    style={{
                        gridTemplateColumns: `repeat(${bottomBarColumns}, minmax(0, 1fr))`,
                    }}
                >
                    {primaryNav.map((item) => {
                        const active = isActive(item.href);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className="relative flex flex-col items-center gap-1 py-3"
                            >
                                <span
                                    className={cn(
                                        'flex h-8 w-14 items-center justify-center rounded-full transition-colors',
                                        active && 'bg-emerald-500/15',
                                    )}
                                >
                                    <item.icon
                                        className={cn(
                                            'size-5 transition-colors',
                                            active
                                                ? 'text-emerald-400'
                                                : 'text-muted-foreground',
                                        )}
                                        strokeWidth={active ? 2.4 : 2}
                                    />
                                </span>
                                <span
                                    className={cn(
                                        'text-[10px] font-medium',
                                        active
                                            ? 'text-emerald-400'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {item.title}
                                </span>
                                {active && (
                                    <span className="absolute -top-px h-0.5 w-8 rounded-full bg-emerald-400" />
                                )}
                            </Link>
                        );
                    })}

                    <MobileMenuTrigger />
                </div>
            </nav>
        </div>
    );
}
