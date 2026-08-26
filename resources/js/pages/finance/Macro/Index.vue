<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import MacroController from '@/actions/App/Http/Controllers/Finance/MacroController';
import Heading from '@/components/Heading.vue';
import MacroSparkline from '@/components/finance/MacroSparkline.vue';
import MarketOverviewInstrumentCard from '@/components/finance/MarketOverviewInstrumentCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import * as macroRoutes from '@/routes/macro';
import type {
    EconomicRegime,
    MacroCategoryGroup,
    MacroIndicator,
    MacroIndicatorTrend,
    MarketOverviewCategoryGroup,
    MarketOverviewInstrument,
    MarketSentiment,
    RiskSentiment,
} from '@/types';

defineProps<{
    categories: MacroCategoryGroup[];
    markets: MarketOverviewCategoryGroup[];
    sectors: MarketOverviewInstrument[];
    regime: EconomicRegime;
    riskSentiment: RiskSentiment;
    sentiment: MarketSentiment;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Macro', href: macroRoutes.index() }],
    },
});

function reloadAfterRefresh() {
    setTimeout(() => router.reload(), 500);
}

const activeTab = ref('macro');

const REGION_LABELS: Record<string, string> = {
    usa: 'USA',
    eurozone: 'Eurozona',
    italia: 'Italia',
    globale: 'Globale',
};

const REGION_BADGE_CLASS: Record<string, string> = {
    usa: 'bg-red-600 text-white',
    eurozone: 'bg-blue-600 text-white',
    italia: 'bg-green-600 text-white',
    globale: '',
};

const SOURCE_LABELS: Record<string, string> = {
    fred: 'FRED',
    ecb: 'BCE',
};

const RISK_CLASS: Record<string, string> = {
    'Risk On': 'text-green-600',
    'Moderatamente Risk On': 'text-green-600',
    Neutrale: 'text-foreground',
    'Moderatamente Risk Off': 'text-red-600',
    'Risk Off': 'text-red-600',
};

const SENTIMENT_CLASS: Record<string, string> = {
    Ottimismo: 'text-green-600',
    'Moderato ottimismo': 'text-green-600',
    Neutrale: 'text-foreground',
    'Moderato pessimismo': 'text-red-600',
    Pessimismo: 'text-red-600',
};

const TREND_CLASS: Record<MacroIndicatorTrend, string> = {
    improving: 'text-green-600',
    worsening: 'text-red-600',
    neutral: 'text-foreground',
    stable: 'text-muted-foreground',
};

function formatValue(value: number, unit: string) {
    const formatted = new Intl.NumberFormat('it-IT', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 2,
    }).format(value);

    return unit === '%' || unit === 'p.p.' ? `${formatted}${unit}` : `${formatted} ${unit}`;
}

function formatChange(indicator: MacroIndicator) {
    if (indicator.change_absolute === null) {
        return null;
    }

    const sign = indicator.change_absolute > 0 ? '+' : '';

    return `${sign}${formatValue(indicator.change_absolute, indicator.unit)}`;
}

