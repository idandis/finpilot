<script setup lang="ts">
import { computed, ref } from 'vue';
import type { InvestmentPositions } from '@/types';

const props = defineProps<{
    positions: InvestmentPositions;
    accountBalance: number | null;
}>();

/**
 * The 8-hue categorical order validated by the dataviz skill (adjacent-pair
 * CVD/contrast gates in both light and dark modes). A donut is an "all
 * pairs visible at once" chart, where only the first 4 slots keep that
 * guarantee - past that, hue alone can't carry the distinction, so every
 * slice still gets a direct text label (name + %) in the legend, never
 * color-only.
 */
const PALETTE = [
    '#2a78d6', // blue
    '#008300', // green
    '#e87ba4', // magenta
    '#eda100', // yellow
    '#1baf7a', // aqua
    '#eb6834', // orange
    '#4a3aa7', // violet
    '#e34948', // red
];

const RADIUS = 70;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const GAP = 2;

type RawSlice = { key: string; name: string; value: number };

const rawSlices = computed<RawSlice[]>(() => {
    const slices = props.positions.open.map((position) => ({
        key: position.isin,
        name: position.name,
        value: position.market_value ?? position.invested,
    }));

    if (props.accountBalance !== null && props.accountBalance > 0) {
        slices.push({
            key: 'account-balance',
            name: 'Saldo conto',
            value: props.accountBalance,
        });
    }

    return slices;
});

const total = computed(() =>
    rawSlices.value.reduce((sum, slice) => sum + slice.value, 0),
);

/** Every position gets its own slice, largest first - none folded away. */
const sortedSlices = computed(() =>
    [...rawSlices.value].sort((a, b) => b.value - a.value),
);

const segments = computed(() => {
    if (total.value <= 0) {
        return [];
    }

    let offset = 0;

    return sortedSlices.value.map((slice, index) => {
        const fraction = slice.value / total.value;
        const length = Math.max(fraction * CIRCUMFERENCE - GAP, 0);
        const segment = {
            ...slice,
            color: PALETTE[index % PALETTE.length],
            percentage: fraction * 100,
            dasharray: `${length} ${CIRCUMFERENCE - length}`,
            dashoffset: -offset,
        };
        offset += fraction * CIRCUMFERENCE;

        return segment;
    });
});

const hoveredKey = ref<string | null>(null);

function formatCurrency(value: number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}
</script>

<template>
    <div v-if="total <= 0" class="text-sm text-muted-foreground">
        Nessun patrimonio da mostrare.
    </div>

    <div v-else class="flex flex-col gap-8 @lg:flex-row @lg:items-center">
        <div class="relative aspect-square w-56 shrink-0 @lg:w-72">
            <svg viewBox="0 0 200 200" class="size-full -rotate-90">
                <circle
                    v-for="segment in segments"
                    :key="segment.key"
                    cx="100"
                    cy="100"
                    :r="RADIUS"
                    fill="none"
                    :stroke="segment.color"
                    stroke-width="30"
                    :stroke-dasharray="segment.dasharray"
                    :stroke-dashoffset="segment.dashoffset"
                    class="cursor-pointer transition-opacity"
                    :class="{
                        'opacity-40':
                            hoveredKey !== null && hoveredKey !== segment.key,
                    }"
                    @mouseenter="hoveredKey = segment.key"
                    @mouseleave="hoveredKey = null"
                    @focus="hoveredKey = segment.key"
                    @blur="hoveredKey = null"
                >
                    <title>
                        {{ segment.name }}: {{ formatCurrency(segment.value) }}
                        ({{ segment.percentage.toFixed(1) }}%)
                    </title>
                </circle>
            </svg>
            <div
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
            >
                <span class="text-xs text-muted-foreground">Patrimonio</span>
                <span class="text-lg font-semibold">{{
                    formatCurrency(total)
                }}</span>
            </div>
        </div>

        <ul class="min-w-0 flex-1 space-y-1.5">
            <li
                v-for="segment in segments"
                :key="segment.key"
                class="flex items-center justify-between gap-3 rounded-md px-2 py-1 text-sm transition-colors"
                :class="{ 'bg-muted': hoveredKey === segment.key }"
                @mouseenter="hoveredKey = segment.key"
                @mouseleave="hoveredKey = null"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: segment.color }"
                    />
                    <span class="truncate">{{ segment.name }}</span>
                </span>
                <span class="flex shrink-0 items-center gap-2 tabular-nums">
                    <span class="text-xs text-muted-foreground"
                        >{{ segment.percentage.toFixed(0) }}%</span
                    >
                    <span class="font-medium">{{
                        formatCurrency(segment.value)
                    }}</span>
                </span>
            </li>
        </ul>
    </div>
</template>
