<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import { formatCurrency } from '@/lib/balance-sheet-format';

interface Subcategory {
    id: number;
}

interface Category {
    id: number;
    name: string;
    color: string;
    subcategories: Subcategory[];
}

const props = defineProps<{
    categories: Category[];
    planned: Record<number, number>;
    actual: Record<number, number>;
}>();

const rows = computed(() => props.categories
    .map((category) => ({
        id: category.id,
        name: category.name,
        color: category.color,
        planned: category.subcategories.reduce((sum, sub) => sum + (props.planned[sub.id] || 0), 0),
        actual: category.subcategories.reduce((sum, sub) => sum + (props.actual[sub.id] || 0), 0),
    }))
    .filter((row) => row.planned > 0 || row.actual > 0)
    .sort((a, b) => (b.planned - a.planned) || (b.actual - a.actual)));

const plannedTotal = computed(() => rows.value.reduce((sum, row) => sum + row.planned, 0));
const actualTotal = computed(() => rows.value.reduce((sum, row) => sum + row.actual, 0));

const share = (value: number, total: number) => total > 0 ? (value / total) * 100 : 0;

const RADIUS = 70;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const GAP = 2;

// Un anello per stato. Le fette piccole restano schegge, per questo ogni
// categoria è comunque scritta per esteso nella tabella qui sotto.
const segmentsOf = (key: 'planned' | 'actual', total: number) => {
    if (total <= 0) return [];

    let offset = 0;

    return rows.value
        .map((row) => {
            const fraction = row[key] / total;
            const length = Math.max(fraction * CIRCUMFERENCE - GAP, 0);
            const segment = {
                id: row.id,
                name: row.name,
                color: row.color,
                value: row[key],
                percentage: fraction * 100,
                dasharray: `${length} ${CIRCUMFERENCE - length}`,
                dashoffset: -offset,
            };

            offset += fraction * CIRCUMFERENCE;

            return segment;
        })
        .filter((segment) => segment.value > 0);
};

type HoveredSlice = {
    donut: string;
    id: number;
    name: string;
    color: string;
    value: number;
    percentage: number;
    x: number;
    y: number;
};

const hovered = ref<HoveredSlice | null>(null);

const showSlice = (
    donutKey: string,
    segment: { id: number; name: string; color: string; value: number; percentage: number },
    event: MouseEvent,
) => {
    const wrapper = (event.currentTarget as SVGCircleElement).closest('[data-donut]');

    if (!wrapper) return;

    const bounds = wrapper.getBoundingClientRect();

    hovered.value = {
        donut: donutKey,
        ...segment,
        x: event.clientX - bounds.left,
        y: event.clientY - bounds.top,
    };
};

const donuts = computed(() => [
    {
        key: 'planned' as const,
        label: 'Attese',
        total: plannedTotal.value,
        emptyLabel: 'Nessun costo atteso impostato.',
        segments: segmentsOf('planned', plannedTotal.value),
    },
    {
        key: 'actual' as const,
        label: 'Effettive',
        total: actualTotal.value,
        emptyLabel: 'Nessuna spesa registrata.',
        segments: segmentsOf('actual', actualTotal.value),
    },
]);

const isOpen = ref(false);

</script>

