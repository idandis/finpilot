<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    Building,
    Building2,
    Clock,
    Scale,
    Target,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import OverviewController from '@/actions/App/Http/Controllers/BalanceSheet/OverviewController';
import BalanceSheetSectionPanel from '@/components/balance-sheet/BalanceSheetSectionPanel.vue';
import type { BalanceSheetSection } from '@/components/balance-sheet/BalanceSheetSectionPanel.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatCurrency, formatHours } from '@/lib/balance-sheet-format';
import * as balanceSheet from '@/routes/balance-sheet';
import * as goalRoutes from '@/routes/balance-sheet/entries';
import type {
    BalanceSheetEntry,
    BalanceSheetEntryType,
    BalanceSheetLiabilityOption,
    BalanceSheetMonthClosure,
    BalanceSheetOverview,
    BalanceSheetProfile,
} from '@/types';

const props = defineProps<{
    overview: BalanceSheetOverview;
    profile: BalanceSheetProfile;
    goals: BalanceSheetEntry[];
    recentClosures: BalanceSheetMonthClosure[];
    entries: BalanceSheetEntry[];
    categorySuggestions: Record<BalanceSheetEntryType, string[]>;
    liabilityOptions: BalanceSheetLiabilityOption[];
    assetOptions: BalanceSheetLiabilityOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Bilancio patrimoniale', href: balanceSheet.index() },
        ],
    },
});

const tiles = computed(() => [
    { label: 'Liquidità', value: formatCurrency(props.overview.cash_balance) },
    {
        label: 'Flusso di cassa',
        value: formatCurrency(props.overview.cash_flow),
    },
    {
        label: 'Entrate mensili',
        value: formatCurrency(props.overview.monthly_income),
    },
    {
        label: 'Uscite mensili',
        value: formatCurrency(props.overview.monthly_expense),
    },
    { label: 'Attività', value: formatCurrency(props.overview.total_assets) },
    {
        label: 'Passività',
        value: formatCurrency(props.overview.total_liabilities),
    },
    { label: 'Tempo libero', value: formatHours(props.overview.free_time) },
    {
        label: 'Valore orario',
        value:
            props.overview.hourly_value === null
                ? 'Non calcolabile'
                : formatCurrency(props.overview.hourly_value),
    },
]);

function closeMonth() {
    if (
        confirm(
            'Chiudere il mese? Il flusso di cassa verrà aggiunto alla liquidità e le rate collegate a un mutuo ridurranno il debito residuo.',
        )
    ) {
        router.post(
            balanceSheet.closeMonth.url(),
            {},
            { preserveScroll: true },
        );
    }
}

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

const sheetSection = ref<BalanceSheetSection | null>(null);
const isSheetOpen = computed(() => sheetSection.value !== null);

function openSection(section: BalanceSheetSection) {
    sheetSection.value = section;
}

function closeSheet() {
    sheetSection.value = null;
}

function sheetCloseMonth() {
    closeMonth();
    closeSheet();
}
</script>

