import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Coins,
    FolderGit2,
    History,
    Landmark,
    LayoutGrid,
    Receipt,
    Server,
    Shield,
    Tag,
    Users,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import AppLogo from '@/components/app-logo';
import { LanguageSwitcher } from '@/components/language-switcher';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminAuditIndex } from '@/routes/admin/audit';
import { index as adminCategoriesIndex } from '@/routes/admin/categories';
import { index as adminExpensesIndex } from '@/routes/admin/expenses';
import { index as adminRatesIndex } from '@/routes/admin/rates';
import { index as adminSourcesIndex } from '@/routes/admin/sources';
import { index as adminSystemIndex } from '@/routes/admin/system';
import { index as adminUsersIndex } from '@/routes/admin/users';
import type { Auth, NavItem } from '@/types';

export function AppSidebar() {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth: Auth }>().props;

    const mainNavItems: NavItem[] = [
        {
            title: t('shell.sidebar.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    const adminNavItems: NavItem[] = [
        {
            title: t('shell.sidebar.admin_panel'),
            href: adminDashboard(),
            icon: Shield,
        },
        {
            title: t('shell.sidebar.users'),
            href: adminUsersIndex(),
            icon: Users,
        },
        {
            title: t('shell.sidebar.expenses'),
            href: adminExpensesIndex(),
            icon: Receipt,
        },
        {
            title: t('shell.sidebar.rates'),
            href: adminRatesIndex(),
            icon: Coins,
        },
        {
            title: t('shell.sidebar.categories'),
            href: adminCategoriesIndex(),
            icon: Tag,
        },
        {
            title: t('shell.sidebar.sources'),
            href: adminSourcesIndex(),
            icon: Landmark,
        },
        {
            title: t('shell.sidebar.audit'),
            href: adminAuditIndex(),
            icon: History,
        },
        {
            title: t('shell.sidebar.system'),
            href: adminSystemIndex(),
            icon: Server,
        },
    ];

    const footerNavItems: NavItem[] = [
        {
            title: t('shell.sidebar.repository'),
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: t('shell.sidebar.documentation'),
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {auth.user.is_admin && <NavMain items={adminNavItems} />}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <SidebarMenu>
                    <SidebarMenuItem>
                        <LanguageSwitcher compact />
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
