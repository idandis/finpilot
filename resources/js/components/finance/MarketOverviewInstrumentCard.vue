<script setup lang="ts">
import MacroSparkline from '@/components/finance/MacroSparkline.vue';
import { Badge } from '@/components/ui/badge';
import type { MarketOverviewInstrument, MarketOverviewRotation } from '@/types';

defineProps<{
    instrument: MarketOverviewInstrument;
}>();

const REGION_LABELS: Record<string, string> = {
    usa: 'USA',
    eurozone: 'Eurozona',
    italia: 'Italia',
    globale: 'Globale',
};

const ROTATION_LABELS: Record<MarketOverviewRotation, string> = {
    strong_accelerating: 'Forte, in accelerazione',
    strong_slowing: 'Forte, in rallentamento',
    weak_recovering: 'Debole, in recupero',
    weak_worsening: 'Debole, in peggioramento',
};

const ROTATION_CLASS: Record<MarketOverviewRotation, string> = {
    strong_accelerating: 'text-green-600',
    strong_slowing: 'text-amber-600',
    weak_recovering: 'text-blue-600',
    weak_worsening: 'text-red-600',
};

const PERFORMANCE_WINDOWS: { key: keyof MarketOverviewInstrument; label: string }[] = [
    { key: 'week_change_percent', label: '1S' },
    { key: 'month_change_percent', label: '1M' },
    { key: 'quarter_change_percent', label: '3M' },
    { key: 'ytd_change_percent', label: 'YTD' },
];

function formatMarketValue(value: number, unit: string) {
    if (unit === 'USD') {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: 2,
        }).format(value);
    }

    if (unit === 'pt') {
        return `${new Intl.NumberFormat('it-IT', { maximumFractionDigits: 2 }).format(value)} pt`;
    }

    // Bare exchange rate (EUR/USD): no unit suffix, extra precision.
    return new Intl.NumberFormat('it-IT', { minimumFractionDigits: 4, maximumFractionDigits: 4 }).format(value);
}

function formatPercent(value: number | null) {
    if (value === null) {
        return '—';
    }

    const sign = value > 0 ? '+' : '';

    return `${sign}${new Intl.NumberFormat('it-IT', { minimumFractionDigits: 1, maximumFractionDigits: 2 }).format(value)}%`;
}

function formatDate(value: string) {
    return new Date(`${value}T00:00:00`).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function changeClass(value: number | null) {
    if (value === null || value === 0) {
        return 'text-muted-foreground';
    }

    return value > 0 ? 'text-green-600' : 'text-red-600';
}
</script>

<template>
    <div class="space-y-3 rounded-lg bg-muted dark:bg-muted/40 p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="line-clamp-2 text-sm font-medium">{{ instrument.label }}</p>
                <Badge variant="secondary" class="mt-1">{{ REGION_LABELS[instrument.region] ?? instrument.region }}</Badge>
            </div>
            <MacroSparkline :points="instrument.history" :width="120" class="mt-0.5" />
        </div>

        <div class="flex items-end justify-between gap-2">
            <div>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ instrument.current ? formatMarketValue(instrument.current.value, instrument.unit) : '—' }}
                </p>
                <p v-if="instrument.current" class="text-xs text-muted-foreground">
                    {{ formatDate(instrument.current.date) }}
                </p>
            </div>
            <p
                class="shrink-0 text-sm font-medium tabular-nums"
                :class="changeClass(instrument.day_change_percent)"
            >
                {{ formatPercent(instrument.day_change_percent) }}
            </p>
        </div>

        <dl class="grid grid-cols-4 gap-2 text-center text-xs">
            <div v-for="window in PERFORMANCE_WINDOWS" :key="window.key">
                <dt class="text-muted-foreground">{{ window.label }}</dt>
                <dd
                    class="font-medium tabular-nums"
                    :class="changeClass(instrument[window.key] as number | null)"
                >
                    {{ formatPercent(instrument[window.key] as number | null) }}
                </dd>
            </div>
        </dl>

        <div class="flex items-center justify-between text-xs text-muted-foreground">
            <span>Dist. da max 52S: {{ formatPercent(instrument.distance_from_high_percent) }}</span>
            <span>Volatilità: {{ instrument.volatility_percent !== null ? `${instrument.volatility_percent}%` : '—' }}</span>
        </div>

        <p
            v-if="instrument.rotation"
            class="text-xs font-medium"
            :class="ROTATION_CLASS[instrument.rotation]"
        >
            {{ ROTATION_LABELS[instrument.rotation] }}
        </p>
    </div>
</template>
