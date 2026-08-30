<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import NotificationsMenu from '@/components/NotificationsMenu.vue';
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
import { useSidebarModules } from '@/composables/useSidebarModules';
import { mainNavItems as configurableNavItems } from '@/lib/sidebar-nav';
import { dashboard } from '@/routes';
import * as aiChat from '@/routes/ai-chat';
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
    ...configurableNavItems,
];

const { isModuleHidden } = useSidebarModules();

// Groups (and their sub-items) the user turned off in Impostazioni ›
// Sidebar don't render at all - a group left with zero visible children
// after filtering is dropped too, since NavMain can't render an empty
// collapsible.
const visibleNavItems = computed<NavItem[]>(() =>
    mainNavItems
        .filter((item) => !isModuleHidden(item.key))
        .map((item) =>
            item.items
                ? {
                      ...item,
                      items: item.items.filter(
                          (sub) => !isModuleHidden(sub.key),
                      ),
                  }
                : item,
        )
        .filter((item) => !item.items || item.items.length > 0),
);
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
            <NavMain :items="visibleNavItems" label="Menu" />
        </SidebarContent>

        <SidebarFooter>
            <NotificationsMenu />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
