<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import * as lifeRoutes from '@/routes/life';
import type { LifeWeekMetrics, LifeWeekSummary } from '@/types';

const props = defineProps<{
    year: number;
    currentYear: number;
    availableYears: number[];
    weeks: LifeWeekSummary[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vita', href: lifeRoutes.index() }],
    },
});

function goToYear(year: number) {
    router.get(
        lifeRoutes.index.url({ query: { anno: year } }),
        {},
        { preserveScroll: true },
    );
}

const WEEK_RANGE_FORMATTER = new Intl.DateTimeFormat('it-IT', {
    day: 'numeric',
    month: 'short',
});

const MONTH_FORMATTER = new Intl.DateTimeFormat('en-US', {
    month: 'short',
});

const isCurrentYear = computed(() => props.year === props.currentYear);

type MetricKey = 'none' | 'season' | keyof LifeWeekMetrics;

const METRIC_OPTIONS: { key: MetricKey; label: string }[] = [
    { key: 'none', label: 'Nessuna' },
    { key: 'season', label: 'Stagione' },
    { key: 'productivity', label: 'Produttività' },
    { key: 'workouts', label: 'Allenamenti' },
    { key: 'mood', label: 'Mood medio' },
    { key: 'budget', label: 'Rispetto budget' },
];

// Client-side only: every week already carries all four metric scores, so
// switching which one colors the grid never needs a round trip.
const selectedMetric = ref<MetricKey>('none');

function getMonthAbbr(dateStr: string): string {
    return MONTH_FORMATTER.format(new Date(`${dateStr}T00:00:00`));
}

function getSeasonColor(dateStr: string): string {
    const date = new Date(`${dateStr}T00:00:00`);
    const month = date.getMonth();

    if (month === 11 || month === 0 || month === 1) {
        return 'bg-cyan-500 text-white';
    }

    if (month >= 2 && month <= 4) {
        return 'bg-green-500 text-white';
    }

    if (month >= 5 && month <= 7) {
        return 'bg-yellow-500 text-yellow-950';
    }

    return 'bg-orange-500 text-white';
}

function metricValue(week: LifeWeekSummary): number | null {
    return selectedMetric.value === 'none' || selectedMetric.value === 'season'
        ? null
        : week.metrics[selectedMetric.value];
}

function squareColorClass(week: LifeWeekSummary): string {
    if (selectedMetric.value === 'none') {
        return week.isCurrent
            ? 'bg-primary text-primary-foreground'
            : 'bg-muted/60 text-muted-foreground';
    }

    if (selectedMetric.value === 'season') {
        return getSeasonColor(week.start);
    }

    const value = metricValue(week);

    if (value === null) {
        return 'bg-muted/40 text-muted-foreground';
    }

    if (value < 20) {
        return 'bg-blue-100 text-blue-950';
    }

    if (value < 40) {
        return 'bg-blue-200 text-blue-950';
    }

    if (value < 60) {
        return 'bg-blue-300 text-blue-950';
    }

    if (value < 80) {
        return 'bg-blue-500 text-white';
    }

    return 'bg-blue-700 text-white';
}

function weekTitle(week: LifeWeekSummary) {
    const start = WEEK_RANGE_FORMATTER.format(new Date(`${week.start}T00:00:00`));
    const end = WEEK_RANGE_FORMATTER.format(new Date(`${week.end}T00:00:00`));
    const range = `Settimana ${week.week} · ${start} - ${end}`;

    if (selectedMetric.value === 'none') {
        return range;
    }

    const value = metricValue(week);
    const label = METRIC_OPTIONS.find((option) => option.key === selectedMetric.value)?.label;

    return `${range} · ${label}: ${value === null ? 'nessun dato' : `${value}%`}`;
}
</script>

<template>
    <Head title="Vita" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-8 p-4">
        <div class="space-y-4">
            <div class="space-y-1">
                <h2 class="text-xl font-semibold tracking-tight">Weekly Review</h2>
                <p class="text-sm text-muted-foreground">
                    Ripercorri gli anni attraverso i dati raccolti da tutti i
                    moduli di ManageMe.
                </p>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    Ogni quadrato è una settimana: cliccalo per vedere nel
                    dettaglio finanza, salute e organizzazione di quei giorni,
                    insieme ai ricordi.
                </p>
            </div>

            <div class="-mx-4 overflow-x-auto pb-2 sm:mx-0">
                <div class="flex items-center gap-2 px-4 sm:px-0">
                    <Button
                        v-for="y in availableYears"
                        :key="y"
                        :variant="y === year ? 'default' : 'outline'"
                        size="sm"
                        class="shrink-0"
                        @click="goToYear(y)"
                    >
                        {{ y }}
                    </Button>
                    <Badge v-if="!isCurrentYear" variant="secondary" class="shrink-0">
                        <Button variant="ghost" size="sm" class="h-auto p-0 text-xs font-normal" @click="goToYear(currentYear)">
                            Torna a {{ currentYear }}
                        </Button>
                    </Badge>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Label for="life-metric" class="text-xs text-muted-foreground">Colora per</Label>
                <select
                    id="life-metric"
                    v-model="selectedMetric"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option v-for="option in METRIC_OPTIONS" :key="option.key" :value="option.key">
                        {{ option.label }}
                    </option>
                </select>
            </div>
        </div>

        <div
            class="grid grid-cols-[repeat(auto-fill,minmax(3.5rem,1fr))] gap-2"
        >
            <Link
                v-for="week in weeks"
                :key="week.week"
                :href="lifeRoutes.week([year, week.week])"
                :title="weekTitle(week)"
                class="flex aspect-square flex-col items-center justify-center gap-0.5 rounded-lg transition-colors hover:opacity-80"
                :class="[
                    squareColorClass(week),
                    selectedMetric !== 'none' && week.isCurrent
                        ? 'ring-2 ring-primary ring-offset-2 ring-offset-background'
                        : '',
                ]"
            >
                <span class="text-xs font-semibold">{{ week.week }}</span>
                <span class="text-[10px] opacity-75">{{ getMonthAbbr(week.start) }}</span>
            </Link>
        </div>
    </div>
</template>
