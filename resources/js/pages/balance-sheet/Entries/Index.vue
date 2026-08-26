<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { balanceSheetEntryTypeConfig } from '@/lib/balance-sheet-entry-types';
import { formatCurrency, formatHours } from '@/lib/balance-sheet-format';
import { groupBalanceSheetEntriesByCategory } from '@/lib/balance-sheet-grouping';
import balanceSheet from '@/routes/balance-sheet';
import * as entryRoutes from '@/routes/balance-sheet/entries';
import type { BalanceSheetEntry, BalanceSheetGenericEntryType } from '@/types';

const props = defineProps<{
    type: BalanceSheetGenericEntryType;
    entries: BalanceSheetEntry[];
}>();

const config = computed(() => balanceSheetEntryTypeConfig[props.type]);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Bilancio patrimoniale', href: balanceSheet.index() },
        ],
    },
});

const timeGroups = computed(() => {
    if (props.type !== 'time') {
        return null;
    }

    return {
        frees: props.entries.filter((entry) => entry.time_kind === 'frees'),
        consumes: props.entries.filter(
            (entry) => entry.time_kind === 'consumes',
        ),
    };
});

const categoryGroups = computed(() =>
    props.type === 'time'
        ? []
        : groupBalanceSheetEntriesByCategory(props.entries),
);

const total = computed(() => {
    if (props.type === 'time' || props.type === 'goal') {
        return null;
    }

    return props.entries
        .filter((entry) => entry.active)
        .reduce((sum, entry) => sum + Number(entry.amount ?? 0), 0);
});

function entryValue(entry: BalanceSheetEntry): string {
    if (props.type === 'time') {
        return formatHours(entry.hours_per_month ?? 0);
    }

    if (props.type === 'goal') {
        return `${entry.progress_percent ?? 0}%`;
    }

    return formatCurrency(entry.amount ?? 0);
}

function destroyEntry(entry: BalanceSheetEntry) {
    if (confirm(`Vuoi eliminare "${entry.name}"?`)) {
        router.delete(entryRoutes.destroy([props.type, entry.id]).url, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="config.title" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Heading
                :title="config.title"
                :description="
                    total !== null
                        ? `Totale: ${formatCurrency(total)}`
                        : undefined
                "
            />
            <Button as-child>
                <Link :href="entryRoutes.create(type).url">{{
                    config.addLabel
                }}</Link>
            </Button>
        </div>

        <template v-if="type === 'time' && timeGroups">
            <div
                v-for="group in [
                    { title: 'Attività del tempo', items: timeGroups.frees },
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
                            <Button as-child variant="ghost" size="icon-sm">
                                <Link
                                    :href="
                                        entryRoutes.edit([type, entry.id]).url
                                    "
                                >
                                    <Pencil />
                                </Link>
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
        </template>

        <template v-else>
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
                            <Button as-child variant="ghost" size="icon-sm">
                                <Link
                                    :href="
                                        entryRoutes.edit([type, entry.id]).url
                                    "
                                >
                                    <Pencil />
                                </Link>
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
        </template>
    </div>
</template>
