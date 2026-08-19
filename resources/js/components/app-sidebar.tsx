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
import AppLogo from '@/components/app-logo';
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

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Panel admin',
        href: adminDashboard(),
        icon: Shield,
    },
    {
        title: 'Usuarios',
        href: adminUsersIndex(),
        icon: Users,
    },
    {
        title: 'Gastos',
        href: adminExpensesIndex(),
        icon: Receipt,
    },
    {
        title: 'Tasas',
        href: adminRatesIndex(),
        icon: Coins,
    },
    {
        title: 'Categorías',
        href: adminCategoriesIndex(),
        icon: Tag,
    },
    {
        title: 'Orígenes',
        href: adminSourcesIndex(),
        icon: Landmark,
    },
    {
        title: 'Auditoría',
        href: adminAuditIndex(),
        icon: History,
    },
    {
        title: 'Sistema',
        href: adminSystemIndex(),
        icon: Server,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;

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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
