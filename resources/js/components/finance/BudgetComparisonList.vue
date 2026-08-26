<script setup lang="ts">
import { computed } from 'vue';
import type { BudgetComparisonRow } from '@/types';

const props = defineProps<{
    rows: BudgetComparisonRow[];
    currency: string;
}>();

const budgeted = computed(() => props.rows.filter((row) => row.budget !== null));

function formatCurrency(value: number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: props.currency,
    }).format(value);
}

function barWidth(row: BudgetComparisonRow): number {
    return Math.min(100, row.percent_used ?? 0);
}

function barColorClass(row: BudgetComparisonRow): string {
    const percent = row.percent_used ?? 0;

    if (percent >= 100) {
        return 'bg-red-500';
    }

    if (percent >= 80) {
        return 'bg-amber-500';
    }

    return 'bg-green-500';
}
</script>

<template>
    <div v-if="budgeted.length === 0" class="text-sm text-muted-foreground">
        Nessun budget impostato per le categorie di questa carta.
    </div>

    <ul v-else class="space-y-4">
        <li v-for="row in budgeted" :key="row.category_id">
            <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                <span class="truncate">{{ row.name }}</span>
                <span class="flex shrink-0 items-center gap-1 tabular-nums">
                    <span class="font-medium">{{ formatCurrency(row.spent) }}</span>
                    <span class="text-xs text-muted-foreground">/ {{ formatCurrency(row.budget ?? 0) }}</span>
                </span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full transition-all"
                    :class="barColorClass(row)"
                    :style="{ width: `${barWidth(row)}%` }"
                />
            </div>
            <p
                class="mt-1 text-xs"
                :class="(row.remaining ?? 0) < 0 ? 'text-red-600' : 'text-muted-foreground'"
            >
                <template v-if="(row.remaining ?? 0) < 0">
                    Superato di {{ formatCurrency(Math.abs(row.remaining ?? 0)) }}
                </template>
                <template v-else> Rimangono {{ formatCurrency(row.remaining ?? 0) }} </template>
            </p>
        </li>
    </ul>
</template>
