<script setup lang="ts">
import { computed } from 'vue';
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

const total = computed(() =>
    props.breakdown.reduce((sum, item) => sum + item.amount, 0),
);

const bars = computed(() => {
    const maxAmount = Math.max(0, ...props.breakdown.map((item) => item.amount));

    return [...props.breakdown]
        .sort((a, b) => b.amount - a.amount)
        .map((item) => ({
            ...item,
            color: item.color ?? UNCATEGORIZED_COLOR,
            percentage: total.value > 0 ? (item.amount / total.value) * 100 : 0,
            width: maxAmount > 0 ? (item.amount / maxAmount) * 100 : 0,
        }));
});

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

    <ul v-else class="min-w-0 space-y-3">
        <li v-for="bar in bars" :key="keyFor(bar)" class="min-w-0">
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
                        formatCurrency(bar.amount)
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
