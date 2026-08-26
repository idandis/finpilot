<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/balance-sheet-format';
import balanceSheet from '@/routes/balance-sheet';
import * as assetRoutes from '@/routes/balance-sheet/assets';
import type { BalanceSheetEntry } from '@/types';

const props = defineProps<{
    assets: BalanceSheetEntry[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Bilancio patrimoniale', href: balanceSheet.index() },
            { title: 'Attività', href: assetRoutes.index() },
        ],
    },
});

const total = computed(() =>
    props.assets.reduce((sum, asset) => sum + Number(asset.amount ?? 0), 0),
);

function destroyAsset(asset: BalanceSheetEntry) {
    if (
        confirm(
            `Vuoi eliminare "${asset.name}" e tutte le voci collegate: entrata, mutuo, rata e tempo?`,
        )
    ) {
        router.delete(assetRoutes.destroy(asset.id).url);
    }
}
</script>

<template>
    <Head title="Attività" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Heading
                title="Attività"
                :description="`Totale: ${formatCurrency(total)}`"
            />
            <Button as-child>
                <Link :href="assetRoutes.create().url">Aggiungi attività</Link>
            </Button>
        </div>

        <p v-if="!assets.length" class="text-sm text-muted-foreground">
            Nessuna attività ancora.
        </p>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="asset in assets"
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
                        <span v-if="asset.linked_income"
                            >Entrata:
                            {{
                                formatCurrency(asset.linked_income.amount ?? 0)
                            }}/mese</span
                        >
                        <span v-if="asset.linked_liability"
                            >Mutuo residuo:
                            {{
                                formatCurrency(
                                    asset.linked_liability.amount ?? 0,
                                )
                            }}</span
                        >
                        <span v-if="asset.linked_expense"
                            >Rata:
                            {{
                                formatCurrency(
                                    asset.linked_expense.amount ?? 0,
                                )
                            }}/mese</span
                        >
                        <span v-if="asset.linked_time"
                            >Tempo:
                            {{ asset.linked_time.hours_per_month }}
                            h/mese</span
                        >
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="tabular-nums">{{
                        formatCurrency(asset.amount ?? 0)
                    }}</span>
                    <Button as-child variant="ghost" size="icon-sm">
                        <Link :href="assetRoutes.edit(asset.id).url">
                            <Pencil />
                        </Link>
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
</template>
