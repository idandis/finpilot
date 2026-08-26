<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import AssetController from '@/actions/App/Http/Controllers/BalanceSheet/AssetController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import balanceSheet from '@/routes/balance-sheet';
import * as assetRoutes from '@/routes/balance-sheet/assets';
import type { BalanceSheetEntry } from '@/types';

const props = defineProps<{
    asset: BalanceSheetEntry;
    categorySuggestions: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Bilancio patrimoniale', href: balanceSheet.index() },
            { title: 'Attività', href: assetRoutes.index() },
            { title: 'Modifica attività', href: assetRoutes.index() },
        ],
    },
});

function destroyAsset() {
    if (
        confirm(
            `Vuoi eliminare "${props.asset.name}" e tutte le voci collegate: entrata, mutuo, rata e tempo?`,
        )
    ) {
        router.delete(assetRoutes.destroy(props.asset.id).url);
    }
}
</script>

<template>
    <Head :title="`Modifica ${asset.name}`" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <Heading title="Modifica attività" :description="asset.name" />

        <Form
            v-bind="AssetController.update.form(asset.id)"
            class="max-w-lg space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Descrizione</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    :default-value="asset.name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="category">Categoria</Label>
                <Input
                    id="category"
                    name="category"
                    list="categories"
                    :default-value="asset.category ?? ''"
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

            <div class="grid gap-2">
                <Label for="asset_value">Valore attività</Label>
                <Input
                    id="asset_value"
                    name="asset_value"
                    type="number"
                    step="0.01"
                    required
                    :default-value="asset.amount ?? ''"
                />
                <InputError :message="errors.asset_value" />
            </div>

            <div class="grid gap-2">
                <Label for="monthly_income">Entrata/mese (opzionale)</Label>
                <Input
                    id="monthly_income"
                    name="monthly_income"
                    type="number"
                    step="0.01"
                    :default-value="asset.linked_income?.amount ?? ''"
                />
                <InputError :message="errors.monthly_income" />
            </div>

            <div class="grid gap-2">
                <Label for="cash_used">Liquidità usata (opzionale)</Label>
                <Input
                    id="cash_used"
                    name="cash_used"
                    type="number"
                    step="0.01"
                    :default-value="asset.cash_used ?? ''"
                />
                <InputError :message="errors.cash_used" />
            </div>

            <div class="grid gap-2">
                <Label for="mortgage_total">Totale mutuo (opzionale)</Label>
                <Input
                    id="mortgage_total"
                    name="mortgage_total"
                    type="number"
                    step="0.01"
                    :default-value="asset.linked_liability?.amount ?? ''"
                />
                <InputError :message="errors.mortgage_total" />
            </div>

            <div class="grid gap-2">
                <Label for="monthly_installment">Rata/mese (opzionale)</Label>
                <Input
                    id="monthly_installment"
                    name="monthly_installment"
                    type="number"
                    step="0.01"
                    :default-value="asset.linked_expense?.amount ?? ''"
                />
                <InputError :message="errors.monthly_installment" />
            </div>

            <div class="grid gap-2">
                <Label for="hours_per_month">Ore/mese (opzionale)</Label>
                <Input
                    id="hours_per_month"
                    name="hours_per_month"
                    type="number"
                    step="0.1"
                    :default-value="asset.linked_time?.hours_per_month ?? ''"
                />
                <InputError :message="errors.hours_per_month" />
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing"
                    >Salva modifiche</Button
                >
                <Button
                    type="button"
                    variant="destructive"
                    @click="destroyAsset"
                    >Elimina</Button
                >
            </div>
        </Form>
    </div>
</template>
