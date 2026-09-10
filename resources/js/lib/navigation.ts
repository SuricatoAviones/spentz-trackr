import { usePage } from '@inertiajs/react';
import {
    BarChart3,
    ChartPie,
    Coins,
    History,
    Landmark,
    PiggyBank,
    Receipt,
    Repeat,
    Server,
    Settings,
    Shield,
    Tag,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ajustes, dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminAuditIndex } from '@/routes/admin/audit';
import { index as adminCategoriesIndex } from '@/routes/admin/categories';
import { index as adminExpensesIndex } from '@/routes/admin/expenses';
import { index as adminRatesIndex } from '@/routes/admin/rates';
import { index as adminSourcesIndex } from '@/routes/admin/sources';
import { index as adminSystemIndex } from '@/routes/admin/system';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as categoriesIndex } from '@/routes/categories';
import { index as expensesIndex } from '@/routes/expenses';
import { index as incomesIndex } from '@/routes/incomes';
import { index as recurringPaymentsIndex } from '@/routes/recurring-payments';
import { index as reportsIndex } from '@/routes/reports';
import { index as savingsGoalsIndex } from '@/routes/savings-goals';
import { index as sourcesIndex } from '@/routes/sources';
import type { Auth } from '@/types';

export type NavLink = {
    title: string;
    href: string;
    icon: LucideIcon;
};

export type NavSection = {
    id: string;
    label: string;
    items: NavLink[];
};

/**
 * The complete, grouped navigation for the authenticated area — shared by every
 * layout (tracker sidebar + bottom sheet, and the shadcn app sidebar) so the
 * whole menu is reachable from every screen and on every viewport.
 */
export function useNavSections(): NavSection[] {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth: Auth }>().props;

    const trackingType = auth.user.tracking_type ?? 'both';
    const showExpenses = trackingType !== 'income';
    const showIncomes = trackingType !== 'expenses';

    const sections: NavSection[] = [
        {
            id: 'main',
            label: t('shell.tracker.main_section'),
            items: [
                {
                    title: t('shell.tracker.nav_home'),
                    href: dashboard().url,
                    icon: ChartPie,
                },
                ...(showExpenses
                    ? [
                          {
                              title: t('shell.tracker.nav_expenses'),
                              href: expensesIndex().url,
                              icon: Wallet,
                          },
                      ]
                    : []),
                ...(showIncomes
                    ? [
                          {
                              title: t('shell.tracker.nav_incomes'),
                              href: incomesIndex().url,
                              icon: TrendingUp,
                          },
                      ]
                    : []),
                {
                    title: t('shell.tracker.nav_reports'),
                    href: reportsIndex().url,
                    icon: BarChart3,
                },
            ],
        },
        {
            id: 'manage',
            label: t('shell.nav.manage_section'),
            items: [
                {
                    title: t('shell.sidebar.savings_goals'),
                    href: savingsGoalsIndex().url,
                    icon: PiggyBank,
                },
                {
                    title: t('shell.sidebar.recurring_payments'),
                    href: recurringPaymentsIndex().url,
                    icon: Repeat,
                },
                {
                    title: t('shell.sidebar.categories'),
                    href: categoriesIndex().url,
                    icon: Tag,
                },
                {
                    title: t('shell.sidebar.sources'),
                    href: sourcesIndex().url,
                    icon: Landmark,
                },
                {
                    title: t('shell.tracker.nav_settings'),
                    href: ajustes().url,
                    icon: Settings,
                },
            ],
        },
    ];

    if (auth.user.is_admin) {
        sections.push({
            id: 'admin',
            label: t('shell.tracker.admin_section'),
            items: [
                {
                    title: t('shell.sidebar.admin_panel'),
                    href: adminDashboard().url,
                    icon: Shield,
                },
                {
                    title: t('shell.sidebar.users'),
                    href: adminUsersIndex().url,
                    icon: Users,
                },
                {
                    title: t('shell.sidebar.expenses'),
                    href: adminExpensesIndex().url,
                    icon: Receipt,
                },
                {
                    title: t('shell.sidebar.rates'),
                    href: adminRatesIndex().url,
                    icon: Coins,
                },
                {
                    title: t('shell.sidebar.categories'),
                    href: adminCategoriesIndex().url,
                    icon: Tag,
                },
                {
                    title: t('shell.sidebar.sources'),
                    href: adminSourcesIndex().url,
                    icon: Landmark,
                },
                {
                    title: t('shell.sidebar.audit'),
                    href: adminAuditIndex().url,
                    icon: History,
                },
                {
                    title: t('shell.sidebar.system'),
                    href: adminSystemIndex().url,
                    icon: Server,
                },
            ],
        });
    }

    return sections;
}

/**
 * The handful of destinations that stay on the mobile bottom bar; everything
 * else lives behind the "More" button which opens the full menu.
 */
export function usePrimaryNav(): NavLink[] {
    return useNavSections()[0].items;
}

/**
 * Highlight the nav item that best matches the current path: an exact match
 * wins outright (so `/admin` doesn't stay lit on `/admin/users`); otherwise the
 * longest matching prefix wins (so `/expenses/nuevo` lights up "Gastos").
 */
export function useIsNavItemActive(): (href: string) => boolean {
    const { url } = usePage();
    const path = url.split('?')[0];
    const hrefs = useNavSections().flatMap((section) =>
        section.items.map((item) => item.href),
    );

    const exact = hrefs.find((href) => href === path);

    if (exact !== undefined) {
        return (href) => href === exact;
    }

    const prefixMatches = hrefs.filter((href) => path.startsWith(`${href}/`));
    const longest = prefixMatches.reduce(
        (best, href) => (href.length > best.length ? href : best),
        '',
    );

    return (href) => href === longest && longest !== '';
}
