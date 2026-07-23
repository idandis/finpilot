<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import YearlyOverviewTable from '@/components/finance/YearlyOverviewTable.vue';
import Heading from '@/components/Heading.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import * as overviewRoutes from '@/routes/overview';
import type { OverviewTab } from '@/types';

const props = defineProps<{
    tabs: OverviewTab[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panoramica', href: overviewRoutes.index() }],
    },
});
</script>

<template>
    <Head title="Panoramica" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            title="Panoramica"
            description="Entrate e uscite mese per mese, su tutti i tuoi conti o per singola carta"
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
                <YearlyOverviewTable :overview="tab.overview" />
            </TabsContent>
        </Tabs>
    </div>
</template>
