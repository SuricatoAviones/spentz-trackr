import { router, usePage } from '@inertiajs/react';
import { Check, Globe } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { applyLocale } from '@/i18n';
import { cn } from '@/lib/utils';
import { update as updateLanguage } from '@/routes/language';

const LOCALES = ['es', 'en'] as const;

function useLanguage() {
    const { t, i18n } = useTranslation();
    const { locale: persistedLocale } = usePage<{ locale: string }>().props;

    // i18next is what the screen is actually rendering: it flips on click,
    // while the prop only catches up once the server confirms the preference.
    const locale = i18n.language || persistedLocale;

    const changeLanguage = (code: string): void => {
        if (code === locale) {
            return;
        }

        // Swap the dictionaries first so the whole tree re-renders right away,
        // then persist in the background without resetting the page.
        applyLocale(code);

        router.post(
            updateLanguage().url,
            { locale: code },
            { preserveScroll: true, preserveState: true },
        );
    };

    const label = (code: string): string =>
        code === 'es' ? t('settings.language_es') : t('settings.language_en');

    return { locale, changeLanguage, label, t };
}

export function LanguageSwitcher({
    compact = false,
    className,
}: {
    compact?: boolean;
    className?: string;
}) {
    const { locale, changeLanguage, label, t } = useLanguage();

    if (compact) {
        return (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        className="flex h-8 w-full items-center gap-2 rounded-md p-2 text-left text-sm ring-sidebar-ring outline-hidden transition-[width,height,padding] group-data-[collapsible=icon]:size-8! group-data-[collapsible=icon]:p-2! hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 active:bg-sidebar-accent"
                    >
                        <Globe className="size-4 shrink-0" />
                        <span className="truncate text-xs font-medium uppercase">
                            {locale}
                        </span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    side="right"
                    sideOffset={8}
                    className="w-40"
                >
                    <DropdownMenuLabel>
                        {t('settings.language_title')}
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {LOCALES.map((code) => (
                        <DropdownMenuItem
                            key={code}
                            onClick={() => changeLanguage(code)}
                        >
                            <Check
                                className={cn(
                                    'size-4',
                                    locale !== code && 'opacity-0',
                                )}
                            />
                            {label(code)}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    return (
        <div
            className={cn(
                'inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800',
                className,
            )}
        >
            {LOCALES.map((code) => (
                <button
                    key={code}
                    type="button"
                    onClick={() => changeLanguage(code)}
                    className={cn(
                        'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                        locale === code
                            ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                            : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                    )}
                >
                    <span className="text-sm">{label(code)}</span>
                </button>
            ))}
        </div>
    );
}
