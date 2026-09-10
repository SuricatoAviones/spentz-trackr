import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Menu } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { LanguageSwitcher } from '@/components/language-switcher';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIsNavItemActive, useNavSections } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import type { Auth } from '@/types';

/**
 * Full navigation drawer for small viewports — every section (main, manage,
 * admin) plus the user identity, language and logout. Rendered behind a trigger
 * you pass as `children`.
 */
export function MobileMenu({ children }: { children: ReactNode }) {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth: Auth }>().props;
    const sections = useNavSections();
    const isActive = useIsNavItemActive();

    const initials = auth.user.name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return (
        <Sheet>
            <SheetTrigger asChild>{children}</SheetTrigger>
            <SheetContent
                side="right"
                className="flex w-[86%] max-w-xs flex-col gap-0 border-white/10 bg-[#0d1526] p-0 text-foreground"
            >
                <SheetHeader className="flex-row items-center gap-3 space-y-0 px-5 py-4 text-left">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 font-display text-lg font-extrabold text-primary-foreground">
                        S
                    </span>
                    <div className="min-w-0">
                        <SheetTitle className="font-display text-sm leading-tight font-bold text-foreground">
                            Spentz Trackr
                        </SheetTitle>
                        <SheetDescription className="text-[10px] text-muted-foreground">
                            {t('shell.tracker.subtitle')}
                        </SheetDescription>
                    </div>
                </SheetHeader>

                <nav
                    className="flex-1 space-y-1 overflow-y-auto px-3 pb-4"
                    aria-label={t('shell.nav.menu_aria')}
                >
                    {sections.map((section) => (
                        <div key={section.id}>
                            <p className="px-3 pt-4 pb-1 text-[10px] font-bold tracking-widest text-muted-foreground/70 uppercase">
                                {section.label}
                            </p>
                            {section.items.map((item) => {
                                const active = isActive(item.href);

                                return (
                                    <SheetClose asChild key={item.href}>
                                        <Link
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
                                    </SheetClose>
                                );
                            })}
                        </div>
                    ))}
                </nav>

                <div className="space-y-3 border-t border-white/10 p-4">
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
                    </div>
                    <div className="flex items-center justify-between gap-2">
                        <LanguageSwitcher />
                        <SheetClose asChild>
                            <button
                                type="button"
                                onClick={() => router.post(logout().url)}
                                className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-white/5 hover:text-destructive"
                            >
                                <LogOut className="size-4" />
                                {t('shell.user_menu.logout')}
                            </button>
                        </SheetClose>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

/**
 * Default "More" trigger styled for the tracker bottom bar.
 */
export function MobileMenuTrigger() {
    const { t } = useTranslation();

    return (
        <MobileMenu>
            <button
                type="button"
                className="relative flex flex-col items-center gap-1 py-3"
            >
                <span className="flex h-8 w-14 items-center justify-center rounded-full">
                    <Menu className="size-5 text-muted-foreground" />
                </span>
                <span className="text-[10px] font-medium text-muted-foreground">
                    {t('shell.nav.more')}
                </span>
            </button>
        </MobileMenu>
    );
}