function formatDate(value: string) {
    return new Date(`${value}T00:00:00`).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

</script>

<template>
    <Head title="Macro" />

    <div class="mx-auto flex w-full max-w-[72rem] flex-col space-y-8 p-4">
        <Heading
            title="Macro"
            description="Panoramica macroeconomica e overview dei mercati per USA ed Eurozona."
        />

        <Tabs v-model="activeTab">
            <div class="flex items-center justify-between gap-4">
                <TabsList>
                    <TabsTrigger value="macro">Panoramica macro</TabsTrigger>
                    <TabsTrigger value="markets">Mercati</TabsTrigger>
                    <TabsTrigger value="sectors">Settori</TabsTrigger>
                </TabsList>

                <Form
                    v-if="activeTab === 'macro'"
                    v-bind="MacroController.refresh.form()"
                    @submit="reloadAfterRefresh"
                    v-slot="{ processing }"
                >
                    <Button type="submit" variant="outline" size="sm" :disabled="processing">
                        {{ processing ? 'Aggiornamento…' : 'Aggiorna ora' }}
                    </Button>
                </Form>
                <Form
                    v-else-if="activeTab === 'markets' || activeTab === 'sectors'"
                    v-bind="MacroController.refreshMarkets.form()"
                    @submit="reloadAfterRefresh"
                    v-slot="{ processing }"
                >
                    <Button type="submit" variant="outline" size="sm" :disabled="processing">
                        {{ processing ? 'Aggiornamento…' : 'Aggiorna ora' }}
                    </Button>
                </Form>
            </div>

            <TabsContent value="macro" class="space-y-8 pt-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="space-y-3 rounded-lg bg-muted/40 p-4">
                        <div>
                            <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Regime economico (USA)
                            </p>
                            <p class="mt-1 text-xl font-semibold">{{ regime.label }}</p>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ regime.description }}</p>
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 flex-1 rounded-full bg-background">
                                <div
                                    class="h-1.5 rounded-full bg-primary"
                                    :style="{ width: `${regime.confidence_percent}%` }"
                                />
                            </div>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ regime.confidence_percent }}% confidenza</span>
                        </div>
                        <ul v-if="regime.signals.length > 0" class="grid grid-cols-1 gap-1 text-xs sm:grid-cols-2">
                            <li
                                v-for="signal in regime.signals"
                                :key="signal.label"
                                class="flex items-center gap-1.5"
                                :class="signal.satisfied ? 'text-foreground' : 'text-muted-foreground'"
                            >
                                <span>{{ signal.satisfied ? '✓' : '✗' }}</span>
                                {{ signal.label }}
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-3 rounded-lg bg-muted/40 p-4">
                        <div>
                            <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Condizione di mercato
                            </p>
                            <p class="mt-1 text-xl font-semibold" :class="RISK_CLASS[riskSentiment.condition]">
                                {{ riskSentiment.condition }}
                            </p>
                        </div>
                        <div v-if="riskSentiment.positive_signals.length > 0">
                            <p class="text-xs font-medium text-green-600">Segnali positivi</p>
                            <p class="text-sm text-muted-foreground">{{ riskSentiment.positive_signals.join(', ') }}</p>
                        </div>
                        <div v-if="riskSentiment.caution_signals.length > 0">
                            <p class="text-xs font-medium text-red-600">Segnali di cautela</p>
                            <p class="text-sm text-muted-foreground">{{ riskSentiment.caution_signals.join(', ') }}</p>
                        </div>
                        <p
                            v-if="riskSentiment.positive_signals.length === 0 && riskSentiment.caution_signals.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Dati insufficienti per calcolare la condizione di mercato.
                        </p>
                    </div>

                    <div class="space-y-3 rounded-lg bg-muted/40 p-4">
                        <div>
                            <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Sentiment di mercato
                            </p>
                            <p class="mt-1 text-xl font-semibold" :class="SENTIMENT_CLASS[sentiment.sentiment]">
                                {{ sentiment.sentiment }}
                            </p>
                        </div>
                        <div v-if="sentiment.positive_signals.length > 0">
                            <p class="text-xs font-medium text-green-600">Segnali positivi</p>
                            <p class="text-sm text-muted-foreground">{{ sentiment.positive_signals.join(', ') }}</p>
                        </div>
                        <div v-if="sentiment.caution_signals.length > 0">
                            <p class="text-xs font-medium text-red-600">Segnali di cautela</p>
                            <p class="text-sm text-muted-foreground">{{ sentiment.caution_signals.join(', ') }}</p>
                        </div>
                        <p
                            v-if="sentiment.positive_signals.length === 0 && sentiment.caution_signals.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Nessun segnale netto al momento (dati mancanti, oppure VIX/ampiezza/vicinanza ai massimi
                            tutti nella fascia neutra).
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Non incluso (nessuna fonte gratuita): {{ sentiment.omitted_signals.join(' · ') }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="categories.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Nessun indicatore disponibile ancora. Esegui <code>php artisan macro:refresh-indicators</code>
                    per caricare i dati da FRED e dalla BCE.
                </div>

                <div v-for="category in categories" :key="category.key" class="space-y-4">
                    <h3 class="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                        {{ category.label }}
                    </h3>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="indicator in category.indicators"
                            :key="indicator.key"
                            class="space-y-3 rounded-lg bg-muted/40 p-4"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="line-clamp-2 text-sm font-medium">{{ indicator.label }}</p>
                                    <Badge variant="secondary" class="mt-1" :class="REGION_BADGE_CLASS[indicator.region]">{{ REGION_LABELS[indicator.region] }}</Badge>
                                </div>
                                <MacroSparkline :points="indicator.history" :width="120" class="mt-0.5" />
                            </div>

                            <div class="flex items-end justify-between gap-2">
                                <div>
                                    <p class="text-2xl font-semibold tabular-nums">
                                        {{ indicator.current ? formatValue(indicator.current.value, indicator.unit) : '—' }}
                                    </p>
                                    <p v-if="indicator.previous" class="text-xs text-muted-foreground">
                                        Precedente: {{ formatValue(indicator.previous.value, indicator.unit) }}
                                        ({{ formatDate(indicator.previous.date) }})
                                    </p>
                                </div>
                                <p
                                    v-if="formatChange(indicator)"
                                    class="shrink-0 text-sm font-medium tabular-nums"
                                    :class="TREND_CLASS[indicator.trend]"
                                >
                                    {{ formatChange(indicator) }}
                                </p>
                            </div>

                            <p class="text-sm text-muted-foreground">{{ indicator.narrative }}</p>

                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Fonte: {{ SOURCE_LABELS[indicator.source] }}</span>
                                <span v-if="indicator.current">{{ formatDate(indicator.current.date) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </TabsContent>

            <TabsContent value="markets" class="space-y-8 pt-6">
                <div
                    v-if="markets.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Nessuno strumento disponibile ancora. Esegui <code>php artisan market-overview:refresh</code>
                    per caricare i dati da EODHD.
                </div>

                <div v-for="category in markets" :key="category.key" class="space-y-4">
                    <h3 class="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                        {{ category.label }}
                    </h3>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <MarketOverviewInstrumentCard
                            v-for="instrument in category.instruments"
                            :key="instrument.key"
                            :instrument="instrument"
                        />
                    </div>
                </div>
            </TabsContent>

            <TabsContent value="sectors" class="space-y-8 pt-6">
                <div
                    v-if="sectors.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Nessuno strumento disponibile ancora. Esegui <code>php artisan market-overview:refresh</code>
                    per caricare i dati da EODHD.
                </div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <MarketOverviewInstrumentCard
                        v-for="instrument in sectors"
                        :key="instrument.key"
                        :instrument="instrument"
                    />
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
