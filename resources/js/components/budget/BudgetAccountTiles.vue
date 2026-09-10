<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatCurrency } from '@/lib/balance-sheet-format';
import { accountIcon } from '@/lib/budget-accounts';
import type { BudgetAccount } from '@/lib/budget-accounts';

/**
 * I saldi dei conti in cima al budget: quanto c'è, adesso, in ogni tasca.
 * Non dipendono dal mese aperto - il denaro è quello, qualunque mese si stia
 * guardando - perciò stanno sopra al selettore dei mesi.
 */
const props = defineProps<{
    accounts: BudgetAccount[];
    manageHref: string;
}>();

const PER_PAGE = 4;

/** Archiviato o escluso dalle statistiche: fuori dal riepilogo del mese. */
const visible = computed(() =>
    props.accounts.filter(
        (account) => !account.hidden_from_stats && !account.excluded_from_stats,
    ),
);

const pages = computed<BudgetAccount[][]>(() =>
    Array.from(
        { length: Math.ceil(visible.value.length / PER_PAGE) },
        (_, page) =>
            visible.value.slice(page * PER_PAGE, page * PER_PAGE + PER_PAGE),
    ),
);

const track = ref<HTMLElement | null>(null);
const currentPage = ref(0);

// La pagina attiva si ricava dallo scorrimento invece di essere pilotata:
// così il pallino segue anche il dito, non solo i click.
const syncCurrentPage = () => {
    const element = track.value;

    if (!element) {
        return;
    }

    currentPage.value = Math.round(element.scrollLeft / element.clientWidth);
};

const goToPage = (page: number) => {
    const element = track.value;

    if (!element) {
        return;
    }

    element.scrollTo({ left: page * element.clientWidth, behavior: 'smooth' });
};
</script>

<template>
    <!-- Senza conti non c'è niente da mostrare: i conti si aggiungono
         dall'icona del portafoglio in testata. -->
    <div v-if="visible.length" class="space-y-3">
        <div
            ref="track"
            class="flex snap-x snap-mandatory [scrollbar-width:none] overflow-x-auto [&::-webkit-scrollbar]:hidden"
            @scroll.passive="syncCurrentPage"
        >
            <div
                v-for="(page, index) in pages"
                :key="index"
                class="grid w-full shrink-0 snap-start grid-cols-2 content-start gap-3 md:grid-cols-4"
            >
                <Link
                    v-for="account in page"
                    :key="account.id"
                    :href="manageHref"
                    class="flex items-center gap-3 rounded-2xl bg-muted/60 p-3 transition-colors hover:bg-muted dark:bg-muted/40 dark:hover:bg-muted/60"
                    :title="`Vai ai conti · ${account.name}`"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl text-white"
                        :style="{ backgroundColor: account.color ?? '#3b82f6' }"
                    >
                        <component
                            :is="accountIcon(account.icon)"
                            class="size-5"
                        />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ account.name }}
                        </span>
                        <span
                            class="block truncate text-base font-bold tabular-nums"
                            :class="account.balance < 0 ? 'text-red-500' : ''"
                        >
                            {{ formatCurrency(account.balance) }}
                        </span>
                    </span>
                </Link>
            </div>
        </div>

        <div v-if="pages.length > 1" class="flex justify-center gap-2">
            <button
                v-for="(page, index) in pages"
                :key="index"
                class="h-2 rounded-full transition-all"
                :class="
                    index === currentPage
                        ? 'w-5 bg-primary'
                        : 'w-2 bg-muted-foreground/30'
                "
                :aria-label="`Vai al gruppo ${index + 1} di ${pages.length}`"
                :aria-current="index === currentPage ? 'true' : undefined"
                @click="goToPage(index)"
            />
        </div>
    </div>
</template>
