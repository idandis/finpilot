<script setup lang="ts">
import { computed, ref } from 'vue';
import type { PortfolioHistory } from '@/types';

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

const WIDTH = 600;
const HEIGHT = 220;
const PAD = { top: 12, right: 12, bottom: 28, left: 56 };
const PLOT_WIDTH = WIDTH - PAD.left - PAD.right;
const PLOT_HEIGHT = HEIGHT - PAD.top - PAD.bottom;
const GRID_TICKS = 6;

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

function formatMonthYear(value: string) {
    return new Date(value).toLocaleDateString('it-IT', {
        month: 'short',
        year: '2-digit',
    });
}

function formatQuantity(value: number) {
    return new Intl.NumberFormat('it-IT', {
        maximumFractionDigits: 8,
    }).format(value);
}

const valueRange = computed(() => {
    const values = points.value.flatMap((point) =>
        point.market_value !== null
            ? [point.invested, point.market_value]
            : [point.invested],
    );

    const min = Math.min(0, ...values);
    const max = Math.max(0, ...values);
    const padding = (max - min) * 0.1 || 1;

    return { min: min - padding, max: max + padding };
});

function xFor(index: number) {
    const count = points.value.length;

    return count <= 1
        ? PAD.left + PLOT_WIDTH / 2
        : PAD.left + (index / (count - 1)) * PLOT_WIDTH;
}

function yFor(value: number) {
    const { min, max } = valueRange.value;
    const fraction = (value - min) / (max - min);

    return PAD.top + (1 - fraction) * PLOT_HEIGHT;
}

const investedPath = computed(() =>
    points.value
        .map((point, index) => `${index === 0 ? 'M' : 'L'} ${xFor(index)} ${yFor(point.invested)}`)
        .join(' '),
);

/** Contiguous runs of defined market_value - never interpolated across a gap. */
const marketValueSegments = computed(() => {
    const segments: { line: string; area: string }[] = [];
    let current: { index: number; value: number }[] = [];

    const flush = () => {
        if (current.length < 2) {
            current = [];

            return;
        }

        const line = current
            .map((p, i) => `${i === 0 ? 'M' : 'L'} ${xFor(p.index)} ${yFor(p.value)}`)
            .join(' ');

        const baseline = PAD.top + PLOT_HEIGHT;
        const first = current[0];
        const last = current[current.length - 1];
        const area = `M ${xFor(first.index)} ${baseline} L ${current.map((p) => `${xFor(p.index)} ${yFor(p.value)}`).join(' L ')} L ${xFor(last.index)} ${baseline} Z`;

        segments.push({ line, area });
        current = [];
    };

    points.value.forEach((point, index) => {
        if (point.market_value === null) {
            flush();

            return;
        }

        current.push({ index, value: point.market_value });
    });
    flush();

    return segments;
});

const gridLines = computed(() => {
    const { min, max } = valueRange.value;

    return Array.from({ length: GRID_TICKS + 1 }, (_, i) => {
        const value = min + ((max - min) * i) / GRID_TICKS;

        return { value, y: yFor(value) };
    });
});

/**
 * One label per calendar month covered by the data, plus always the last
 * point (today) - with a minimum pixel gap so adjacent labels (e.g. a month
 * boundary landing right next to today, or two boundaries a single weekly
 * point apart) never overlap. When two candidates collide, the earlier one
 * is dropped in favor of the later one, so "today" is always shown.
 */
const monthTicks = computed(() => {
    const candidates: { index: number; label: string }[] = [];
    let lastMonthKey: string | null = null;

    points.value.forEach((point, index) => {
        const monthKey = point.date.slice(0, 7);

        if (monthKey !== lastMonthKey) {
            candidates.push({ index, label: formatMonthYear(point.date) });
            lastMonthKey = monthKey;
        }
    });

    const lastIndex = points.value.length - 1;

    if (candidates.length === 0 || candidates[candidates.length - 1].index !== lastIndex) {
        candidates.push({ index: lastIndex, label: formatMonthYear(points.value[lastIndex].date) });
    }

    const MIN_GAP = 40;
    const kept: { index: number; label: string }[] = [];

    candidates.forEach((candidate, i) => {
        const previous = kept[kept.length - 1];

        if (!previous || xFor(candidate.index) - xFor(previous.index) >= MIN_GAP) {
            kept.push(candidate);
        } else if (i === candidates.length - 1) {
            kept.pop();
            kept.push(candidate);
        }
    });

    return kept;
});

