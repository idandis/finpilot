<script setup lang="ts">
import { CalendarClock, ChevronDown, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { formatCurrency } from '@/lib/balance-sheet-format';
import { readableTextOn } from '@/lib/budget-colors';
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
    planned: Record<number, number>;
    actual: Record<number, number>;
    expanded: boolean;
}>();

const emit = defineEmits<{
    toggle: [];
    update: [subcategoryId: number, value: string];
    flush: [];
    addSubcategory: [];
    deleteCategory: [];
    deleteSubcategory: [subcategoryId: number];
}>();

const isIncome = computed(() => props.category.type === 'income');

const badgeTextColor = computed(() => readableTextOn(props.category.color));

const labels = computed(() => isIncome.value
    ? {
        planned: 'Atteso',
        actual: 'Incassato',
        difference: 'Differenza',
        positive: 'In più',
        negative: 'Mancano',
        addSubcategory: 'Voce solo per questo mese',
    }
    : {
        planned: 'Costo atteso',
        actual: 'Speso',
        difference: 'Rimane',
        positive: 'Rimane',
        negative: 'Sforato di',
        addSubcategory: 'Sottocategoria solo per questo mese',
    });

const plannedOf = (subcategoryId: number) => props.planned[subcategoryId] || 0;
const actualOf = (subcategoryId: number) => props.actual[subcategoryId] || 0;

// Positivo = va bene in entrambi i versi: sulle uscite è quanto avanza,
// sulle entrate è quanto è arrivato oltre le attese.
const differenceOf = (subcategoryId: number) => isIncome.value
    ? actualOf(subcategoryId) - plannedOf(subcategoryId)
    : plannedOf(subcategoryId) - actualOf(subcategoryId);

// La barra si scala sul maggiore fra previsto e reale: sforare di poco
// dipinge di rosso solo la fetta eccedente, non tutta la barra.
const overColor = computed(() => isIncome.value ? '#047857' : '#ef4444');

const segmentsFor = (plannedAmount: number, actualAmount: number) => {
    const scale = Math.max(plannedAmount, actualAmount);

    if (scale <= 0) return null;

    return {
        within: (Math.min(actualAmount, plannedAmount) / scale) * 100,
        over: (Math.max(actualAmount - plannedAmount, 0) / scale) * 100,
    };
};

const plannedTotal = computed(() =>
    props.category.subcategories.reduce((sum, sub) => sum + plannedOf(sub.id), 0));

const actualTotal = computed(() =>
    props.category.subcategories.reduce((sum, sub) => sum + actualOf(sub.id), 0));

const differenceTotal = computed(() => isIncome.value
    ? actualTotal.value - plannedTotal.value
    : plannedTotal.value - actualTotal.value);
</script>

