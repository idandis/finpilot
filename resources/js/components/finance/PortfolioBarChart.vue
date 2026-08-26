<script setup lang="ts">
import { computed } from 'vue';
import type { InvestmentPositions } from '@/types';

const props = defineProps<{
    positions: InvestmentPositions;
    accountBalance: number | null;
}>();

/**
 * Same 8-hue categorical order as PortfolioPieChart, kept in sync so a
 * position renders with the same color in both views.
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

const bars = computed(() => {
    const sorted = [...rawSlices.value].sort((a, b) => b.value - a.value);
    const maxValue = Math.max(0, ...sorted.map((slice) => slice.value));

    return sorted.map((slice, index) => ({
        ...slice,
        color: PALETTE[index % PALETTE.length],
        percentage: total.value > 0 ? (slice.value / total.value) * 100 : 0,
        width: maxValue > 0 ? (slice.value / maxValue) * 100 : 0,
    }));
});

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

    <ul v-else class="min-w-0 space-y-3">
        <li v-for="bar in bars" :key="bar.key" class="min-w-0">
            <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                <span class="flex min-w-0 items-center gap-2">
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: bar.color }"
                    />
                    <span class="truncate">{{ bar.name }}</span>
                </span>
                <span class="flex shrink-0 items-center gap-2 tabular-nums">
                    <span class="text-xs text-muted-foreground"
                        >{{ bar.percentage.toFixed(0) }}%</span
                    >
                    <span class="font-medium">{{
                        formatCurrency(bar.value)
                    }}</span>
                </span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full transition-all"
                    :style="{
                        width: `${bar.width}%`,
                        backgroundColor: bar.color,
                    }"
                />
            </div>
        </li>
    </ul>
</template>
