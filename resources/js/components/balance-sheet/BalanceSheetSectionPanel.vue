<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AssetController from '@/actions/App/Http/Controllers/BalanceSheet/AssetController';
import EntryController from '@/actions/App/Http/Controllers/BalanceSheet/EntryController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { balanceSheetEntryTypeConfig } from '@/lib/balance-sheet-entry-types';
import { formatCurrency, formatHours } from '@/lib/balance-sheet-format';
import { groupBalanceSheetEntriesByCategory } from '@/lib/balance-sheet-grouping';
import * as assetRoutes from '@/routes/balance-sheet/assets';
import * as entryRoutes from '@/routes/balance-sheet/entries';
import type {
    BalanceSheetEntry,
    BalanceSheetEntryType,
    BalanceSheetGenericEntryType,
    BalanceSheetLiabilityOption,
    BalanceSheetOverview,
} from '@/types';

export type BalanceSheetSection = BalanceSheetEntryType | 'overview';

const props = defineProps<{
    section: BalanceSheetSection;
    overview: BalanceSheetOverview;
    entries: BalanceSheetEntry[];
    categorySuggestions: Record<BalanceSheetEntryType, string[]>;
    liabilityOptions: BalanceSheetLiabilityOption[];
    assetOptions: BalanceSheetLiabilityOption[];
}>();

const emit = defineEmits<{ closeMonth: [] }>();

const entriesByType = computed(() => {
    const map = {
        income: [],
        expense: [],
        asset: [],
        liability: [],
        time: [],
        goal: [],
    } as Record<BalanceSheetEntryType, BalanceSheetEntry[]>;

    for (const entry of props.entries) {
        map[entry.type].push(entry);
    }

    return map;
});

const sectionTitle = computed(() => {
    if (props.section === 'overview') {
        return 'Panoramica';
    }

    if (props.section === 'asset') {
        return 'Attività';
    }

    return balanceSheetEntryTypeConfig[props.section].title;
});

const sectionAddLabel = computed(() => {
    if (props.section === 'asset') {
        return 'Aggiungi attività';
    }

    if (props.section === 'overview') {
        return '';
    }

    return balanceSheetEntryTypeConfig[props.section].addLabel;
});

const currentEntries = computed(() =>
    props.section === 'overview' ? [] : entriesByType.value[props.section],
);

const currentTotal = computed(() => {
    if (
        props.section === 'overview' ||
        props.section === 'time' ||
        props.section === 'goal'
    ) {
        return null;
    }

    return currentEntries.value
        .filter((entry) => entry.active)
        .reduce((sum, entry) => sum + Number(entry.amount ?? 0), 0);
});

const categoryGroups = computed(() =>
    props.section === 'time' || props.section === 'overview'
        ? []
        : groupBalanceSheetEntriesByCategory(currentEntries.value),
);

const timeGroups = computed(() => ({
    frees: currentEntries.value.filter((entry) => entry.time_kind === 'frees'),
    consumes: currentEntries.value.filter(
        (entry) => entry.time_kind === 'consumes',
    ),
}));

function entryValue(entry: BalanceSheetEntry): string {
    if (entry.type === 'time') {
        return formatHours(entry.hours_per_month ?? 0);
    }

    if (entry.type === 'goal') {
        return `${entry.progress_percent ?? 0}%`;
    }

    return formatCurrency(entry.amount ?? 0);
}

function linkedOfType(
    assetId: number,
    type: BalanceSheetEntryType,
): BalanceSheetEntry | null {
    return (
        props.entries.find(
            (entry) => entry.linked_asset_id === assetId && entry.type === type,
        ) ?? null
    );
}

