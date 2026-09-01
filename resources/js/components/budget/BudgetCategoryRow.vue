<script setup lang="ts">
import { CalendarClock, ChevronRight, Plus, SlidersHorizontal } from '@lucide/vue';
import { computed } from 'vue';
import { formatAmount } from '@/lib/balance-sheet-format';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface Subcategory {
    id: number;
    name: string;
    monthly_budget_id: number | null;
}

interface Category {
    id: number;
    name: string;
    color: string;
    type: string;
    subcategories: Subcategory[];
    monthly_budget_id: number | null;
}

const props = defineProps<{
    category: Category;
    position: number;
    monthName: string;
    planned: Record<number, number>;
    actual: Record<number, number>;
    expanded: boolean;
}>();

const emit = defineEmits<{
    toggle: [];
    update: [subcategoryId: number, value: string];
    flush: [];
    addSubcategory: [];
    manage: [];
}>();

const isIncome = computed(() => props.category.type === 'income');

const labels = computed(() => isIncome.value
    ? {
        planned: 'Atteso',
        actual: 'Incassato',
        residual: 'Da incassare',
    }
    : {
        planned: 'Atteso',
        actual: 'Effettivo',
        residual: 'Residuo',
    });

const numberLabel = computed(() => String(props.position).padStart(2, '0'));

const plannedOf = (subcategoryId: number) => props.planned[subcategoryId] || 0;
const actualOf = (subcategoryId: number) => props.actual[subcategoryId] || 0;

// Quello che resta: sulle uscite è quanto si può ancora spendere, sulle
// entrate quanto manca ancora da incassare.
const residualOf = (subcategoryId: number) => plannedOf(subcategoryId) - actualOf(subcategoryId);

const plannedTotal = computed(() =>
    props.category.subcategories.reduce((sum, sub) => sum + plannedOf(sub.id), 0));

const actualTotal = computed(() =>
    props.category.subcategories.reduce((sum, sub) => sum + actualOf(sub.id), 0));

const residualTotal = computed(() => plannedTotal.value - actualTotal.value);

// Sulle uscite un residuo negativo è uno sforamento; sulle entrate è invece
// un incasso oltre le attese, quindi resta verde e col segno più.
const isOver = (residual: number) => !isIncome.value && residual < 0;
const isExtra = (residual: number) => isIncome.value && residual < 0;

const residualLabel = (residual: number) =>
    isExtra(residual) ? `+${formatAmount(-residual)}` : formatAmount(residual);

// In testata il secondo numero è l'effettivo, ma quando si sfora diventa lo
// sforamento col segno meno: è l'informazione che serve in quel momento, e
// l'effettivo resta comunque nella colonna della riga aperta.
const headlineAmount = (plannedAmount: number, actualAmount: number) => {
    const residual = plannedAmount - actualAmount;

    return isOver(residual) ? residual : actualAmount;
};

// Dentro l'atteso la barra si riempie da sinistra con l'effettivo. Sforando
// diventa solo rossa, ancorata a destra, e misura di quanto si è andati oltre
// rispetto all'atteso: 20 su 150 resta una scheggia, 100 su 150 riempie i due
// terzi.
const barWidth = (plannedAmount: number, actualAmount: number) => {
    if (plannedAmount <= 0) return actualAmount > 0 ? 100 : 0;

    const overspend = actualAmount - plannedAmount;

    if (!isIncome.value && overspend > 0) {
        return Math.min((overspend / plannedAmount) * 100, 100);
    }

    return Math.min((actualAmount / plannedAmount) * 100, 100);
};
</script>

