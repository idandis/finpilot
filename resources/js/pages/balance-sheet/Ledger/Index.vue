<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Building,
    Building2,
    Clock,
    Scale,
    Target,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import type { Component } from 'vue';
import BalanceSheetSectionPanel from '@/components/balance-sheet/BalanceSheetSectionPanel.vue';
import type { BalanceSheetSection } from '@/components/balance-sheet/BalanceSheetSectionPanel.vue';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/balance-sheet-format';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import balanceSheet from '@/routes/balance-sheet';
import closeMonthRoutes from '@/routes/balance-sheet/close-month';
import monthlyBudgets from '@/routes/monthly-budgets';
import type {
    BalanceSheetEntry,
    BalanceSheetEntryType,
    BalanceSheetLiabilityOption,
    BalanceSheetOverview,
    BalanceSheetProfile,
} from '@/types';

type BudgetMonth = {
    year: number;
    month: number;
    income: number;
    expense: number;
};

type MonthClosure = {
    id: number;
    year: number | null;
    month: number | null;
    income_total: number;
    expense_total: number;
    cash_flow: number;
    cash_balance_after: number;
    closed_at: string | null;
};

const props = defineProps<{
    profile: BalanceSheetProfile;
    overview: BalanceSheetOverview;
    closures: MonthClosure[];
    budgetMonths: BudgetMonth[];
    entries: BalanceSheetEntry[];
    categorySuggestions: Record<BalanceSheetEntryType, string[]>;
    liabilityOptions: BalanceSheetLiabilityOption[];
    assetOptions: BalanceSheetLiabilityOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Bilancio patrimoniale', href: balanceSheet.index() },
            { title: 'Libro contabile', href: balanceSheet.ledger() },
        ],
    },
});

const SECTIONS: { key: BalanceSheetSection; label: string; icon: Component }[] =
    [
        { key: 'overview', label: 'Panoramica', icon: Scale },
        { key: 'income', label: 'Entrate', icon: TrendingUp },
        { key: 'expense', label: 'Uscite', icon: TrendingDown },
        { key: 'asset', label: 'Attività', icon: Building2 },
        { key: 'liability', label: 'Passività', icon: Building },
        { key: 'time', label: 'Tempo', icon: Clock },
        { key: 'goal', label: 'Obiettivi', icon: Target },
    ];

const activeSection = ref<BalanceSheetSection>('overview');

const MONTHS = [
    'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
    'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
];

const today = new Date();
const selectedYear = ref(today.getFullYear());
const selectedMonth = ref(today.getMonth() + 1);

const isCurrentMonth = computed(() =>
    selectedYear.value === today.getFullYear() && selectedMonth.value === today.getMonth() + 1);

const yearOptions = computed(() => {
    const years = new Set<number>([today.getFullYear(), selectedYear.value]);

    props.closures.forEach((closure) => {
        if (closure.year) years.add(closure.year);
    });

    return [...years].sort((a, b) => a - b);
});

const closuresOfYear = computed(() =>
    props.closures.filter((closure) => closure.year === selectedYear.value));

const hasClosure = (month: number) =>
    closuresOfYear.value.some((closure) => closure.month === month);

/** Più di una se in quel mese la chiusura è stata eseguita più volte. */
const selectedClosures = computed(() =>
    closuresOfYear.value.filter((closure) => closure.month === selectedMonth.value));

const monthRow = ref<HTMLElement | null>(null);

const scrollActiveMonthIntoView = () => {
    monthRow.value
        ?.querySelector('[data-active="true"]')
        ?.scrollIntoView({ inline: 'center', block: 'nearest' });
};

onMounted(scrollActiveMonthIntoView);
watch([selectedYear, selectedMonth], () => nextTick(scrollActiveMonthIntoView));

const backToCurrentMonth = () => {
    selectedYear.value = today.getFullYear();
    selectedMonth.value = today.getMonth() + 1;
};

const selectedBudgetMonth = computed(() => props.budgetMonths.find(
    (budget) => budget.year === selectedYear.value && budget.month === selectedMonth.value,
) ?? null);

