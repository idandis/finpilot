<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarRange,
    CreditCard,
    FolderGit2,
    LayoutGrid,
    PiggyBank,
    Tag,
    Tags,
    TrendingUp,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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
import * as budgets from '@/routes/budgets';
import * as cards from '@/routes/cards';
import * as categories from '@/routes/categories';
import * as categoryRules from '@/routes/category-rules';
import * as investments from '@/routes/investments';
import * as overview from '@/routes/overview';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Panoramica',
        href: overview.index(),
        icon: CalendarRange,
    },
    {
        title: 'Carte',
        href: cards.index(),
        icon: CreditCard,
    },
    {
        title: 'Categorie',
        href: categories.index(),
        icon: Tag,
    },
    {
        title: 'Regole categorie',
        href: categoryRules.index(),
        icon: Tags,
    },
    {
        title: 'Budget',
        href: budgets.index(),
        icon: PiggyBank,
    },
    {
        title: 'Investimenti',
        href: investments.index(),
        icon: TrendingUp,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
