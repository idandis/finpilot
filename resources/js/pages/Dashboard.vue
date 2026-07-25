<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BankCard from '@/components/finance/BankCard.vue';
import InvestmentPositionsTables from '@/components/finance/InvestmentPositionsTables.vue';
import InvestmentSummaryCards from '@/components/finance/InvestmentSummaryCards.vue';
import PortfolioHistoryChart from '@/components/finance/PortfolioHistoryChart.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import * as cardRoutes from '@/routes/cards';
import * as investmentRoutes from '@/routes/investments';
import type { Card, InvestmentPositions, PortfolioHistory } from '@/types';

defineProps<{
    cards: Card[];
    positions: InvestmentPositions;
    portfolioHistory: PortfolioHistory;
    accountBalance: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-8 p-4">
        <div>
            <div class="flex items-start justify-between gap-4">
                <Heading title="Le tue carte" />
                <Button as-child variant="outline" size="sm" class="shrink-0">
                    <Link :href="cardRoutes.index()">Vedi tutte</Link>
                </Button>
            </div>

            <div
                v-if="cards.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Non hai ancora nessuna carta.
                <Link :href="cardRoutes.create()" class="underline">Creane una</Link>.
            </div>
            <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="card in cards"
                    :key="card.id"
                    :href="cardRoutes.show(card.id)"
                    class="transition-transform hover:-translate-y-0.5"
                >
                    <BankCard :card="card" />
                </Link>
            </div>

            <InvestmentSummaryCards
                v-if="positions.open.length > 0 || positions.closed.length > 0"
                class="mt-6"
                :positions="positions"
                :account-balance="accountBalance"
            />
        </div>

        <div>
            <div class="flex items-start justify-between gap-4">
                <Heading
                    title="Andamento portafoglio"
                    description="Investito vs valore di mercato, su tutte le carte da investimenti"
                />
                <Button as-child variant="outline" size="sm" class="shrink-0">
                    <Link :href="investmentRoutes.index()">Vai a Investimenti</Link>
                </Button>
            </div>
            <PortfolioHistoryChart :history="portfolioHistory" />
        </div>

        <InvestmentPositionsTables
            :positions="positions"
            :show-closed="false"
            layout="cards"
        />
    </div>
</template>
