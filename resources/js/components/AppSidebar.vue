<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChartCandlestick,
    ClipboardCheck,
    CreditCard,
    KanbanSquare,
    KeyRound,
    LayoutGrid,
    PiggyBank,
    ShoppingCart,
    Dumbbell,
    Sparkles,
    Sprout,
    Tag,
    Tags,
    TrendingUp,
    UtensilsCrossed,
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
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import * as aiChat from '@/routes/ai-chat';
import * as budgets from '@/routes/budgets';
import * as cards from '@/routes/cards';
import * as categories from '@/routes/categories';
import * as categoryRules from '@/routes/category-rules';
import * as companyAnalyses from '@/routes/company-analyses';
import * as investments from '@/routes/investments';
import * as life from '@/routes/life';
import * as market from '@/routes/market';
import * as meals from '@/routes/meals';
import * as passwords from '@/routes/passwords';
import * as shoppingLists from '@/routes/shopping-lists';
import * as tasks from '@/routes/tasks';
import * as workouts from '@/routes/workouts';
import type { NavItem } from '@/types';

const aiNavItems: NavItem[] = [
    {
        title: 'AI',
        href: aiChat.index(),
        icon: Sparkles,
    },
];

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Carte',
        href: cards.index(),
        icon: CreditCard,
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
    {
        title: 'Mercato',
        href: market.index(),
        icon: ChartCandlestick,
    },
    {
        title: 'Analisi aziende',
        href: companyAnalyses.index(),
        icon: ClipboardCheck,
    },
];

const managementNavItems: NavItem[] = [
    {
        title: 'Task',
        href: tasks.index(),
        icon: KanbanSquare,
    },
    {
        title: 'Password',
        href: passwords.index(),
        icon: KeyRound,
    },
    {
        title: 'Lista della spesa',
        href: shoppingLists.index(),
        icon: ShoppingCart,
    },
    {
        title: 'Pasti',
        href: meals.index(),
        icon: UtensilsCrossed,
    },
    {
        title: 'Allenamenti',
        href: workouts.index(),
        icon: Dumbbell,
    },
    {
        title: 'Vita',
        href: life.index(),
        icon: Sprout,
    },
];

const footerNavItems: NavItem[] = [
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
            <NavMain :items="aiNavItems" label="Assistente" />
            <SidebarSeparator />
            <NavMain :items="mainNavItems" label="Finanza" />
            <SidebarSeparator />
            <NavMain :items="managementNavItems" label="Gestione" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