<template>
    <div class="rounded-xl border bg-card shadow-sm transition-all hover:shadow-md">
        <div
            class="flex w-full items-start gap-2 pr-3 sm:items-center"
            :style="{ backgroundColor: expanded ? `${category.color}15` : 'transparent' }"
        >
        <button
            class="flex flex-1 flex-col gap-2 px-5 py-4 text-left sm:flex-row sm:items-center sm:gap-4"
            @click="emit('toggle')"
        >
            <div class="flex w-full items-center gap-3 sm:flex-1">
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg text-sm font-semibold tabular-nums"
                    :style="{ backgroundColor: category.color, color: badgeTextColor }"
                    :title="`${category.subcategories.length} voci`"
                >
                    {{ category.subcategories.length }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-semibold">{{ category.name }}</p>
                        <Badge v-if="category.monthly_budget_id" variant="secondary">
                            <CalendarClock class="size-3" />
                            solo questo mese
                        </Badge>
                    </div>

                    <div
                        v-if="segmentsFor(plannedTotal, actualTotal)"
                        class="mt-1.5 flex h-1.5 w-full overflow-hidden rounded-full bg-muted sm:max-w-md"
                    >
                        <div
                            class="h-full transition-all"
                            :style="{
                                width: `${segmentsFor(plannedTotal, actualTotal)!.within}%`,
                                backgroundColor: '#10b981',
                            }"
                        />
                        <div
                            class="h-full transition-all"
                            :style="{
                                width: `${segmentsFor(plannedTotal, actualTotal)!.over}%`,
                                backgroundColor: overColor,
                            }"
                        />
                    </div>
                </div>

                <!-- Su mobile gli importi vanno sotto: qui resta solo la freccia. -->
                <ChevronDown
                    class="size-4 shrink-0 self-start transition-transform sm:hidden"
                    :class="{ 'rotate-180': expanded }"
                />
            </div>

            <div class="flex w-full items-center justify-between gap-4 sm:w-auto sm:justify-end">
                <p class="text-sm sm:text-right">
                    <span class="whitespace-nowrap">
                        {{ labels.actual }}
                        <span class="font-semibold">{{ formatCurrency(actualTotal) }}</span>
                        di {{ formatCurrency(plannedTotal) }}
                    </span>
                    <span
                        class="block whitespace-nowrap text-xs"
                        :class="differenceTotal >= 0 ? 'text-muted-foreground' : 'text-red-600'"
                    >
                        {{ differenceTotal >= 0 ? labels.positive : labels.negative }}
                        {{ formatCurrency(Math.abs(differenceTotal)) }}
                    </span>
                </p>

                <ChevronDown
                    class="hidden size-4 shrink-0 transition-transform sm:block"
                    :class="{ 'rotate-180': expanded }"
                />
            </div>
        </button>

            <Button
                variant="ghost"
                size="icon-sm"
                class="mt-3 shrink-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive sm:mt-0"
                :title="`Elimina la categoria ${category.name}`"
                @click="emit('deleteCategory')"
            >
                <Trash2 class="size-4" />
            </Button>
        </div>

        <div v-if="expanded" class="border-t bg-muted/30">
            <div class="hidden items-center gap-3 px-5 py-2 text-xs uppercase tracking-wide text-muted-foreground sm:flex">
                <span class="flex-1">Voce</span>
                <span class="w-28 text-right">{{ labels.planned }}</span>
                <span class="w-24 text-right">{{ labels.actual }}</span>
                <span class="w-24 text-right">{{ labels.difference }}</span>
                <span class="w-9" />
            </div>

            <div class="divide-y border-t">
                <div
                    v-for="sub in category.subcategories"
                    :key="sub.id"
                    class="flex flex-wrap items-center gap-3 px-5 py-3"
                >
                    <div class="min-w-40 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium">{{ sub.name }}</p>
                            <Badge
                                v-if="sub.monthly_budget_id && !category.monthly_budget_id"
                                variant="secondary"
                            >
                                <CalendarClock class="size-3" />
                                solo questo mese
                            </Badge>
                        </div>

                        <div
                            v-if="segmentsFor(plannedOf(sub.id), actualOf(sub.id))"
                            class="mt-1 flex h-1.5 max-w-56 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full transition-all"
                                :style="{
                                    width: `${segmentsFor(plannedOf(sub.id), actualOf(sub.id))!.within}%`,
                                    backgroundColor: '#10b981',
                                }"
                            />
                            <div
                                class="h-full transition-all"
                                :style="{
                                    width: `${segmentsFor(plannedOf(sub.id), actualOf(sub.id))!.over}%`,
                                    backgroundColor: overColor,
                                }"
                            />
                        </div>
                    </div>

                    <div class="flex w-28 items-center justify-end gap-1">
                        <input
                            type="text"
                            inputmode="decimal"
                            :value="planned[sub.id] || ''"
                            class="w-20 rounded border border-input bg-transparent px-2 py-1 text-right text-sm"
                            placeholder="0.00"
                            :aria-label="`${labels.planned} per ${sub.name}`"
                            @input="emit('update', sub.id, ($event.target as HTMLInputElement).value)"
                            @blur="emit('flush')"
                        />
                        <span class="text-xs text-muted-foreground">€</span>
                    </div>

                    <div class="w-24 text-right text-sm">
                        <span class="text-muted-foreground sm:hidden">{{ labels.actual }} </span>
                        {{ formatCurrency(actualOf(sub.id)) }}
                    </div>

                    <div
                        class="w-24 text-right text-sm font-semibold"
                        :class="differenceOf(sub.id) >= 0 ? 'text-green-600' : 'text-red-600'"
                    >
                        <span class="font-normal text-muted-foreground sm:hidden">
                            {{ labels.difference }}
                        </span>
                        {{ formatCurrency(differenceOf(sub.id)) }}
                    </div>

                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="shrink-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                        :title="`Elimina ${sub.name}`"
                        @click="emit('deleteSubcategory', sub.id)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t bg-background px-5 py-3 text-sm font-semibold">
                <span class="min-w-40 flex-1">Subtotale</span>
                <span class="w-28 text-right">{{ formatCurrency(plannedTotal) }}</span>
                <span class="w-24 text-right">{{ formatCurrency(actualTotal) }}</span>
                <span
                    class="w-24 text-right"
                    :class="differenceTotal >= 0 ? 'text-green-600' : 'text-red-600'"
                >
                    {{ formatCurrency(differenceTotal) }}
                </span>
                <span class="w-9" />
            </div>

            <div class="border-t bg-muted/20 px-5 py-3">
                <Button variant="outline" size="sm" class="w-full" @click="emit('addSubcategory')">
                    <Plus class="mr-2 size-4" />
                    {{ labels.addSubcategory }}
                </Button>
            </div>
        </div>
    </div>
</template>
