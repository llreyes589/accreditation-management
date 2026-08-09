import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ClipboardCheck,
    FolderGit2,
    LayoutGrid,
    ListChecks,
    Search,
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
import checklistVersions from '@/routes/admin/checklists';
import inspections from '@/routes/inspections';
import completenessReviews from '@/routes/reviewer/completeness';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Checklist Administration',
        href: checklistVersions.index().url,
        icon: ListChecks,
    },
];

const reviewerNavItems: NavItem[] = [
    {
        title: 'Completeness Review',
        href: completenessReviews.dashboard().url,
        icon: ClipboardCheck,
    },
];

const inspectionsNavItems: NavItem[] = [
    {
        title: 'Inspections',
        href: inspections.index().url,
        icon: Search,
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
    const { auth } = usePage().props;
    const user = auth.user;
    const items = [
        ...mainNavItems,
        ...(user?.can_review_completeness ? reviewerNavItems : []),
        ...(user?.can_inspect ? inspectionsNavItems : []),
        ...(user?.can_administer_checklist ? adminNavItems : []),
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
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
