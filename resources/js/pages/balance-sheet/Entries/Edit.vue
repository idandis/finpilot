<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import EntryController from '@/actions/App/Http/Controllers/BalanceSheet/EntryController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { balanceSheetEntryTypeConfig } from '@/lib/balance-sheet-entry-types';
import balanceSheet from '@/routes/balance-sheet';
import * as entryRoutes from '@/routes/balance-sheet/entries';
import type {
    BalanceSheetEntry,
    BalanceSheetGenericEntryType,
    BalanceSheetLiabilityOption,
} from '@/types';

const props = defineProps<{
    type: BalanceSheetGenericEntryType;
    entry: BalanceSheetEntry;
    categorySuggestions: string[];
    liabilities: BalanceSheetLiabilityOption[];
    assets: BalanceSheetLiabilityOption[];
}>();

const config = balanceSheetEntryTypeConfig[props.type];

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Bilancio patrimoniale', href: balanceSheet.index() },
        ],
    },
});

function destroyEntry() {
    if (confirm(`Vuoi eliminare "${props.entry.name}"?`)) {
        router.delete(entryRoutes.destroy([props.type, props.entry.id]).url);
    }
}
</script>

<template>
    <Head :title="`Modifica ${entry.name}`" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <Heading
            :title="`Modifica ${config.singular}`"
            :description="entry.name"
        />

        <Form
            v-bind="EntryController.update.form([type, entry.id])"
            class="max-w-lg space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    :default-value="entry.name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="category">Categoria</Label>
                <Input
                    id="category"
                    name="category"
                    list="categories"
                    :default-value="entry.category ?? ''"
                />
                <datalist id="categories">
                    <option
                        v-for="category in categorySuggestions"
                        :key="category"
                        :value="category"
                    />
                </datalist>
                <InputError :message="errors.category" />
            </div>

            <div v-if="config.showAmount" class="grid gap-2">
                <Label for="amount">{{ config.amountLabel }}</Label>
                <Input
                    id="amount"
                    name="amount"
                    type="number"
                    step="0.01"
                    required
                    :default-value="entry.amount ?? ''"
                />
                <InputError :message="errors.amount" />
            </div>

            <div v-if="config.showFrequency" class="grid gap-2">
                <Label for="frequency">Frequenza</Label>
                <select
                    id="frequency"
                    name="frequency"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option
                        value="monthly"
                        :selected="entry.frequency === 'monthly'"
                    >
                        Mensile
                    </option>
                    <option
                        value="one_time"
                        :selected="entry.frequency === 'one_time'"
                    >
                        Una tantum
                    </option>
                </select>
                <InputError :message="errors.frequency" />
            </div>

            <template v-if="config.showHours">
                <div class="grid gap-2">
                    <Label for="hours_per_month">Ore al mese</Label>
                    <Input
                        id="hours_per_month"
                        name="hours_per_month"
                        type="number"
                        step="0.1"
                        required
                        :default-value="entry.hours_per_month ?? ''"
                    />
                    <InputError :message="errors.hours_per_month" />
                </div>

                <div class="grid gap-2">
                    <Label for="time_kind">Tipo</Label>
                    <select
                        id="time_kind"
                        name="time_kind"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option
                            value="consumes"
                            :selected="entry.time_kind === 'consumes'"
                        >
                            Consuma tempo
                        </option>
                        <option
                            value="frees"
                            :selected="entry.time_kind === 'frees'"
                        >
                            Libera tempo
                        </option>
                    </select>
                    <InputError :message="errors.time_kind" />
                </div>
            </template>

            <div v-if="config.showProgress" class="grid gap-2">
                <Label for="progress_percent">Progresso (%)</Label>
                <Input
                    id="progress_percent"
                    name="progress_percent"
                    type="number"
                    min="0"
                    max="100"
                    required
                    :default-value="entry.progress_percent ?? 0"
                />
                <InputError :message="errors.progress_percent" />
            </div>

            <div
                v-if="config.showLinkedAsset && assets.length"
                class="grid gap-2"
            >
                <Label for="linked_asset_id"
                    >Attività collegata (opzionale)</Label
                >
                <select
                    id="linked_asset_id"
                    name="linked_asset_id"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Nessuna</option>
                    <option
                        v-for="asset in assets"
                        :key="asset.id"
                        :value="asset.id"
                        :selected="entry.linked_asset_id === asset.id"
                    >
                        {{ asset.name }}
                    </option>
                </select>
                <InputError :message="errors.linked_asset_id" />
            </div>

            <div
                v-if="config.showLinkedLiability && liabilities.length"
                class="grid gap-2"
            >
                <Label for="linked_liability_id"
                    >Passività collegata (opzionale)</Label
                >
                <select
                    id="linked_liability_id"
                    name="linked_liability_id"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Nessuna</option>
                    <option
                        v-for="liability in liabilities"
                        :key="liability.id"
                        :value="liability.id"
                        :selected="entry.linked_liability_id === liability.id"
                    >
                        {{ liability.name }}
                    </option>
                </select>
                <InputError :message="errors.linked_liability_id" />
            </div>

            <Label for="active" class="flex items-center space-x-3">
                <input type="hidden" name="active" value="0" />
                <Checkbox
                    id="active"
                    name="active"
                    value="1"
                    :default-value="entry.active"
                />
                <span>Voce attiva</span>
            </Label>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing"
                    >Salva modifiche</Button
                >
                <Button
                    type="button"
                    variant="destructive"
                    @click="destroyEntry"
                    >Elimina</Button
                >
            </div>
        </Form>
    </div>
</template>