const hoveredIndex = ref<number | null>(null);

const hoveredPoint = computed(() =>
    hoveredIndex.value !== null ? points.value[hoveredIndex.value] : null,
);

const hasGap = computed(
    () =>
        props.history.market_data_since !== null &&
        points.value.length > 0 &&
        props.history.market_data_since !== points.value[0].date,
);
</script>

<template>
    <div v-if="points.length < 2" class="text-sm text-muted-foreground">
        Dati insufficienti per mostrare l'andamento del portafoglio.
    </div>

    <div v-else class="space-y-3">
        <div class="flex items-center gap-4 text-sm">
            <span class="flex items-center gap-2">
                <span
                    class="h-0.5 w-4 rounded-full"
                    :style="{ backgroundColor: MARKET_VALUE_COLOR }"
                />
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

        <svg :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" class="w-full">
            <g v-for="grid in gridLines" :key="grid.value">
                <line
                    :x1="PAD.left"
                    :x2="WIDTH - PAD.right"
                    :y1="grid.y"
                    :y2="grid.y"
                    class="stroke-muted-foreground/15"
                    stroke-width="1"
                />
                <text
                    :x="PAD.left - 6"
                    :y="grid.y"
                    text-anchor="end"
                    dominant-baseline="middle"
                    class="fill-muted-foreground text-[9px]"
                >
                    {{ formatCurrency(grid.value) }}
                </text>
            </g>

            <path
                v-for="(segment, index) in marketValueSegments"
                :key="`area-${index}`"
                :d="segment.area"
                :fill="MARKET_VALUE_COLOR"
                fill-opacity="0.12"
                stroke="none"
            />
            <path
                v-for="(segment, index) in marketValueSegments"
                :key="`line-${index}`"
                :d="segment.line"
                fill="none"
                :stroke="MARKET_VALUE_COLOR"
                stroke-width="2"
                stroke-linejoin="round"
            />

            <path
                :d="investedPath"
                fill="none"
                :stroke="INVESTED_COLOR"
                stroke-width="2"
                stroke-dasharray="5 3"
                stroke-linejoin="round"
            />

            <line
                v-if="hoveredIndex !== null"
                :x1="xFor(hoveredIndex)"
                :x2="xFor(hoveredIndex)"
                :y1="PAD.top"
                :y2="PAD.top + PLOT_HEIGHT"
                class="stroke-muted-foreground/40"
                stroke-width="1"
                stroke-dasharray="2 2"
            />
            <circle
                v-if="hoveredPoint && hoveredPoint.market_value !== null"
                :cx="xFor(hoveredIndex!)"
                :cy="yFor(hoveredPoint.market_value)"
                r="3.5"
                :fill="MARKET_VALUE_COLOR"
            />
            <circle
                v-if="hoveredPoint"
                :cx="xFor(hoveredIndex!)"
                :cy="yFor(hoveredPoint.invested)"
                r="3.5"
                :fill="INVESTED_COLOR"
            />

            <rect
                v-for="(point, index) in points"
                :key="point.date"
                :x="index === 0 ? PAD.left : (xFor(index - 1) + xFor(index)) / 2"
                :y="PAD.top"
                :width="
                    (index === 0 || index === points.length - 1
                        ? (xFor(1) - xFor(0)) / 2
                        : (xFor(index + 1) - xFor(index - 1)) / 2) || PLOT_WIDTH
                "
                :height="PLOT_HEIGHT"
                fill="transparent"
                class="cursor-pointer"
                @mouseenter="hoveredIndex = index"
                @mouseleave="hoveredIndex = null"
            >
                <title>
                    {{ formatDate(point.date) }} — {{ investedLabel }}:
                    {{ formatCurrency(point.invested) }}<template v-if="point.market_value !== null">
                        · {{ marketValueLabel }}: {{ formatCurrency(point.market_value) }}</template
                    >
                </title>
            </rect>

            <text
                v-for="tick in monthTicks"
                :key="tick.index"
                :x="xFor(tick.index)"
                :y="HEIGHT - 8"
                :text-anchor="
                    tick.index === 0
                        ? 'start'
                        : tick.index === points.length - 1
                          ? 'end'
                          : 'middle'
                "
                class="fill-muted-foreground text-[9px]"
            >
                {{ tick.label }}
            </text>
        </svg>

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
                        <span class="font-medium text-foreground">{{
                            formatCurrency(position.invested)
                        }}</span>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
