<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChartCandlestick,
    ClipboardCheck,
    CreditCard,
    Dumbbell,
    HeartPulse,
    KanbanSquare,
    KeyRound,
    LayoutGrid,
    LineChart,
    ListChecks,
    PiggyBank,
    ShoppingCart,
    Sparkles,
    Sprout,
    Tag,
    Tags,
    TrendingUp,
    UtensilsCrossed,
    Wallet,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
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
import * as calendar from '@/routes/calendar';
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
        title: 'Finanza',
        icon: Wallet,
        items: [
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
                title: 'Carte',
                href: cards.index(),
                icon: CreditCard,
            },
            {
                title: 'Budget',
                href: budgets.index(),
                icon: PiggyBank,
            },
        ],
    },
    {
        title: 'Investimenti',
        icon: LineChart,
        items: [
            {
                title: 'Investimenti personali',
                href: investments.index(),
                icon: TrendingUp,
            },
            {
                title: 'Mercato',
                href: market.index(),
                icon: ChartCandlestick,
            },
            {
                title: 'Analisi aziendale',
                href: companyAnalyses.index(),
                icon: ClipboardCheck,
            },
        ],
    },
    {
        title: 'Produttività',
        icon: ListChecks,
        items: [
            {
                title: 'Task',
                href: tasks.index(),
                icon: KanbanSquare,
            },
            {
                title: 'Calendario',
                href: calendar.index(),
                icon: CalendarDays,
            },
            {
                title: 'Password',
                href: passwords.index(),
                icon: KeyRound,
            },
        ],
    },
    {
        title: 'Vita',
        icon: HeartPulse,
        items: [
            {
                title: 'Pasti',
                href: meals.index(),
                icon: UtensilsCrossed,
            },
            {
                title: 'Lista della spesa',
                href: shoppingLists.index(),
                icon: ShoppingCart,
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
        ],
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
            <NavMain :items="mainNavItems" label="Menu" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