<template>
    <Head title="Bilancio patrimoniale" />

    <div class="mx-auto flex w-full max-w-[76rem] gap-4 p-4">
        <div class="min-w-0 flex-1 space-y-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <Heading
                    title="Bilancio patrimoniale"
                    :description="`Mesi chiusi: ${profile.closed_months}`"
                />
                <Button @click="closeMonth">Chiudi mese</Button>
            </div>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div
                    v-for="tile in tiles"
                    :key="tile.label"
                    class="rounded-lg border p-4"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ tile.label }}
                    </p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ tile.value }}
                    </p>
                </div>
                <div class="rounded-lg border bg-muted/40 p-4 md:col-span-2">
                    <p class="text-sm text-muted-foreground">
                        Patrimonio netto
                    </p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">
                        {{ formatCurrency(overview.net_worth) }}
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <Heading
                    variant="small"
                    title="Obiettivi in corso"
                    :description="
                        goals.length
                            ? undefined
                            : 'Nessun obiettivo attivo al momento'
                    "
                />
                <ul v-if="goals.length" class="space-y-3">
                    <li
                        v-for="goal in goals"
                        :key="goal.id"
                        class="flex items-center gap-4"
                    >
                        <div class="w-48 shrink-0">
                            <p class="text-sm font-medium">{{ goal.name }}</p>
                            <p
                                v-if="goal.category"
                                class="text-xs text-muted-foreground"
                            >
                                {{ goal.category }}
                            </p>
                        </div>
                        <div class="h-2 flex-1 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full bg-primary"
                                :style="{
                                    width: `${goal.progress_percent ?? 0}%`,
                                }"
                            />
                        </div>
                        <span class="w-12 text-right text-sm tabular-nums">
                            {{ goal.progress_percent ?? 0 }}%
                        </span>
                    </li>
                </ul>
                <Button as-child variant="outline" size="sm">
                    <a :href="goalRoutes.index('goal').url"
                        >Gestisci obiettivi</a
                    >
                </Button>
            </div>

            <div v-if="recentClosures.length" class="space-y-4">
                <Heading variant="small" title="Ultime chiusure mese" />
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="closure in recentClosures"
                        :key="closure.id"
                        class="flex items-center justify-between rounded-lg border px-3 py-2"
                    >
                        <span class="text-muted-foreground">{{
                            new Date(closure.created_at).toLocaleDateString(
                                'it-IT',
                                { year: 'numeric', month: 'long' },
                            )
                        }}</span>
                        <span class="tabular-nums"
                            >Flusso:
                            {{ formatCurrency(closure.cash_flow) }}</span
                        >
                        <span class="tabular-nums"
                            >Liquidità:
                            {{
                                formatCurrency(closure.cash_balance_after)
                            }}</span
                        >
                    </li>
                </ul>
            </div>

            <div class="max-w-md space-y-4">
                <Heading
                    variant="small"
                    title="Impostazioni"
                    description="Liquidità iniziale e ore-base mensili di riferimento"
                />
                <Form
                    v-bind="OverviewController.updateProfile.form()"
                    class="flex flex-wrap items-end gap-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="cash_balance">Liquidità</Label>
                        <Input
                            id="cash_balance"
                            name="cash_balance"
                            type="number"
                            step="0.01"
                            :default-value="profile.cash_balance"
                            class="w-40"
                        />
                        <InputError :message="errors.cash_balance" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="base_monthly_hours">Ore-base mensili</Label>
                        <Input
                            id="base_monthly_hours"
                            name="base_monthly_hours"
                            type="number"
                            step="0.1"
                            :default-value="profile.base_monthly_hours"
                            class="w-40"
                        />
                        <InputError :message="errors.base_monthly_hours" />
                    </div>
                    <Button type="submit" :disabled="processing">Salva</Button>
                </Form>
            </div>
        </div>

        <div class="flex shrink-0 flex-col gap-2">
            <Button
                v-for="section in SECTIONS"
                :key="section.key"
                variant="outline"
                class="h-14 w-14 rounded-2xl"
                :title="section.label"
                @click="openSection(section.key)"
            >
                <component :is="section.icon" class="size-6" />
            </Button>
        </div>
    </div>

    <Sheet
        :open="isSheetOpen"
        @update:open="
            (open) => {
                if (!open) closeSheet();
            }
        "
    >
        <SheetContent class="w-full gap-0 overflow-y-auto sm:max-w-2xl">
            <SheetHeader class="sr-only">
                <SheetTitle>Libro contabile</SheetTitle>
            </SheetHeader>
            <div class="p-4">
                <BalanceSheetSectionPanel
                    v-if="sheetSection"
                    :section="sheetSection"
                    :overview="overview"
                    :entries="entries"
                    :category-suggestions="categorySuggestions"
                    :liability-options="liabilityOptions"
                    :asset-options="assetOptions"
                    @close-month="sheetCloseMonth"
                />
            </div>
        </SheetContent>
    </Sheet>
</template>
