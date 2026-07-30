<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import InvestmentController from '@/actions/App/Http/Controllers/Finance/InvestmentController';
import InvestmentPositionsTables from '@/components/finance/InvestmentPositionsTables.vue';
import InvestmentSummaryCards from '@/components/finance/InvestmentSummaryCards.vue';
import PortfolioCompositionChart from '@/components/finance/PortfolioCompositionChart.vue';
import PortfolioHistoryChart from '@/components/finance/PortfolioHistoryChart.vue';
import YearlyInvestmentFlowTable from '@/components/finance/YearlyInvestmentFlowTable.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import * as investmentRoutes from '@/routes/investments';
import type { InvestmentTab } from '@/types';

function reloadAfterRefresh() {
    setTimeout(() => router.reload(), 500);
}

const props = defineProps<{
    tabs: InvestmentTab[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Investimenti', href: investmentRoutes.index() },
        ],
    },
});
</script>

<template>
    <Head title="Investimenti" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Investimenti"
                description=""
            />
            <div class="flex shrink-0 gap-2">
                <Form
                    v-bind="InvestmentController.refresh.form()"
                    @submit="reloadAfterRefresh"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="processing"
                    >
                        {{ processing ? 'Aggiornamento…' : 'Aggiorna chiusura' }}
                    </Button>
                </Form>
                <Form
                    v-bind="InvestmentController.refreshRealtime.form()"
                    @submit="reloadAfterRefresh"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="processing"
                    >
                        {{ processing ? 'Aggiornamento…' : 'Aggiorna realtime' }}
                    </Button>
                </Form>
            </div>
        </div>

        <Tabs :default-value="props.tabs[0].id">
            <TabsList>
                <TabsTrigger
                    v-for="tab in props.tabs"
                    :key="tab.id"
                    :value="tab.id"
                >
                    {{ tab.name }}
                </TabsTrigger>
            </TabsList>

            <TabsContent
                v-for="tab in props.tabs"
                :key="tab.id"
                :value="tab.id"
                class="space-y-6 pt-4"
            >
                <InvestmentSummaryCards
                    :positions="tab.positions"
                    :account-balance="tab.accountBalance"
                />

                <Tabs default-value="positions">
                    <TabsList>
                        <TabsTrigger value="cash-flow"
                            >Bilancio</TabsTrigger
                        >
                        <TabsTrigger value="positions">Posizioni</TabsTrigger>
                        <TabsTrigger value="composition"
                            >Composizione</TabsTrigger
                        >
                        <TabsTrigger value="history">Andamento</TabsTrigger>
                    </TabsList>

                    <TabsContent value="cash-flow" class="pt-4">
                        <YearlyInvestmentFlowTable :overview="tab.cashFlow" />
                    </TabsContent>

                    <TabsContent value="positions" class="pt-4">
                        <InvestmentPositionsTables :positions="tab.positions" />
                    </TabsContent>

                    <TabsContent value="composition" class="@container pt-4">
                        <PortfolioCompositionChart
                            :positions="tab.positions"
                            :account-balance="tab.accountBalance"
                        />
                    </TabsContent>

                    <TabsContent value="history" class="space-y-6 pt-4">
                        <div>
                            <h3 class="mb-4 text-sm font-medium">
                                Andamento investimenti
                            </h3>
                            <PortfolioHistoryChart :history="tab.portfolioHistory" />
                        </div>

                        <div v-if="tab.wealthHistory">
                            <h3 class="mb-4 text-sm font-medium">
                                Andamento del patrimonio
                            </h3>
                            <PortfolioHistoryChart
                                :history="tab.wealthHistory"
                                market-value-label="Patrimonio totale"
                                invested-label="Saldo conto"
                                :show-footnotes="false"
                            />
                        </div>
                    </TabsContent>
                </Tabs>
            </TabsContent>
        </Tabs>
    </div>
</template>
