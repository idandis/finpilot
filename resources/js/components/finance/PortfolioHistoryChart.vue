<script setup lang="ts">
import { AreaSeries, LineSeries, LineStyle, createChart } from 'lightweight-charts';
import type { IChartApi, ISeriesApi, MouseEventParams, Time } from 'lightweight-charts';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { PortfolioHistory } from '@/types';
import { useAppearance } from '@/composables/useAppearance';

const props = withDefaults(
    defineProps<{
        history: PortfolioHistory;
        marketValueLabel?: string;
        investedLabel?: string;
        /** Hide the "data available since"/"unpriced positions" notes - for a second chart reusing the same underlying gap, already explained above it. */
        showFootnotes?: boolean;
    }>(),
    { marketValueLabel: 'Valore di mercato', investedLabel: 'Investito', showFootnotes: true },
);

// Chart colors kept independent from the red/green gain-loss convention used
// elsewhere (InvestmentPositionsTables.vue), since these are two distinct
// series (not a positive/negative encoding) - validated for lightness band,
// chroma, CVD separation and contrast in both light and dark surfaces via the
// dataviz skill's validator, so no separate dark: variant is needed.
const MARKET_VALUE_COLOR = '#3b82f6';
const INVESTED_COLOR = '#d97706';

const HEIGHT = 240;

// Same neutral chart-chrome tones already used in CandlestickChart.vue, just
// supplied as literal colors here since the chart renders to a <canvas>,
// which can't pick up Tailwind's dark: classes.
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

const container = ref<HTMLDivElement | null>(null);
let chart: IChartApi | null = null;
let marketValueSeries: ISeriesApi<'Area'> | null = null;
let investedSeries: ISeriesApi<'Line'> | null = null;
let resizeObserver: ResizeObserver | null = null;

const { resolvedAppearance } = useAppearance();

const points = computed(() => props.history.points);

function formatCurrency(value: number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatDate(value: string) {
    return new Date(value).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatQuantity(value: number) {
    return new Intl.NumberFormat('it-IT', {
        maximumFractionDigits: 8,
    }).format(value);
}

const hasGap = computed(
    () =>
        props.history.market_data_since !== null &&
        points.value.length > 0 &&
        props.history.market_data_since !== points.value[0].date,
);

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

function renderData() {
    // Whitespace points (time only, no value) break the market-value line
    // across gaps instead of interpolating through them - same "never
    // interpolate across a gap" behavior the previous hand-drawn chart had.
    marketValueSeries?.setData(
        points.value.map((point) =>
            point.market_value !== null
                ? { time: point.date as Time, value: point.market_value }
                : { time: point.date as Time },
        ),
    );
    investedSeries?.setData(points.value.map((point) => ({ time: point.date as Time, value: point.invested })));
    chart?.timeScale().fitContent();
}

const hoverPoint = ref<{ date: string; marketValue: number | null; invested: number } | null>(null);

function handleCrosshairMove(param: MouseEventParams) {
    if (!param.time || !investedSeries) {
        hoverPoint.value = null;

        return;
    }

    const investedData = param.seriesData.get(investedSeries) as { value: number } | undefined;
    const marketValueData = marketValueSeries
        ? (param.seriesData.get(marketValueSeries) as { value: number } | undefined)
        : undefined;

    if (!investedData) {
        hoverPoint.value = null;

        return;
    }

    hoverPoint.value = {
        date: param.time as string,
        marketValue: marketValueData?.value ?? null,
        invested: investedData.value,
    };
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

    marketValueSeries = chart.addSeries(AreaSeries, {
        lineColor: MARKET_VALUE_COLOR,
        topColor: 'rgba(59, 130, 246, 0.18)',
        bottomColor: 'rgba(59, 130, 246, 0)',
        lineWidth: 2,
    });

    investedSeries = chart.addSeries(LineSeries, {
        color: INVESTED_COLOR,
        lineWidth: 2,
        lineStyle: LineStyle.Dashed,
    });

    applyTheme();
    renderData();
    chart.subscribeCrosshairMove(handleCrosshairMove);

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
    marketValueSeries = null;
    investedSeries = null;
});

watch(() => props.history, renderData);
watch(resolvedAppearance, applyTheme);
</script>

<template>
    <div v-if="points.length < 2" class="text-sm text-muted-foreground">
        Dati insufficienti per mostrare l'andamento del portafoglio.
    </div>

    <div v-else class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm">
            <div class="flex items-center gap-4">
                <span class="flex items-center gap-2">
                    <span class="h-0.5 w-4 rounded-full" :style="{ backgroundColor: MARKET_VALUE_COLOR }" />
                    {{ marketValueLabel }}
                </span>
                <span class="flex items-center gap-2">
                    <svg width="16" height="2" class="shrink-0">
                        <line
                            x1="0"
                            y1="1"
                            x2="16"
                            y2="1"
                            :stroke="INVESTED_COLOR"
                            stroke-width="2"
                            stroke-dasharray="3 2"
                        />
                    </svg>
                    {{ investedLabel }}
                </span>
            </div>

            <div v-if="hoverPoint" class="flex items-center gap-3 text-xs tabular-nums text-muted-foreground">
                <span>{{ formatDate(hoverPoint.date) }}</span>
                <span v-if="hoverPoint.marketValue !== null" :style="{ color: MARKET_VALUE_COLOR }">
                    {{ formatCurrency(hoverPoint.marketValue) }}
                </span>
                <span :style="{ color: INVESTED_COLOR }">{{ formatCurrency(hoverPoint.invested) }}</span>
            </div>
        </div>

        <div ref="container" class="w-full overflow-hidden rounded-lg border" />

        <p v-if="showFootnotes && hasGap" class="text-xs text-muted-foreground">
            Dati di mercato disponibili da {{ formatDate(history.market_data_since!) }}.
        </p>

        <div v-if="showFootnotes && history.unpriced_positions.length > 0" class="space-y-1.5 border-t pt-3">
            <p class="text-xs text-muted-foreground">
                Nessun prezzo di mercato disponibile per queste posizioni - non incluse né nell'investito né nel valore di mercato sopra:
            </p>
            <ul class="space-y-1">
                <li
                    v-for="position in history.unpriced_positions"
                    :key="position.isin"
                    class="flex items-center justify-between gap-3 text-sm"
                >
                    <span class="truncate">{{ position.name }}</span>
                    <span class="flex shrink-0 items-center gap-2 tabular-nums text-muted-foreground">
                        <span>{{ formatQuantity(position.quantity) }}</span>
                        <span class="font-medium text-foreground">{{ formatCurrency(position.invested) }}</span>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
