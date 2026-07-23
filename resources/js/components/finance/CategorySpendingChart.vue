<script setup lang="ts">
import { computed, ref } from 'vue';
import type { CategoryBreakdownItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breakdown: CategoryBreakdownItem[];
        currency: string;
        emptyMessage?: string;
    }>(),
    {
        emptyMessage: 'Nessuna spesa da mostrare per questo mese.',
    },
);

const UNCATEGORIZED_COLOR = '#71717a';
const RADIUS = 70;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const GAP = 2;

const total = computed(() =>
    props.breakdown.reduce((sum, item) => sum + item.amount, 0),
);

const segments = computed(() => {
    if (total.value <= 0) {
        return [];
    }

    let offset = 0;

    return props.breakdown.map((item) => {
        const fraction = item.amount / total.value;
        const length = Math.max(fraction * CIRCUMFERENCE - GAP, 0);
        const segment = {
            ...item,
            color: item.color ?? UNCATEGORIZED_COLOR,
            percentage: fraction * 100,
            dasharray: `${length} ${CIRCUMFERENCE - length}`,
            dashoffset: -offset,
        };
        offset += fraction * CIRCUMFERENCE;

        return segment;
    });
});

const hoveredKey = ref<number | string | null>(null);

function keyFor(item: CategoryBreakdownItem) {
    return item.category_id ?? 'uncategorized';
}

function formatCurrency(value: number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: props.currency,
    }).format(value);
}
</script>

<template>
    <div v-if="breakdown.length === 0" class="text-sm text-muted-foreground">
        {{ emptyMessage }}
    </div>

    <div v-else class="flex flex-col gap-6 sm:flex-row sm:items-center">
        <div class="relative mx-auto size-48 shrink-0">
            <svg viewBox="0 0 200 200" class="size-full -rotate-90">
                <circle
                    v-for="segment in segments"
                    :key="keyFor(segment)"
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
                            hoveredKey !== null &&
                            hoveredKey !== keyFor(segment),
                    }"
                    @mouseenter="hoveredKey = keyFor(segment)"
                    @mouseleave="hoveredKey = null"
                    @focus="hoveredKey = keyFor(segment)"
                    @blur="hoveredKey = null"
                >
                    <title>
                        {{ segment.name }}:
                        {{ formatCurrency(segment.amount) }} ({{
                            segment.percentage.toFixed(1)
                        }}%)
                    </title>
                </circle>
            </svg>
            <div
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
            >
                <span class="text-xs text-muted-foreground">Uscite</span>
                <span class="text-lg font-semibold">{{
                    formatCurrency(total)
                }}</span>
            </div>
        </div>

        <ul class="flex-1 space-y-1.5">
            <li
                v-for="segment in segments"
                :key="keyFor(segment)"
                class="flex items-center justify-between gap-3 rounded-md px-2 py-1 text-sm transition-colors"
                :class="{
                    'bg-muted': hoveredKey === keyFor(segment),
                }"
                @mouseenter="hoveredKey = keyFor(segment)"
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
                        formatCurrency(segment.amount)
                    }}</span>
                </span>
            </li>
        </ul>
    </div>
</template>
