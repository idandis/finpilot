<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    marketCap: number | null;
    currentPrice: number | null;
    currency: string | null;
}>();

const emit = defineEmits<{
    apply: [value: number];
}>();

function impliedShares(): number | null {
    return props.marketCap !== null && props.currentPrice !== null && props.currentPrice > 0
        ? props.marketCap / props.currentPrice
        : null;
}

const revenue = ref<number | ''>('');
const sharesOutstanding = ref<number | ''>(impliedShares() ?? '');
const revenueGrowth = ref<number | ''>(10);
const targetMargin = ref<number | ''>(10);
const years = ref<number | ''>(7);
const discountRate = ref<number | ''>(9);
const terminalGrowth = ref<number | ''>(2.5);

watch(
    () => [props.marketCap, props.currentPrice],
    () => {
        sharesOutstanding.value = impliedShares() ?? '';
    },
);

type Scenario = {
    revenueGrowth: number;
    targetMargin: number;
    years: number;
    discountRate: number;
    terminalGrowth: number;
};

const SCENARIOS: Record<'conservative' | 'base' | 'optimistic', Scenario> = {
    conservative: { revenueGrowth: 5, targetMargin: 6, years: 5, discountRate: 10, terminalGrowth: 2 },
    base: { revenueGrowth: 10, targetMargin: 10, years: 7, discountRate: 9, terminalGrowth: 2.5 },
    optimistic: { revenueGrowth: 18, targetMargin: 14, years: 10, discountRate: 8, terminalGrowth: 3 },
};

function applyScenario(scenario: Scenario) {
    revenueGrowth.value = scenario.revenueGrowth;
    targetMargin.value = scenario.targetMargin;
    years.value = scenario.years;
    discountRate.value = scenario.discountRate;
    terminalGrowth.value = scenario.terminalGrowth;
}

const fairValuePerShare = computed<number | null>(() => {
    const rev0 = Number(revenue.value);
    const shares = Number(sharesOutstanding.value);
    const g = Number(revenueGrowth.value) / 100;
    const margin = Number(targetMargin.value) / 100;
    const r = Number(discountRate.value) / 100;
    const gt = Number(terminalGrowth.value) / 100;
    const n = Math.round(Number(years.value));

    if (!rev0 || !shares || shares <= 0 || margin <= 0 || !n || n <= 0 || r <= gt) {
        return null;
    }

    let presentValueSum = 0;
    let projectedRevenue = rev0;
    let lastYearFcf = 0;

    for (let year = 1; year <= n; year++) {
        projectedRevenue *= 1 + g;
        lastYearFcf = projectedRevenue * margin;
        presentValueSum += lastYearFcf / (1 + r) ** year;
    }

    const terminalValue = (lastYearFcf * (1 + gt)) / (r - gt);
    const presentTerminalValue = terminalValue / (1 + r) ** n;
    const equityValue = presentValueSum + presentTerminalValue;

    return equityValue / shares;
});

function formatValue(value: number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: props.currency ?? 'USD',
    }).format(value);
}

function apply() {
    if (fairValuePerShare.value !== null) {
        emit('apply', Math.round(fairValuePerShare.value * 100) / 100);
    }
}
</script>

<template>
    <div class="space-y-3 rounded-lg border p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-sm font-semibold">Calcola Fair Value (ricavi × margine obiettivo)</h4>
            <div class="flex gap-1.5">
                <Button type="button" size="sm" variant="outline" @click="applyScenario(SCENARIOS.conservative)">Prudente</Button>
                <Button type="button" size="sm" variant="outline" @click="applyScenario(SCENARIOS.base)">Normale</Button>
                <Button type="button" size="sm" variant="outline" @click="applyScenario(SCENARIOS.optimistic)">Ottimista</Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="grid gap-1">
                <Label for="dcf-revenue">Ricavi attuali (ultimi 12 mesi)</Label>
                <Input id="dcf-revenue" type="number" step="any" v-model="revenue" />
            </div>
            <div class="grid gap-1">
                <Label for="dcf-shares">Azioni in circolazione</Label>
                <Input id="dcf-shares" type="number" step="any" v-model="sharesOutstanding" />
            </div>
            <div class="grid gap-1">
                <Label for="dcf-growth">Crescita ricavi attesa (%)</Label>
                <Input id="dcf-growth" type="number" step="any" v-model="revenueGrowth" />
            </div>
            <div class="grid gap-1">
                <Label for="dcf-margin">Margine FCF obiettivo (%)</Label>
                <Input id="dcf-margin" type="number" step="any" v-model="targetMargin" />
            </div>
            <div class="grid gap-1">
                <Label for="dcf-years">Anni di proiezione</Label>
                <Input id="dcf-years" type="number" step="1" v-model="years" />
            </div>
            <div class="grid gap-1">
                <Label for="dcf-discount">Tasso di sconto / WACC (%)</Label>
                <Input id="dcf-discount" type="number" step="any" v-model="discountRate" />
            </div>
            <div class="grid gap-1">
                <Label for="dcf-terminal">Crescita terminale (%)</Label>
                <Input id="dcf-terminal" type="number" step="any" v-model="terminalGrowth" />
            </div>
        </div>

        <div v-if="fairValuePerShare !== null" class="flex items-center justify-between rounded-md bg-muted p-3">
            <div>
                <p class="text-xs text-muted-foreground">Fair Value stimato</p>
                <p class="text-lg font-semibold">{{ formatValue(fairValuePerShare) }}</p>
            </div>
            <Button type="button" size="sm" variant="outline" @click="apply">Usa questo valore</Button>
        </div>
        <p v-else class="text-xs text-destructive">
            Inserisci ricavi, azioni in circolazione e un margine obiettivo positivo (e verifica che il tasso di
            sconto sia maggiore della crescita terminale) per calcolare.
        </p>

        <p class="text-xs text-muted-foreground">
            Proietta i ricavi (più stabili e prevedibili dell'FCF di un singolo anno) e applica un margine di cassa
            "a regime" che scegli tu: utile per aziende che stanno investendo pesantemente ora (capex elevato) e il
            cui FCF attuale non riflette la reale capacità di generare cassa una volta a maturità. I tre tasti sono
            punti di partenza generici, non una ricerca sull'azienda specifica: resta un modello semplificato,
            molto sensibile alle ipotesi inserite - non è un consiglio di investimento.
        </p>
    </div>
</template>
