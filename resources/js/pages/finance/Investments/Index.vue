<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import InvestmentPositionsTables from '@/components/finance/InvestmentPositionsTables.vue';
import YearlyInvestmentFlowTable from '@/components/finance/YearlyInvestmentFlowTable.vue';
import Heading from '@/components/Heading.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import * as investmentRoutes from '@/routes/investments';
import type { InvestmentTab } from '@/types';

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

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            title="Investimenti"
            description="Flusso di cassa verso gli investimenti e le posizioni aperte/chiuse per strumento. Non è il rendimento reale: le posizioni aperte mostrano quanto hai pagato, non quanto valgono oggi."
        />

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
                class="pt-4"
            >
                <Tabs default-value="cash-flow">
                    <TabsList>
                        <TabsTrigger value="cash-flow"
                            >Flusso di cassa</TabsTrigger
                        >
                        <TabsTrigger value="positions">Posizioni</TabsTrigger>
                    </TabsList>

                    <TabsContent value="cash-flow" class="pt-4">
                        <YearlyInvestmentFlowTable :overview="tab.cashFlow" />
                    </TabsContent>

                    <TabsContent value="positions" class="pt-4">
                        <InvestmentPositionsTables :positions="tab.positions" />
                    </TabsContent>
                </Tabs>
            </TabsContent>
        </Tabs>
    </div>
</template>