// Entrate e uscite del mese vengono dai movimenti del budget: le voci del
// libro contabile restano la struttura ricorrente, non il consuntivo.
const effectiveOverview = computed(() => {
    const budget = selectedBudgetMonth.value;

    if (!budget) return props.overview;

    return {
        ...props.overview,
        monthly_income: budget.income,
        monthly_expense: budget.expense,
        cash_flow: budget.income - budget.expense,
        hourly_value: props.overview.hours_consumed > 0
            ? Math.round((budget.income / props.overview.hours_consumed) * 100) / 100
            : null,
    };
});

const budgetMonthUrl = computed(() => monthlyBudgets.index.url({
    query: { year: selectedYear.value, month: selectedMonth.value },
}));

/** Solo l'ultima chiusura è annullabile: le precedenti hanno effetti sopra. */
const latestClosure = computed(() => props.closures[0] ?? null);

const currentMonthClosures = computed(() => props.closures.filter(
    (closure) => closure.year === today.getFullYear() && closure.month === today.getMonth() + 1,
));

const canUndo = (closure: MonthClosure) => closure.id === latestClosure.value?.id;

const undoClosure = (closure: MonthClosure) => {
    const confirmed = confirm(
        `Annullare la chiusura di ${MONTHS[(closure.month ?? 1) - 1].toLowerCase()} ${closure.year}? `
        + `La liquidità torna indietro di ${formatCurrency(closure.cash_flow)}, i debiti pagati `
        + 'risalgono e le voci una tantum tornano attive.',
    );

    if (!confirmed) return;

    router.delete(closeMonthRoutes.destroy.url(closure.id), { preserveScroll: true });
};

const closedAtLabel = (closure: MonthClosure) => closure.closed_at
    ? new Intl.DateTimeFormat('it-IT', { dateStyle: 'medium', timeStyle: 'short' })
        .format(new Date(closure.closed_at))
    : null;

