<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    CheckCheck,
    KanbanSquare,
    ShoppingBasket,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import * as notificationRoutes from '@/routes/notifications';
import type { AppNotification } from '@/types';

/**
 * The bell above the user in the sidebar: everything that happened on a
 * list, a board or a meal plan shared with somebody else. Its contents ride
 * along with every page as a shared Inertia prop, so there is nothing to
 * fetch here.
 */
const page = usePage();
const { isMobile, state } = useSidebar();

const feed = computed(() => page.props.notifications);
const items = computed<AppNotification[]>(() => feed.value?.items ?? []);
const unread = computed(() => feed.value?.unread ?? 0);

const KIND_ICONS = {
    shopping_list: ShoppingBasket,
    task_board: KanbanSquare,
    meal_plan: CalendarDays,
} as const;

function iconFor(notification: AppNotification) {
    return notification.resource
        ? (KIND_ICONS[notification.resource.kind] ?? Bell)
        : Bell;
}

// "3 minuti fa", "ieri" - the browser's own wording, no date library.
const relativeTime = new Intl.RelativeTimeFormat('it', { numeric: 'auto' });

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 365 * 24 * 60 * 60],
    ['month', 30 * 24 * 60 * 60],
    ['day', 24 * 60 * 60],
    ['hour', 60 * 60],
    ['minute', 60],
];

function timeAgo(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);

    for (const [unit, size] of UNITS) {
        if (seconds >= size) {
            return relativeTime.format(-Math.floor(seconds / size), unit);
        }
    }

    return 'adesso';
}

// Reading one and going where it points: the visit is what refreshes the
// bell, so the two are chained rather than fired together.
function open(notification: AppNotification) {
    const target = notification.resource?.url;

    if (!notification.read) {
        router.patch(
            notificationRoutes.read(notification.id).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => target && router.visit(target),
            },
        );

        return;
    }

    if (target) {
        router.visit(target);
    }
}

function markAllRead() {
    router.patch(
        notificationRoutes.readAll().url,
        {},
        { preserveScroll: true },
    );
}

function clearAll() {
    router.delete(notificationRoutes.destroyAll().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        class="relative"
                        :tooltip="
                            unread > 0
                                ? `${unread} notifiche da leggere`
                                : 'Notifiche'
                        "
                        data-test="notifications-button"
                    >
                        <Bell />
                        <span>Notifiche</span>
                        <span
                            v-if="unread > 0"
                            class="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-semibold text-primary-foreground group-data-[collapsible=icon]:absolute group-data-[collapsible=icon]:top-0.5 group-data-[collapsible=icon]:right-0.5 group-data-[collapsible=icon]:h-3.5 group-data-[collapsible=icon]:min-w-3.5 group-data-[collapsible=icon]:px-0"
                        >
                            {{ unread > 9 ? '9+' : unread }}
                        </span>
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-80 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'right'
                              : 'top'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <DropdownMenuLabel
                        class="flex items-center justify-between gap-2 font-normal"
                    >
                        <span class="text-sm font-medium">Notifiche</span>
                        <span class="flex items-center gap-0.5">
                            <button
                                v-if="unread > 0"
                                type="button"
                                class="rounded-sm p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                title="Segna tutte come lette"
                                @click.stop.prevent="markAllRead"
                            >
                                <CheckCheck class="size-4" />
                            </button>
                            <button
                                v-if="items.length > 0"
                                type="button"
                                class="rounded-sm p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                title="Svuota le notifiche"
                                @click.stop.prevent="clearAll"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />

                    <p
                        v-if="items.length === 0"
                        class="px-2 py-6 text-center text-xs text-muted-foreground"
                    >
                        Nessuna notifica. Qui finisce quello che gli altri fanno
                        sulle liste, board e pasti che condividete.
                    </p>

                    <div v-else class="max-h-96 overflow-y-auto">
                        <button
                            v-for="notification in items"
                            :key="notification.id"
                            type="button"
                            class="flex w-full items-start gap-2 rounded-sm px-2 py-2 text-left transition hover:bg-muted"
                            :class="notification.read ? 'opacity-60' : ''"
                            @click.stop.prevent="open(notification)"
                        >
                            <span
                                class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full"
                                :class="
                                    notification.read
                                        ? 'bg-muted text-muted-foreground'
                                        : 'bg-primary/15 text-primary'
                                "
                            >
                                <component
                                    :is="iconFor(notification)"
                                    class="size-3.5"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs leading-snug">{{
                                    notification.message
                                }}</span>
                                <span
                                    class="mt-0.5 block truncate text-[10px] text-muted-foreground"
                                >
                                    <template v-if="notification.resource"
                                        >{{ notification.resource.label }} ·
                                    </template>
                                    {{ timeAgo(notification.created_at) }}
                                </span>
                            </span>
                            <span
                                v-if="!notification.read"
                                class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                            />
                        </button>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
