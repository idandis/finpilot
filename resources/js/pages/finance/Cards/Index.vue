<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BankCard from '@/components/finance/BankCard.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import * as accountRoutes from '@/routes/accounts';
import * as cardRoutes from '@/routes/cards';
import type { Card } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Carte', href: cardRoutes.index() }],
    },
});

defineProps<{
    cards: Card[];
}>();
</script>

<template>
    <Head title="Carte" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            title="Le tue carte"
            description="Tutte le carte associate ai tuoi conti"
        />

        <div
            v-if="cards.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Non hai ancora nessuna carta. Aggiungine una dalla pagina di
            modifica di un conto.
            <div class="mt-4">
                <Button as-child variant="outline">
                    <Link :href="accountRoutes.index()">Vai ai conti</Link>
                </Button>
            </div>
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
    </div>
</template>
