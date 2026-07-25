<script setup lang="ts">
import {
    CandlestickSeries,
    createChart
    
    
} from 'lightweight-charts';
import type {IChartApi, ISeriesApi} from 'lightweight-charts';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

type Candle = {
    time: string;
    open: number;
    high: number;
    low: number;
    close: number;
};

const props = defineProps<{
    candles: Candle[];
}>();

const HEIGHT = 420;

// Same neutral chart-chrome tones already used across this app's other
// hand-drawn charts, just supplied as literal colors here since the chart
// renders to a <canvas>, which can't pick up Tailwind's dark: classes.
const THEME = {
    light: {
        background: '#fcfcfb',
        text: '#0b0b0b',
        grid: '#e1e0d9',
        border: '#c3c2b7',
    },
    dark: {
        background: '#1a1a19',
        text: '#ffffff',
        grid: '#2c2c2a',
        border: '#383835',
    },
};

// Matches the red-600/green-600 gain-loss convention already used
// throughout the rest of the app's position tables.
const UP_COLOR = '#16a34a';
const DOWN_COLOR = '#dc2626';

const container = ref<HTMLDivElement | null>(null);
let chart: IChartApi | null = null;
let series: ISeriesApi<'Candlestick'> | null = null;
let resizeObserver: ResizeObserver | null = null;

const { resolvedAppearance } = useAppearance();

function applyTheme() {
    if (!chart) {
        return;
    }

    const colors = THEME[resolvedAppearance.value];

    chart.applyOptions({
        layout: { background: { color: colors.background }, textColor: colors.text },
        grid: {
            vertLines: { color: colors.grid },
            horzLines: { color: colors.grid },
        },
        rightPriceScale: { borderColor: colors.border },
        timeScale: { borderColor: colors.border },
    });
}

function renderCandles() {
    series?.setData(props.candles);
    chart?.timeScale().fitContent();
}

onMounted(() => {
    if (!container.value) {
        return;
    }

    chart = createChart(container.value, {
        width: container.value.clientWidth,
        height: HEIGHT,
        timeScale: { timeVisible: false, borderVisible: true },
        rightPriceScale: { borderVisible: true },
        crosshair: { mode: 0 },
    });

    series = chart.addSeries(CandlestickSeries, {
        upColor: UP_COLOR,
        downColor: DOWN_COLOR,
        borderVisible: false,
        wickUpColor: UP_COLOR,
        wickDownColor: DOWN_COLOR,
    });

    applyTheme();
    renderCandles();

    resizeObserver = new ResizeObserver((entries) => {
        const width = entries[0]?.contentRect.width;

        if (chart && width) {
            chart.applyOptions({ width });
        }
    });
    resizeObserver.observe(container.value);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    chart?.remove();
    chart = null;
    series = null;
});

watch(() => props.candles, renderCandles);
watch(resolvedAppearance, applyTheme);
</script>

<template>
    <div
        v-if="candles.length === 0"
        class="flex items-center justify-center rounded-lg border text-sm text-muted-foreground"
        :style="{ height: `${HEIGHT}px` }"
    >
        Nessun dato storico disponibile per questo strumento.
    </div>
    <div v-else ref="container" class="w-full overflow-hidden rounded-lg border" />
</template>
