<script setup lang="ts">
import { ChartBar, ChartPie } from '@lucide/vue';
import { ref } from 'vue';
import CategoryBarChart from '@/components/finance/CategoryBarChart.vue';
import CategorySpendingChart from '@/components/finance/CategorySpendingChart.vue';
import { Button } from '@/components/ui/button';
import type { CategoryBreakdownItem } from '@/types';

withDefaults(
    defineProps<{
        breakdown: CategoryBreakdownItem[];
        currency: string;
        emptyMessage?: string;
        centerLabel?: string;
        size?: 'md' | 'lg';
    }>(),
    {
        emptyMessage: 'Nessuna spesa da mostrare per questo mese.',
        centerLabel: 'Uscite',
        size: 'md',
    },
);

const chart = ref<'pie' | 'bar'>('pie');
</script>

<template>
    <div class="@container">
        <div class="mb-4 flex justify-end">
            <div class="inline-flex rounded-md border p-1">
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    :class="{ 'bg-muted': chart === 'pie' }"
                    @click="chart = 'pie'"
                >
                    <ChartPie class="size-4" />
                    Torta
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    :class="{ 'bg-muted': chart === 'bar' }"
                    @click="chart = 'bar'"
                >
                    <ChartBar class="size-4" />
                    Barre
                </Button>
            </div>
        </div>

        <CategorySpendingChart
            v-if="chart === 'pie'"
            :breakdown="breakdown"
            :currency="currency"
            :center-label="centerLabel"
            :empty-message="emptyMessage"
            :size="size"
        />
        <CategoryBarChart
            v-else
            :breakdown="breakdown"
            :currency="currency"
            :empty-message="emptyMessage"
        />
    </div>
</template>
