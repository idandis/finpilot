<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { KanbanSquare, KeyRound, LayoutGrid, Menu, ShoppingCart, Sparkles, Wallet } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import * as aiChat from '@/routes/ai-chat';
import * as monthlyBudgets from '@/routes/monthly-budgets';
import * as passwords from '@/routes/passwords';
import * as shoppingLists from '@/routes/shopping-lists';
import * as tasks from '@/routes/tasks';

const { isCurrentUrl } = useCurrentUrl();

// Il resto del menu vive nella sidebar: da qui la si tira fuori.
const { setOpenMobile } = useSidebar();

const items = [
    { href: aiChat.index(), icon: Sparkles, label: 'AI' },
    { href: dashboard(), icon: LayoutGrid, label: 'Dashboard' },
    { href: monthlyBudgets.index(), icon: Wallet, label: 'Budget mensile' },
    { href: tasks.index(), icon: KanbanSquare, label: 'Task' },
    { href: passwords.index(), icon: KeyRound, label: 'Password' },
    { href: shoppingLists.index(), icon: ShoppingCart, label: 'Lista della spesa' },
];

// Starts wide and shrinks once the page is scrolled, so it's roomier at
// rest but stays out of the way while reading content further down.
const isCompact = ref(false);

function handleScroll() {
    isCompact.value = window.scrollY > 24;
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <!--
        Dodici pixel dal bordo, sempre gli stessi.

        Qui la barra non somma `env(safe-area-inset-bottom)`: su un iPhone col
        gesto a scorrimento sono altri 34px, e il menu finiva a mezzo dito da
        dove uno se lo aspetta. La pillola galleggia sopra al contenuto e non
        ha niente da leggere sotto, quindi appoggiarla al bordo non nasconde
        nulla - si sovrappone alla zona del trattino solo di striscio.
    -->
    <nav
        class="fixed bottom-3 left-1/2 z-30 flex -translate-x-1/2 items-center rounded-full border bg-muted/50 shadow-lg backdrop-blur-md transition-all duration-300 ease-out md:hidden"
        :class="isCompact ? 'gap-0.5 px-1.5 py-1.5' : 'gap-0.5 px-2 py-2'"
    >
        <Link
            v-for="item in items"
            :key="item.label"
            :href="item.href"
            :aria-label="item.label"
            class="flex shrink-0 items-center justify-center rounded-full transition-all duration-300 ease-out"
            :class="[
                isCompact ? 'size-9' : 'size-11',
                isCurrentUrl(item.href) ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground',
            ]"
        >
            <component :is="item.icon" :class="isCompact ? 'size-5' : 'size-6'" />
        </Link>

        <button
            aria-label="Apri il menu"
            class="flex shrink-0 items-center justify-center rounded-full text-muted-foreground transition-all duration-300 ease-out hover:text-foreground"
            :class="isCompact ? 'size-9' : 'size-11'"
            @click="setOpenMobile(true)"
        >
            <Menu :class="isCompact ? 'size-5' : 'size-6'" />
        </button>
    </nav>
</template>
