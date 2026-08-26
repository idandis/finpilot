<script setup lang="ts">
import { ChartBar, ChartPie } from '@lucide/vue';
import { ref } from 'vue';
import PortfolioBarChart from '@/components/finance/PortfolioBarChart.vue';
import PortfolioPieChart from '@/components/finance/PortfolioPieChart.vue';
import { Button } from '@/components/ui/button';
import type { InvestmentPositions } from '@/types';

defineProps<{
    positions: InvestmentPositions;
    accountBalance: number | null;
}>();

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

        <PortfolioPieChart
            v-if="chart === 'pie'"
            :positions="positions"
            :account-balance="accountBalance"
        />
        <PortfolioBarChart
            v-else
            :positions="positions"
            :account-balance="accountBalance"
        />
    </div>
</template>
