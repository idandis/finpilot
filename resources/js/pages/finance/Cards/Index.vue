<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BankCard from '@/components/finance/BankCard.vue';
import YearlyOverviewTable from '@/components/finance/YearlyOverviewTable.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import * as cardRoutes from '@/routes/cards';
import type { Card, OverviewTab } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Carte', href: cardRoutes.index() }],
    },
});

defineProps<{
    cards: Card[];
    overviewTabs: OverviewTab[];
}>();
</script>

<template>
    <Head title="Carte" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Le tue carte"
                description="Tutte le tue carte, collegate o meno a un conto"
            />
            <Button as-child>
                <Link :href="cardRoutes.create()">Crea una carta</Link>
            </Button>
        </div>

        <div
            v-if="cards.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Non hai ancora nessuna carta.
        </div>

        <div
            v-else
            class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
        >
            <Link
                v-for="card in cards"
                :key="card.id"
                :href="cardRoutes.show(card.id)"
                class="transition-transform hover:-translate-y-0.5"
            >
                <BankCard :card="card" />
            </Link>
        </div>

        <div v-if="overviewTabs.length > 0" class="space-y-4">
            <Heading
                title="Panoramica"
                description="Entrate e uscite mese per mese, per singola carta"
            />

            <Tabs :default-value="overviewTabs[0].id">
                <TabsList>
                    <TabsTrigger
                        v-for="tab in overviewTabs"
                        :key="tab.id"
                        :value="tab.id"
                    >
                        {{ tab.name }}
                    </TabsTrigger>
                </TabsList>

                <TabsContent
                    v-for="tab in overviewTabs"
                    :key="tab.id"
                    :value="tab.id"
                    class="pt-4"
                >
                    <YearlyOverviewTable :overview="tab.overview" />
                </TabsContent>
            </Tabs>
        </div>
    </div>
</template>