<template>
    <div
        class="group/row"
        :style="{ backgroundColor: expanded ? `${category.color}12` : 'transparent' }"
    >
        <div class="flex items-center pr-5">
            <button class="flex min-w-0 flex-1 items-center gap-3 py-3 pl-5 text-left" @click="emit('toggle')">
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="flex min-w-0 items-center gap-2 overflow-hidden">
                            <span class="truncate font-medium">
                                <span class="tabular-nums">{{ numberLabel }}.</span>
                                {{ category.name }}
                            </span>
                            <Badge
                                v-if="category.monthly_budget_id"
                                class="shrink-0"
                                :title="`Solo ${monthName}`"
                            >
                                <CalendarClock class="size-3" />
                                <span class="hidden sm:inline">solo {{ monthName.toLowerCase() }}</span>
                            </Badge>
                        </span>

                        <!-- Sempre atteso / effettivo, nell'ordine delle colonne sotto. -->
                        <span
                            class="shrink-0 tabular-nums"
                            :class="isOver(residualTotal) ? 'text-red-500' : ''"
                        >
                            <span :class="isOver(residualTotal) ? '' : 'text-muted-foreground'">
                                {{ formatAmount(plannedTotal) }} /
                            </span>
                            <span class="font-semibold">
                                {{ formatAmount(headlineAmount(plannedTotal, actualTotal)) }}
                            </span>
                        </span>
                    </div>

                    <div
                        class="mt-2 flex h-2.5 w-full overflow-hidden rounded-sm bg-foreground/15"
                        :class="isOver(residualTotal) ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="h-full rounded-sm transition-all"
                            :style="{
                                width: `${barWidth(plannedTotal, actualTotal)}%`,
                                backgroundColor: isOver(residualTotal) ? '#ef4444' : '#10b981',
                            }"
                        />
                    </div>
                </div>

                <ChevronRight
                    class="size-4 shrink-0 text-muted-foreground transition-transform"
                    :class="{ 'rotate-90': expanded }"
                />
            </button>

        </div>

        <div v-if="expanded" class="border-t border-border/60 pb-3">
            <div class="hidden items-center gap-3 px-5 py-2 text-xs uppercase tracking-wide text-muted-foreground sm:flex">
                <span class="min-w-40 flex-1 pl-4">Voce</span>
                <span class="w-24 pr-2 text-right">{{ labels.planned }}</span>
                <span class="w-24 text-right">{{ labels.actual }}</span>
                <span class="w-24 text-right">{{ labels.residual }}</span>
            </div>

            <div
                v-for="sub in category.subcategories"
                :key="sub.id"
                class="flex items-center gap-1 py-2 pl-4 pr-2 sm:gap-3 sm:px-5"
            >
                <div class="flex min-w-0 flex-1 items-center gap-1 overflow-hidden sm:min-w-40 sm:gap-2 sm:pl-4">
                    <span class="truncate text-sm">{{ sub.name }}</span>
                    <Badge
                        v-if="sub.monthly_budget_id && !category.monthly_budget_id"
                        class="shrink-0"
                        :title="`Solo ${monthName}`"
                    >
                        <CalendarClock class="size-3" />
                        <span class="hidden sm:inline">solo {{ monthName.toLowerCase() }}</span>
                    </Badge>
                </div>

                <input
                    type="text"
                    inputmode="decimal"
                    :value="planned[sub.id] || ''"
                    class="w-14 shrink-0 rounded-md border border-foreground/40 bg-background/50 px-1.5 py-1 text-right text-sm tabular-nums focus:border-foreground focus:outline-none sm:w-24 sm:px-2"
                    placeholder="0,00"
                    :aria-label="`${labels.planned} per ${sub.name}`"
                    @input="emit('update', sub.id, ($event.target as HTMLInputElement).value)"
                    @blur="emit('flush')"
                />

                <span class="shrink-0 text-xs text-muted-foreground sm:hidden">/</span>

                <span
                    class="w-12 shrink-0 truncate text-right text-xs tabular-nums sm:w-24 sm:text-sm"
                    :title="`${labels.actual} ${formatAmount(actualOf(sub.id))}`"
                >
                    {{ formatAmount(actualOf(sub.id)) }}
                </span>

                <span class="shrink-0 text-xs text-muted-foreground sm:hidden">/</span>

                <span
                    class="w-16 shrink-0 text-right text-xs font-semibold tabular-nums sm:w-24 sm:text-sm"
                    :class="isOver(residualOf(sub.id)) ? 'text-red-500' : ''"
                    :title="`${labels.residual} ${residualLabel(residualOf(sub.id))}`"
                >
                    {{ residualLabel(residualOf(sub.id)) }}
                </span>

            </div>

            <div class="flex flex-wrap items-center gap-1 px-2 pb-1 sm:px-3">
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground"
                    @click="emit('addSubcategory')"
                >
                    <Plus class="mr-1 size-4" />
                    Aggiungi alla lista
                </Button>

                <Button
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground"
                    @click="emit('manage')"
                >
                    <SlidersHorizontal class="mr-1 size-4" />
                    Modifica la lista
                </Button>
            </div>

            <div
                class="mx-1 mt-1 flex items-center gap-1 rounded-lg border py-1.5 pl-3 pr-1 text-sm font-semibold tabular-nums sm:mx-2 sm:gap-3 sm:px-3"
                :style="{ borderColor: category.color, backgroundColor: `${category.color}22` }"
            >
                <span class="min-w-0 flex-1 truncate text-xs sm:min-w-40 sm:pl-4 sm:text-sm">
                    Totale · {{ category.subcategories.length }} voci
                </span>
                <span
                    class="w-14 shrink-0 truncate pr-1.5 text-right text-xs sm:w-24 sm:pr-2 sm:text-sm"
                    :title="`${labels.planned} ${formatAmount(plannedTotal)}`"
                >
                    {{ formatAmount(plannedTotal) }}
                </span>
                <span class="shrink-0 text-xs font-normal text-muted-foreground sm:hidden">/</span>
                <span
                    class="w-12 shrink-0 truncate text-right text-xs sm:w-24 sm:text-sm"
                    :title="`${labels.actual} ${formatAmount(actualTotal)}`"
                >
                    {{ formatAmount(actualTotal) }}
                </span>
                <span class="shrink-0 text-xs font-normal text-muted-foreground sm:hidden">/</span>
                <span
                    class="w-16 shrink-0 text-right text-xs sm:w-24 sm:text-sm"
                    :class="isOver(residualTotal) ? 'text-red-500' : 'text-green-600 dark:text-green-400'"
                >
                    {{ residualLabel(residualTotal) }}
                </span>
            </div>
        </div>
    </div>
</template>
