<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InvestmentController from '@/actions/App/Http/Controllers/Finance/InvestmentController';
import CandlestickChart from '@/components/finance/CandlestickChart.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import * as marketRoutes from '@/routes/market';
import type { MarketInstrument } from '@/types';

const props = defineProps<{
    instruments: MarketInstrument[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mercato', href: marketRoutes.index() }],
    },
});

const selectedIsin = ref<string | null>(props.instruments[0]?.isin ?? null);

const selected = computed<MarketInstrument | null>(
    () =>
        props.instruments.find(
            (instrument) => instrument.isin === selectedIsin.value,
        ) ?? null,
);

function formatCurrency(value: number, currency = 'EUR') {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency,
    }).format(value);
}

function formatPercent(value: number) {
    return `${value > 0 ? '+' : ''}${new Intl.NumberFormat('it-IT', {
        maximumFractionDigits: 2,
    }).format(value)}%`;
}

function changeClass(value: number | null) {
    if (value === null) {
        return 'text-muted-foreground';
    }

    return value < 0 ? 'text-red-600' : 'text-green-600';
}

function smaDescription(instrument: MarketInstrument) {
    if (instrument.analysis.sma20_signal === 'above') {
        return 'Prezzo sopra la media: possibile trend rialzista';
    }

    if (instrument.analysis.sma20_signal === 'below') {
        return 'Prezzo sotto la media: possibile trend ribassista';
    }

    return 'Storico insufficiente';
}

function rsiDescription(signal: MarketInstrument['analysis']['rsi14_signal']) {
    switch (signal) {
        case 'overbought':
            return 'Ipercomprato';
        case 'oversold':
            return 'Ipervenduto';
        case 'neutral':
            return 'Neutro';
        default:
            return 'Storico insufficiente';
    }
}

function rsiClass(signal: MarketInstrument['analysis']['rsi14_signal']) {
    return signal === 'overbought' || signal === 'oversold' ? 'text-amber-600' : 'text-muted-foreground';
}
</script>

<template>
    <Head title="Mercato" />

    <div class="flex flex-col space-y-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Mercato"
                description="Le tue posizioni aperte con l'andamento a candele giornaliere. I prezzi sono aggiornati al massimo una volta al giorno."
            />
            <Form
                v-bind="InvestmentController.refresh.form()"
                v-slot="{ processing }"
            >
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="processing"
                    class="shrink-0"
                >
                    {{ processing ? 'Aggiornamento…' : 'Aggiorna ora' }}
                </Button>
            </Form>
        </div>

        <div
            v-if="instruments.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Nessuna posizione aperta da mostrare.
        </div>

        <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_360px]">
            <div class="min-w-0 space-y-3">
                <template v-if="selected">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h2 class="truncate text-lg font-semibold">
                                    {{ selected.name }}
                                </h2>
                                <Badge v-if="selected.is_crypto" variant="secondary">Crypto</Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ selected.isin }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-xl font-semibold">
                                {{ formatCurrency(selected.current_price ?? 0) }}
                            </p>
                            <p
                                v-if="selected.day_change !== null && selected.day_change_percent !== null"
                                class="text-sm font-medium"
                                :class="changeClass(selected.day_change_percent)"
                            >
                                {{ formatCurrency(selected.day_change) }}
                                ({{ formatPercent(selected.day_change_percent) }})
                            </p>
                        </div>
                    </div>

                    <p
                        v-if="selected.price_currency && selected.price_currency !== 'EUR'"
                        class="text-xs text-muted-foreground"
                    >
                        Il grafico mostra il prezzo originale in {{ selected.price_currency }}
                        (non convertito), per non alterare l'andamento storico reale.
                    </p>

                    <CandlestickChart :candles="selected.candles" />

                    <div class="rounded-lg border p-4">
                        <h3 class="mb-3 text-sm font-medium">Analisi tecnica</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                            <div>
                                <p class="text-xs text-muted-foreground">Media mobile (20gg)</p>
                                <p class="font-medium">
                                    {{
                                        selected.analysis.sma20 !== null
                                            ? formatCurrency(selected.analysis.sma20, selected.price_currency ?? 'EUR')
                                            : '—'
                                    }}
                                </p>
                                <p class="text-xs text-muted-foreground">{{ smaDescription(selected) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">RSI (14)</p>
                                <p class="font-medium">{{ selected.analysis.rsi14 ?? '—' }}</p>
                                <p class="text-xs" :class="rsiClass(selected.analysis.rsi14_signal)">
                                    {{ rsiDescription(selected.analysis.rsi14_signal) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Supporto più vicino</p>
                                <p class="font-medium">
                                    {{
                                        selected.analysis.support !== null
                                            ? formatCurrency(selected.analysis.support, selected.price_currency ?? 'EUR')
                                            : '—'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Resistenza più vicina</p>
                                <p class="font-medium">
                                    {{
                                        selected.analysis.resistance !== null
                                            ? formatCurrency(selected.analysis.resistance, selected.price_currency ?? 'EUR')
                                            : '—'
                                    }}
                                </p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Indicatori calcolati automaticamente sullo storico prezzi disponibile: non
                            costituiscono un consiglio di investimento.
                        </p>
                    </div>
                </template>
            </div>

            <div class="space-y-1">
                <h3 class="mb-2 text-sm font-medium">Posizioni</h3>
                <ul class="space-y-1">
                    <li v-for="instrument in instruments" :key="instrument.isin">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-md p-2 text-left text-sm transition-colors hover:bg-muted"
                            :class="{ 'bg-muted': instrument.isin === selectedIsin }"
                            @click="selectedIsin = instrument.isin"
                        >
                            <span class="min-w-0">
                                <span class="flex items-center gap-1.5">
                                    <span class="truncate font-medium">{{ instrument.name }}</span>
                                    <Badge v-if="instrument.is_crypto" variant="secondary" class="shrink-0">Crypto</Badge>
                                </span>
                                <span class="block text-xs text-muted-foreground">{{ instrument.isin }}</span>
                            </span>
                            <span class="shrink-0 text-right tabular-nums">
                                <span class="block font-medium">{{
                                    instrument.current_price !== null
                                        ? formatCurrency(instrument.current_price)
                                        : '—'
                                }}</span>
                                <span
                                    v-if="instrument.day_change_percent !== null"
                                    class="block text-xs font-medium"
                                    :class="changeClass(instrument.day_change_percent)"
                                >
                                    {{ formatPercent(instrument.day_change_percent) }}
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