function closeMonth() {
    if (
        confirm(
            'Chiudere il mese? Il flusso di cassa verrà aggiunto alla liquidità e le rate collegate a un mutuo ridurranno il debito residuo.',
        )
    ) {
        router.post(
            balanceSheet.closeMonth.url({ query: { from_ledger: 1 } }),
            {},
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head title="Libro contabile" />

    <div class="mx-auto w-full max-w-[72rem] p-4">
        <!-- Mese e anno -->
        <div ref="monthRow" class="mb-4 flex items-center gap-2 overflow-x-auto pb-1">
            <Select v-model="selectedYear">
                <SelectTrigger class="h-9 w-24 shrink-0 rounded-full border-0 bg-muted/50 shadow-none">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="year in yearOptions" :key="year" :value="year">
                        {{ year }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <button
                v-for="(label, index) in MONTHS"
                :key="label"
                :data-active="index + 1 === selectedMonth"
                class="shrink-0 whitespace-nowrap rounded-full px-4 py-2 text-sm transition-colors"
                :class="[
                    index + 1 === selectedMonth
                        ? 'bg-primary font-medium text-primary-foreground'
                        : hasClosure(index + 1)
                            ? 'bg-muted/50 text-foreground hover:bg-muted'
                            : 'bg-muted/20 text-muted-foreground/60 hover:bg-muted/40',
                ]"
                :title="hasClosure(index + 1) ? 'Mese chiuso' : 'Nessuna chiusura in questo mese'"
                @click="selectedMonth = index + 1"
            >
                <span
                    v-if="hasClosure(index + 1)"
                    class="mr-2 inline-block size-1.5 rounded-full align-middle"
                    :class="index + 1 === selectedMonth ? 'bg-primary-foreground' : 'bg-green-500'"
                />
                {{ label }}
            </button>
        </div>

        <!-- Da dove arrivano entrate e uscite del mese -->
        <div class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-card px-5 py-3">
            <div>
                <p class="text-sm font-medium">
                    Budget di {{ MONTHS[selectedMonth - 1].toLowerCase() }} {{ selectedYear }}
                </p>
                <p class="text-xs text-muted-foreground">
                    <template v-if="selectedBudgetMonth">
                        Entrate e uscite qui sotto sono i movimenti registrati in questo mese.
                    </template>
                    <template v-else>
                        Nessun movimento registrato: qui sotto restano le voci ricorrenti.
                    </template>
                </p>
            </div>

            <Button as-child variant="outline" size="sm">
                <Link :href="budgetMonthUrl">Apri il budget</Link>
            </Button>
        </div>

        <div
            v-if="isCurrentMonth && currentMonthClosures.length"
            class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-green-600/40 bg-green-600/10 px-5 py-3"
        >
            <div>
                <p class="text-sm font-medium">
                    {{ MONTHS[selectedMonth - 1] }} {{ selectedYear }} è già stato chiuso
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ closedAtLabel(currentMonthClosures[0]) }} ·
                    flusso {{ formatCurrency(currentMonthClosures[0].cash_flow) }}
                </p>
            </div>

            <Button
                v-if="canUndo(currentMonthClosures[0])"
                variant="outline"
                size="sm"
                @click="undoClosure(currentMonthClosures[0])"
            >
                Annulla chiusura
            </Button>
        </div>

        <div class="flex gap-4">
            <div class="min-w-0 flex-1">
                <BalanceSheetSectionPanel
                    v-if="isCurrentMonth"
                    :section="activeSection"
                    :overview="effectiveOverview"
                    :entries="entries"
                    :category-suggestions="categorySuggestions"
                    :liability-options="liabilityOptions"
                    :asset-options="assetOptions"
                    @close-month="closeMonth"
                />

                <!-- Mesi passati: esistono solo i totali salvati alla chiusura,
                     le singole voci non hanno storico. -->
                <div v-else class="rounded-xl border bg-card p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold">
                            {{ MONTHS[selectedMonth - 1] }} {{ selectedYear }}
                        </h2>
                        <Button variant="outline" size="sm" @click="backToCurrentMonth">
                            Torna al mese corrente
                        </Button>
                    </div>

                    <div v-if="selectedClosures.length" class="mt-4 space-y-3">
                        <div
                            v-for="closure in selectedClosures"
                            :key="closure.id"
                            class="rounded-lg border p-4"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p v-if="closedAtLabel(closure)" class="text-xs text-muted-foreground">
                                    Chiuso il {{ closedAtLabel(closure) }}
                                </p>
                                <Button
                                    v-if="canUndo(closure)"
                                    variant="outline"
                                    size="sm"
                                    @click="undoClosure(closure)"
                                >
                                    Annulla chiusura
                                </Button>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <p class="text-sm text-muted-foreground">Entrate</p>
                                    <p class="text-lg font-semibold tabular-nums">
                                        {{ formatCurrency(closure.income_total) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Uscite</p>
                                    <p class="text-lg font-semibold tabular-nums">
                                        {{ formatCurrency(closure.expense_total) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Flusso di cassa</p>
                                    <p
                                        class="text-lg font-semibold tabular-nums"
                                        :class="closure.cash_flow >= 0 ? 'text-green-600' : 'text-red-600'"
                                    >
                                        {{ formatCurrency(closure.cash_flow) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Liquidità dopo</p>
                                    <p class="text-lg font-semibold tabular-nums">
                                        {{ formatCurrency(closure.cash_balance_after) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            Dei mesi passati restano i totali registrati alla chiusura: le singole
                            voci vivono solo nel mese corrente.
                        </p>
                    </div>

                    <div v-else class="mt-4 rounded-lg border border-dashed bg-muted/30 p-8 text-center">
                        <p class="text-sm text-muted-foreground">
                            Nessuna chiusura per {{ MONTHS[selectedMonth - 1].toLowerCase() }}
                            {{ selectedYear }}.
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Lo storico si popola ogni volta che chiudi un mese.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2" :class="{ 'pointer-events-none opacity-40': !isCurrentMonth }">
                <Button
                    v-for="section in SECTIONS"
                    :key="section.key"
                    :variant="activeSection === section.key ? 'default' : 'outline'"
                    class="h-14 w-14 rounded-2xl"
                    :title="section.label"
                    @click="activeSection = section.key"
                >
                    <component :is="section.icon" class="size-6" />
                </Button>
            </div>
        </div>
    </div>
</template>
