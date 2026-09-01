<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import { formatAmount } from '@/lib/balance-sheet-format';

interface Subcategory {
    id: number;
}

interface Category {
    id: number;
    name: string;
    color: string;
    type: string;
    subcategories: Subcategory[];
}

const props = defineProps<{
    categories: Category[];
    planned: Record<number, number>;
    actual: Record<number, number>;
}>();

const share = (value: number, total: number) => total > 0 ? (value / total) * 100 : 0;

// Le righe seguono l'ordine delle categorie nel budget: il riepilogo si legge
// scorrendo la stessa sequenza, senza doverla ricostruire a mente.
const rowsOf = (type: string) => props.categories
    .filter((category) => category.type === type)
    .map((category) => ({
        id: category.id,
        name: category.name,
        color: category.color,
        planned: category.subcategories.reduce((sum, sub) => sum + (props.planned[sub.id] || 0), 0),
        actual: category.subcategories.reduce((sum, sub) => sum + (props.actual[sub.id] || 0), 0),
    }))
    .filter((row) => row.planned > 0 || row.actual > 0);

// Entrate e uscite hanno ordini di grandezza diversi: ogni sezione porta i
// propri totali, su cui si calcolano anche le percentuali.
const sections = computed(() => [
    { key: 'income', title: 'Entrate', actualLabel: 'Incassate', rows: rowsOf('income') },
    { key: 'expense', title: 'Uscite', actualLabel: 'Effettive', rows: rowsOf('expense') },
].map((section) => {
    const plannedTotal = section.rows.reduce((sum, row) => sum + row.planned, 0);
    const actualTotal = section.rows.reduce((sum, row) => sum + row.actual, 0);

    return {
        ...section,
        plannedTotal,
        actualTotal,
        isExpense: section.key === 'expense',
    };
}));

const hasRows = computed(() => sections.value.some((section) => section.rows.length > 0));

// Il residuo è sempre atteso meno effettivo; il rosso però ha senso solo dove
// spendere più del previsto è un problema.
const isOver = (isExpense: boolean, residual: number) => isExpense && residual < 0;

const isOpen = ref(false);
</script>

<template>
    <div class="rounded-2xl bg-muted/60 dark:bg-muted/50">
        <button
            class="flex w-full items-center justify-between gap-4 rounded-2xl px-5 py-3 text-left hover:bg-foreground/5"
            :aria-expanded="isOpen"
            @click="isOpen = !isOpen"
        >
            <span>
                <span class="block text-lg font-bold">Riepilogo del mese</span>
                <span class="block text-xs text-muted-foreground">
                    Come si dividono entrate e uscite fra le categorie, attese ed effettive. In EUR.
                </span>
            </span>
            <ChevronDown class="size-4 shrink-0 transition-transform" :class="{ 'rotate-180': isOpen }" />
        </button>

        <div v-if="isOpen" class="border-t border-border/60 px-5 pb-5">
            <div v-if="!hasRows" class="py-6 text-center text-sm text-muted-foreground">
                Imposta un importo atteso o registra un movimento per vedere il riepilogo.
            </div>

            <template v-else>
                <div v-for="section in sections" :key="section.key" class="pt-4">
                    <h4 class="font-semibold">{{ section.title }}</h4>

                    <p v-if="section.rows.length === 0" class="py-3 text-sm text-muted-foreground">
                        Niente da mostrare in questa sezione.
                    </p>

                    <template v-else>
                        <div class="flex items-center gap-1.5 py-2 text-[10px] uppercase tracking-wide text-muted-foreground sm:gap-3 sm:text-xs">
                            <span class="min-w-0 flex-1">Categoria</span>
                            <span class="w-14 shrink-0 text-right sm:w-28">Attese</span>
                            <span class="w-14 shrink-0 text-right sm:w-28">{{ section.actualLabel }}</span>
                            <span class="w-14 shrink-0 text-right sm:w-24">
                                <span class="sm:hidden">Diff.</span>
                                <span class="hidden sm:inline">Differenza</span>
                            </span>
                        </div>

                        <div class="divide-y divide-border/60 border-y border-border/60">
                            <div
                                v-for="row in section.rows"
                                :key="row.id"
                                class="flex items-center gap-1.5 py-2 sm:gap-3"
                            >
                                <span class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
                                    <span
                                        class="size-2.5 shrink-0 rounded-full"
                                        :style="{ backgroundColor: row.color }"
                                    />
                                    <span class="truncate text-xs sm:text-sm">{{ row.name }}</span>
                                </span>

                                <span
                                    class="w-14 shrink-0 truncate text-right text-xs tabular-nums sm:w-28 sm:text-sm"
                                    :title="`Attese ${formatAmount(row.planned)}`"
                                >
                                    {{ formatAmount(row.planned) }}
                                    <span class="ml-1 hidden text-xs text-muted-foreground sm:inline">
                                        {{ share(row.planned, section.plannedTotal).toFixed(0) }}%
                                    </span>
                                </span>

                                <span
                                    class="w-14 shrink-0 truncate text-right text-xs tabular-nums sm:w-28 sm:text-sm"
                                    :title="`${section.actualLabel} ${formatAmount(row.actual)}`"
                                >
                                    {{ formatAmount(row.actual) }}
                                    <span class="ml-1 hidden text-xs text-muted-foreground sm:inline">
                                        {{ share(row.actual, section.actualTotal).toFixed(0) }}%
                                    </span>
                                </span>

                                <span
                                    class="w-14 shrink-0 truncate text-right text-xs font-medium tabular-nums sm:w-24 sm:text-sm"
                                    :class="isOver(section.isExpense, row.planned - row.actual)
                                        ? 'text-red-500'
                                        : 'text-green-600'"
                                >
                                    {{ formatAmount(row.planned - row.actual) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 py-2 text-xs font-semibold tabular-nums sm:gap-3 sm:text-sm">
                            <span class="min-w-0 flex-1">Totale {{ section.title.toLowerCase() }}</span>
                            <span class="w-14 shrink-0 truncate text-right sm:w-28">
                                {{ formatAmount(section.plannedTotal) }}
                            </span>
                            <span class="w-14 shrink-0 truncate text-right sm:w-28">
                                {{ formatAmount(section.actualTotal) }}
                            </span>
                            <span
                                class="w-14 shrink-0 truncate text-right sm:w-24"
                                :class="isOver(section.isExpense, section.plannedTotal - section.actualTotal)
                                    ? 'text-red-500'
                                    : 'text-green-600'"
                            >
                                {{ formatAmount(section.plannedTotal - section.actualTotal) }}
                            </span>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</template>