const overviewTiles = computed(() => [
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

const isAddOpen = ref(false);
const editingEntry = ref<BalanceSheetEntry | null>(null);
const editingAsset = ref<BalanceSheetEntry | null>(null);
const isEditEntryOpen = computed(() => editingEntry.value !== null);
const isEditAssetOpen = computed(() => editingAsset.value !== null);

const editingEntryConfig = computed(() =>
    editingEntry.value
        ? balanceSheetEntryTypeConfig[
              editingEntry.value.type as BalanceSheetGenericEntryType
          ]
        : null,
);

function openEdit(entry: BalanceSheetEntry) {
    if (entry.type === 'asset') {
        editingAsset.value = entry;
    } else {
        editingEntry.value = entry;
    }
}

function closeEditEntry() {
    editingEntry.value = null;
}

function closeEditAsset() {
    editingAsset.value = null;
}

function destroyEntry(entry: BalanceSheetEntry) {
    if (confirm(`Vuoi eliminare "${entry.name}"?`)) {
        router.delete(
            entryRoutes.destroy([entry.type, entry.id], {
                query: { from_ledger: 1 },
            }).url,
            { preserveScroll: true },
        );
    }
}

function destroyAsset(asset: BalanceSheetEntry) {
    if (
        confirm(
            `Vuoi eliminare "${asset.name}" e tutte le voci collegate: entrata, mutuo, rata e tempo?`,
        )
    ) {
        router.delete(
            assetRoutes.destroy(asset.id, { query: { from_ledger: 1 } }).url,
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <div class="rounded-[1.75rem] bg-primary p-0.5 shadow-lg sm:p-1">
        <div class="rounded-[1.35rem] bg-card p-6 sm:p-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <Heading
                    :title="sectionTitle"
                    :description="
                        currentTotal !== null
                            ? `Totale: ${formatCurrency(currentTotal)}`
                            : undefined
                    "
                />
                <Button
                    v-if="section === 'overview'"
                    @click="emit('closeMonth')"
                >
                    Chiudi mese
                </Button>
                <Button v-else @click="isAddOpen = true">
                    <Plus />
                    {{ sectionAddLabel }}
                </Button>
            </div>

            <!-- Panoramica -->
            <div
                v-if="section === 'overview'"
                class="grid grid-cols-2 gap-4 md:grid-cols-4"
            >
                <div
                    v-for="tile in overviewTiles"
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

            <!-- Attività -->
            <div v-else-if="section === 'asset'" class="space-y-3">
                <p
                    v-if="!currentEntries.length"
                    class="text-sm text-muted-foreground"
                >
                    Nessuna attività ancora.
                </p>
                <ul v-else class="divide-y rounded-lg border">
                    <li
                        v-for="asset in currentEntries"
                        :key="asset.id"
                        class="flex items-center justify-between gap-4 px-4 py-3"
                    >
                        <div>
                            <p class="text-sm font-medium">{{ asset.name }}</p>
                            <p
                                v-if="asset.category"
                                class="text-xs text-muted-foreground"
                            >
                                {{ asset.category }}
                            </p>
                            <p
                                class="mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground"
                            >
                                <span v-if="linkedOfType(asset.id, 'income')">
                                    Entrata:
                                    {{
                                        formatCurrency(
                                            linkedOfType(asset.id, 'income')!
                                                .amount ?? 0,
                                        )
                                    }}/mese
                                </span>
                                <span
                                    v-if="linkedOfType(asset.id, 'liability')"
                                >
                                    Mutuo residuo:
                                    {{
                                        formatCurrency(
                                            linkedOfType(asset.id, 'liability')!
                                                .amount ?? 0,
                                        )
                                    }}
                                </span>
                                <span v-if="linkedOfType(asset.id, 'expense')">
                                    Rata:
                                    {{
                                        formatCurrency(
                                            linkedOfType(asset.id, 'expense')!
                                                .amount ?? 0,
                                        )
                                    }}/mese
                                </span>
                                <span v-if="linkedOfType(asset.id, 'time')">
                                    Tempo:
                                    {{
                                        linkedOfType(asset.id, 'time')!
                                            .hours_per_month
                                    }}
                                    h/mese
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="tabular-nums">{{
                                formatCurrency(asset.amount ?? 0)
                            }}</span>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                @click="openEdit(asset)"
                            >
                                <Pencil />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                @click="destroyAsset(asset)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Tempo -->
            <div v-else-if="section === 'time'" class="space-y-6">
                <div
                    v-for="group in [
                        {
                            title: 'Attività del tempo',
                            items: timeGroups.frees,
                        },
                        {
                            title: 'Passività del tempo',
                            items: timeGroups.consumes,
                        },
                    ]"
                    :key="group.title"
                    class="space-y-3"
                >
                    <Heading variant="small" :title="group.title" />
                    <p
                        v-if="!group.items.length"
                        class="text-sm text-muted-foreground"
                    >
                        Nessuna voce.
                    </p>
                    <ul v-else class="divide-y rounded-lg border">
                        <li
                            v-for="entry in group.items"
                            :key="entry.id"
                            class="flex items-center justify-between gap-4 px-4 py-3"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    {{ entry.name }}
                                    <span
                                        v-if="!entry.active"
                                        class="ml-2 text-xs text-muted-foreground"
                                        >(inattiva)</span
                                    >
                                </p>
                                <p
                                    v-if="entry.category"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ entry.category }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="tabular-nums">{{
                                    entryValue(entry)
                                }}</span>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    @click="openEdit(entry)"
                                >
                                    <Pencil />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    @click="destroyEntry(entry)"
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Entrate / Uscite / Passività / Obiettivi -->
            <div v-else class="space-y-6">
                <p
                    v-if="!categoryGroups.length"
                    class="text-sm text-muted-foreground"
                >
                    Nessuna voce ancora.
                </p>
                <div
                    v-for="group in categoryGroups"
                    :key="group.category"
                    class="space-y-3"
                >
                    <Heading variant="small" :title="group.category" />
                    <ul class="divide-y rounded-lg border">
                        <li
                            v-for="entry in group.items"
                            :key="entry.id"
                            class="flex items-center justify-between gap-4 px-4 py-3"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    {{ entry.name }}
                                    <span
                                        v-if="!entry.active"
                                        class="ml-2 text-xs text-muted-foreground"
                                        >(inattiva)</span
                                    >
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="tabular-nums">{{
                                    entryValue(entry)
                                }}</span>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    @click="openEdit(entry)"
                                >
                                    <Pencil />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    @click="destroyEntry(entry)"
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Dialog: aggiungi voce -->
    <Dialog v-model:open="isAddOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ sectionAddLabel }}</DialogTitle>
            </DialogHeader>

            <Form
                v-if="isAddOpen && section === 'asset'"
                key="add-asset"
                v-bind="
                    AssetController.store.form({ query: { from_ledger: 1 } })
                "
                reset-on-success
                class="grid grid-cols-1 gap-4"
                v-slot="{ errors, processing }"
                @success="isAddOpen = false"
            >
                <div class="grid gap-2">
                    <Label for="add-asset-name">Descrizione</Label>
                    <Input
                        id="add-asset-name"
                        name="name"
                        required
                        autofocus
                        placeholder="Es. Appartamento via Garibaldi"
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="add-asset-category">Categoria</Label>
                    <Input
                        id="add-asset-category"
                        name="category"
                        list="categories-add-asset"
                    />
                    <datalist id="categories-add-asset">
                        <option
                            v-for="category in categorySuggestions.asset"
                            :key="category"
                            :value="category"
                        />
                    </datalist>
                    <InputError :message="errors.category" />
                </div>
                <div class="grid gap-2">
                    <Label for="add-asset-value">Valore attività</Label>
                    <Input
                        id="add-asset-value"
                        name="asset_value"
                        type="number"
                        step="0.01"
                        required
                    />
                    <InputError :message="errors.asset_value" />
                </div>
                <div class="grid gap-2">
                    <Label for="add-asset-income"
                        >Entrata/mese (opzionale)</Label
                    >
                    <Input
                        id="add-asset-income"
                        name="monthly_income"
                        type="number"
                        step="0.01"
                    />
                    <InputError :message="errors.monthly_income" />
                </div>
                <div class="grid gap-2">
                    <Label for="add-asset-cash"
                        >Liquidità usata (opzionale)</Label
                    >
                    <Input
                        id="add-asset-cash"
                        name="cash_used"
                        type="number"
                        step="0.01"
                    />
                    <InputError :message="errors.cash_used" />
                </div>
                <div class="grid gap-2">
                    <Label for="add-asset-mortgage"
                        >Totale mutuo (opzionale)</Label
                    >
                    <Input
                        id="add-asset-mortgage"
                        name="mortgage_total"
                        type="number"
                        step="0.01"
                    />
                    <InputError :message="errors.mortgage_total" />
                </div>
                <div class="grid gap-2">
                    <Label for="add-asset-installment"
                        >Rata/mese (opzionale)</Label
                    >
                    <Input
                        id="add-asset-installment"
                        name="monthly_installment"
                        type="number"
                        step="0.01"
                    />
                    <InputError :message="errors.monthly_installment" />
                </div>
                <div class="grid gap-2">
                    <Label for="add-asset-hours">Ore/mese (opzionale)</Label>
                    <Input
                        id="add-asset-hours"
                        name="hours_per_month"
                        type="number"
                        step="0.1"
                    />
                    <InputError :message="errors.hours_per_month" />
                </div>
                <Button type="submit" :disabled="processing"
                    >Crea attività</Button
                >
            </Form>

            <Form
                v-else-if="isAddOpen && section !== 'overview'"
                :key="`add-${section}`"
                v-bind="
                    EntryController.store.form(
                        section as BalanceSheetGenericEntryType,
                        { query: { from_ledger: 1 } },
                    )
                "
                reset-on-success
                class="grid grid-cols-1 gap-4"
                v-slot="{ errors, processing }"
                @success="isAddOpen = false"
            >
                <div class="grid gap-2">
                    <Label for="add-entry-name">Nome</Label>
                    <Input id="add-entry-name" name="name" required autofocus />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="add-entry-category">Categoria</Label>
                    <Input
                        id="add-entry-category"
                        name="category"
                        list="categories-add-entry"
                    />
                    <datalist id="categories-add-entry">
                        <option
                            v-for="category in categorySuggestions[
                                section as BalanceSheetGenericEntryType
                            ]"
                            :key="category"
                            :value="category"
                        />
                    </datalist>
                    <InputError :message="errors.category" />
                </div>
                <div
                    v-if="
                        balanceSheetEntryTypeConfig[
                            section as BalanceSheetGenericEntryType
                        ].showAmount
                    "
                    class="grid gap-2"
                >
                    <Label for="add-entry-amount">{{
                        balanceSheetEntryTypeConfig[
                            section as BalanceSheetGenericEntryType
                        ].amountLabel
                    }}</Label>
                    <Input
                        id="add-entry-amount"
                        name="amount"
                        type="number"
                        step="0.01"
                        required
                    />
                    <InputError :message="errors.amount" />
                </div>
                <div
                    v-if="
                        balanceSheetEntryTypeConfig[
                            section as BalanceSheetGenericEntryType
                        ].showFrequency
                    "
                    class="grid gap-2"
                >
                    <Label for="add-entry-frequency">Frequenza</Label>
                    <select
                        id="add-entry-frequency"
                        name="frequency"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="monthly">Mensile</option>
                        <option value="one_time">Una tantum</option>
                    </select>
                    <InputError :message="errors.frequency" />
                </div>
                <template
                    v-if="
                        balanceSheetEntryTypeConfig[
                            section as BalanceSheetGenericEntryType
                        ].showHours
                    "
                >
                    <div class="grid gap-2">
                        <Label for="add-entry-hours">Ore al mese</Label>
                        <Input
                            id="add-entry-hours"
                            name="hours_per_month"
                            type="number"
                            step="0.1"
                            required
                        />
                        <InputError :message="errors.hours_per_month" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-entry-time-kind">Tipo</Label>
                        <select
                            id="add-entry-time-kind"
                            name="time_kind"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="consumes">Consuma tempo</option>
                            <option value="frees">Libera tempo</option>
                        </select>
                        <InputError :message="errors.time_kind" />
                    </div>
                </template>
                <div
                    v-if="
                        balanceSheetEntryTypeConfig[
                            section as BalanceSheetGenericEntryType
                        ].showProgress
                    "
                    class="grid gap-2"
                >
                    <Label for="add-entry-progress">Progresso (%)</Label>
                    <Input
                        id="add-entry-progress"
                        name="progress_percent"
                        type="number"
                        min="0"
                        max="100"
                        required
                    />
                    <InputError :message="errors.progress_percent" />
                </div>
                <div
                    v-if="
                        balanceSheetEntryTypeConfig[
                            section as BalanceSheetGenericEntryType
                        ].showLinkedAsset && assetOptions.length
                    "
                    class="grid gap-2"
                >
                    <Label for="add-entry-linked-asset"
                        >Attività collegata (opzionale)</Label
                    >
                    <select
                        id="add-entry-linked-asset"
                        name="linked_asset_id"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">Nessuna</option>
                        <option
                            v-for="asset in assetOptions"
                            :key="asset.id"
                            :value="asset.id"
                        >
                            {{ asset.name }}
                        </option>
                    </select>
                    <InputError :message="errors.linked_asset_id" />
                </div>
                <div
                    v-if="
                        balanceSheetEntryTypeConfig[
                            section as BalanceSheetGenericEntryType
                        ].showLinkedLiability && liabilityOptions.length
                    "
                    class="grid gap-2"
                >
                    <Label for="add-entry-linked-liability"
                        >Passività collegata (opzionale)</Label
                    >
                    <select
                        id="add-entry-linked-liability"
                        name="linked_liability_id"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">Nessuna</option>
                        <option
                            v-for="liability in liabilityOptions"
                            :key="liability.id"
                            :value="liability.id"
                        >
                            {{ liability.name }}
                        </option>
                    </select>
                    <InputError :message="errors.linked_liability_id" />
                </div>
                <Button type="submit" :disabled="processing">Aggiungi</Button>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Dialog: modifica attività -->
    <Dialog
        :open="isEditAssetOpen"
        @update:open="
            (open) => {
                if (!open) closeEditAsset();
            }
        "
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Modifica attività</DialogTitle>
            </DialogHeader>
            <Form
                v-if="editingAsset"
                :key="`edit-asset-${editingAsset.id}`"
                v-bind="
                    AssetController.update.form(editingAsset.id, {
                        query: { from_ledger: 1 },
                    })
                "
                class="grid grid-cols-1 gap-4"
                v-slot="{ errors, processing }"
                @success="closeEditAsset"
            >
                <div class="grid gap-2">
                    <Label for="edit-asset-name">Descrizione</Label>
                    <Input
                        id="edit-asset-name"
                        name="name"
                        required
                        autofocus
                        :default-value="editingAsset.name"
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-asset-category">Categoria</Label>
                    <Input
                        id="edit-asset-category"
                        name="category"
                        list="categories-edit-asset"
                        :default-value="editingAsset.category ?? ''"
                    />
                    <datalist id="categories-edit-asset">
                        <option
                            v-for="category in categorySuggestions.asset"
                            :key="category"
                            :value="category"
                        />
                    </datalist>
                    <InputError :message="errors.category" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-asset-value">Valore attività</Label>
                    <Input
                        id="edit-asset-value"
                        name="asset_value"
                        type="number"
                        step="0.01"
                        required
                        :default-value="editingAsset.amount ?? ''"
                    />
                    <InputError :message="errors.asset_value" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-asset-income"
                        >Entrata/mese (opzionale)</Label
                    >
                    <Input
                        id="edit-asset-income"
                        name="monthly_income"
                        type="number"
                        step="0.01"
                        :default-value="
                            linkedOfType(editingAsset.id, 'income')?.amount ??
                            ''
                        "
                    />
                    <InputError :message="errors.monthly_income" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-asset-cash"
                        >Liquidità usata (opzionale)</Label
                    >
                    <Input
                        id="edit-asset-cash"
                        name="cash_used"
                        type="number"
                        step="0.01"
                        :default-value="editingAsset.cash_used ?? ''"
                    />
                    <InputError :message="errors.cash_used" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-asset-mortgage"
                        >Totale mutuo (opzionale)</Label
                    >
                    <Input
                        id="edit-asset-mortgage"
                        name="mortgage_total"
                        type="number"
                        step="0.01"
                        :default-value="
                            linkedOfType(editingAsset.id, 'liability')
                                ?.amount ?? ''
                        "
                    />
                    <InputError :message="errors.mortgage_total" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-asset-installment"
                        >Rata/mese (opzionale)</Label
                    >
                    <Input
                        id="edit-asset-installment"
                        name="monthly_installment"
                        type="number"
                        step="0.01"
                        :default-value="
                            linkedOfType(editingAsset.id, 'expense')?.amount ??
                            ''
                        "
                    />
                    <InputError :message="errors.monthly_installment" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-asset-hours">Ore/mese (opzionale)</Label>
                    <Input
                        id="edit-asset-hours"
                        name="hours_per_month"
                        type="number"
                        step="0.1"
                        :default-value="
                            linkedOfType(editingAsset.id, 'time')
                                ?.hours_per_month ?? ''
                        "
                    />
                    <InputError :message="errors.hours_per_month" />
                </div>
                <Button type="submit" :disabled="processing"
                    >Salva modifiche</Button
                >
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Dialog: modifica voce -->
    <Dialog
        :open="isEditEntryOpen"
        @update:open="
            (open) => {
                if (!open) closeEditEntry();
            }
        "
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Modifica {{ editingEntryConfig?.singular }}</DialogTitle
                >
            </DialogHeader>
            <Form
                v-if="editingEntry && editingEntryConfig"
                :key="`edit-entry-${editingEntry.id}`"
                v-bind="
                    EntryController.update.form(
                        [editingEntry.type, editingEntry.id],
                        { query: { from_ledger: 1 } },
                    )
                "
                class="grid grid-cols-1 gap-4"
                v-slot="{ errors, processing }"
                @success="closeEditEntry"
            >
                <div class="grid gap-2">
                    <Label for="edit-entry-name">Nome</Label>
                    <Input
                        id="edit-entry-name"
                        name="name"
                        required
                        autofocus
                        :default-value="editingEntry.name"
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-entry-category">Categoria</Label>
                    <Input
                        id="edit-entry-category"
                        name="category"
                        list="categories-edit-entry"
                        :default-value="editingEntry.category ?? ''"
                    />
                    <datalist id="categories-edit-entry">
                        <option
                            v-for="category in categorySuggestions[
                                editingEntry.type as BalanceSheetGenericEntryType
                            ]"
                            :key="category"
                            :value="category"
                        />
                    </datalist>
                    <InputError :message="errors.category" />
                </div>
                <div v-if="editingEntryConfig.showAmount" class="grid gap-2">
                    <Label for="edit-entry-amount">{{
                        editingEntryConfig.amountLabel
                    }}</Label>
                    <Input
                        id="edit-entry-amount"
                        name="amount"
                        type="number"
                        step="0.01"
                        required
                        :default-value="editingEntry.amount ?? ''"
                    />
                    <InputError :message="errors.amount" />
                </div>
                <div v-if="editingEntryConfig.showFrequency" class="grid gap-2">
                    <Label for="edit-entry-frequency">Frequenza</Label>
                    <select
                        id="edit-entry-frequency"
                        name="frequency"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option
                            value="monthly"
                            :selected="editingEntry.frequency === 'monthly'"
                        >
                            Mensile
                        </option>
                        <option
                            value="one_time"
                            :selected="editingEntry.frequency === 'one_time'"
                        >
                            Una tantum
                        </option>
                    </select>
                    <InputError :message="errors.frequency" />
                </div>
                <template v-if="editingEntryConfig.showHours">
                    <div class="grid gap-2">
                        <Label for="edit-entry-hours">Ore al mese</Label>
                        <Input
                            id="edit-entry-hours"
                            name="hours_per_month"
                            type="number"
                            step="0.1"
                            required
                            :default-value="editingEntry.hours_per_month ?? ''"
                        />
                        <InputError :message="errors.hours_per_month" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-entry-time-kind">Tipo</Label>
                        <select
                            id="edit-entry-time-kind"
                            name="time_kind"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option
                                value="consumes"
                                :selected="
                                    editingEntry.time_kind === 'consumes'
                                "
                            >
                                Consuma tempo
                            </option>
                            <option
                                value="frees"
                                :selected="editingEntry.time_kind === 'frees'"
                            >
                                Libera tempo
                            </option>
                        </select>
                        <InputError :message="errors.time_kind" />
                    </div>
                </template>
                <div v-if="editingEntryConfig.showProgress" class="grid gap-2">
                    <Label for="edit-entry-progress">Progresso (%)</Label>
                    <Input
                        id="edit-entry-progress"
                        name="progress_percent"
                        type="number"
                        min="0"
                        max="100"
                        required
                        :default-value="editingEntry.progress_percent ?? 0"
                    />
                    <InputError :message="errors.progress_percent" />
                </div>
                <div
                    v-if="
                        editingEntryConfig.showLinkedAsset &&
                        assetOptions.length
                    "
                    class="grid gap-2"
                >
                    <Label for="edit-entry-linked-asset"
                        >Attività collegata (opzionale)</Label
                    >
                    <select
                        id="edit-entry-linked-asset"
                        name="linked_asset_id"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">Nessuna</option>
                        <option
                            v-for="asset in assetOptions"
                            :key="asset.id"
                            :value="asset.id"
                            :selected="
                                editingEntry.linked_asset_id === asset.id
                            "
                        >
                            {{ asset.name }}
                        </option>
                    </select>
                    <InputError :message="errors.linked_asset_id" />
                </div>
                <div
                    v-if="
                        editingEntryConfig.showLinkedLiability &&
                        liabilityOptions.length
                    "
                    class="grid gap-2"
                >
                    <Label for="edit-entry-linked-liability"
                        >Passività collegata (opzionale)</Label
                    >
                    <select
                        id="edit-entry-linked-liability"
                        name="linked_liability_id"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">Nessuna</option>
                        <option
                            v-for="liability in liabilityOptions"
                            :key="liability.id"
                            :value="liability.id"
                            :selected="
                                editingEntry.linked_liability_id ===
                                liability.id
                            "
                        >
                            {{ liability.name }}
                        </option>
                    </select>
                    <InputError :message="errors.linked_liability_id" />
                </div>
                <Button type="submit" :disabled="processing"
                    >Salva modifiche</Button
                >
            </Form>
        </DialogContent>
    </Dialog>
</template>