<template>
    <div class="rounded-lg border bg-card">
        <button
            class="flex w-full items-center justify-between gap-4 px-5 py-3 text-left hover:bg-muted/30"
            :aria-expanded="isOpen"
            @click="isOpen = !isOpen"
        >
            <span>
                <span class="block font-semibold">Riepilogo uscite</span>
                <span class="block text-xs text-muted-foreground">
                    Come si divide la spesa fra le categorie, prevista e reale.
                </span>
            </span>
            <ChevronDown class="size-4 shrink-0 transition-transform" :class="{ 'rotate-180': isOpen }" />
        </button>

        <div v-if="isOpen" class="border-t px-5 pb-5">
        <div v-if="rows.length === 0" class="py-6 text-center text-sm text-muted-foreground">
            Imposta un costo atteso o registra una spesa per vedere il riepilogo.
        </div>

        <template v-else>
            <div class="flex flex-col items-center gap-8 pt-4 sm:flex-row sm:justify-center sm:gap-12">
                <div v-for="donut in donuts" :key="donut.key" class="flex flex-col items-center gap-2">
                    <span class="text-xs font-medium">{{ donut.label }}</span>

                    <div
                        data-donut
                        class="relative aspect-square w-52 sm:w-60"
                        @mouseleave="hovered = null"
                    >
                        <svg viewBox="0 0 200 200" class="size-full -rotate-90">
                            <circle
                                v-if="donut.segments.length === 0"
                                cx="100"
                                cy="100"
                                :r="RADIUS"
                                fill="none"
                                stroke-width="26"
                                class="stroke-muted"
                            />
                            <circle
                                v-for="segment in donut.segments"
                                :key="segment.id"
                                cx="100"
                                cy="100"
                                :r="RADIUS"
                                fill="none"
                                :stroke="segment.color"
                                stroke-width="26"
                                :stroke-dasharray="segment.dasharray"
                                :stroke-dashoffset="segment.dashoffset"
                                class="cursor-pointer transition-opacity"
                                :class="{
                                    'opacity-30': hovered
                                        && hovered.donut === donut.key
                                        && hovered.id !== segment.id,
                                }"
                                @mousemove="showSlice(donut.key, segment, $event)"
                                @click="showSlice(donut.key, segment, $event)"
                            />
                        </svg>

                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <span class="text-base font-semibold">{{ formatCurrency(donut.total) }}</span>
                        </div>

                        <div
                            v-if="hovered && hovered.donut === donut.key"
                            class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-md"
                            :style="{ left: `${hovered.x}px`, top: `${hovered.y - 10}px` }"
                        >
                            <span class="flex items-center gap-1.5 font-medium">
                                <span
                                    class="size-2 shrink-0 rounded-full"
                                    :style="{ backgroundColor: hovered.color }"
                                />
                                {{ hovered.name }}
                            </span>
                            <span class="block text-muted-foreground">
                                {{ formatCurrency(hovered.value) }} · {{ hovered.percentage.toFixed(1) }}%
                            </span>
                        </div>
                    </div>

                    <span v-if="donut.total <= 0" class="text-xs text-muted-foreground">
                        {{ donut.emptyLabel }}
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto pt-6">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-muted-foreground">
                            <th class="py-2 text-left font-medium">Categoria</th>
                            <th class="py-2 text-right font-medium">Attese</th>
                            <th class="py-2 text-right font-medium">Effettive</th>
                            <th class="py-2 text-right font-medium">Differenza</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="row in rows" :key="row.id">
                            <td class="py-2">
                                <span class="flex items-center gap-2">
                                    <span
                                        class="size-2.5 shrink-0 rounded-full"
                                        :style="{ backgroundColor: row.color }"
                                    />
                                    <span class="truncate">{{ row.name }}</span>
                                </span>
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{ formatCurrency(row.planned) }}
                                <span class="ml-1 text-xs text-muted-foreground">
                                    {{ share(row.planned, plannedTotal).toFixed(0) }}%
                                </span>
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{ formatCurrency(row.actual) }}
                                <span class="ml-1 text-xs text-muted-foreground">
                                    {{ share(row.actual, actualTotal).toFixed(0) }}%
                                </span>
                            </td>
                            <td
                                class="py-2 text-right font-medium tabular-nums"
                                :class="row.planned - row.actual >= 0 ? 'text-green-600' : 'text-red-600'"
                            >
                                {{ formatCurrency(row.planned - row.actual) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t font-semibold">
                            <td class="py-2 text-left">Totale</td>
                            <td class="py-2 text-right tabular-nums">{{ formatCurrency(plannedTotal) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ formatCurrency(actualTotal) }}</td>
                            <td
                                class="py-2 text-right tabular-nums"
                                :class="plannedTotal - actualTotal >= 0 ? 'text-green-600' : 'text-red-600'"
                            >
                                {{ formatCurrency(plannedTotal - actualTotal) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </template>
        </div>
    </div>
</template>
