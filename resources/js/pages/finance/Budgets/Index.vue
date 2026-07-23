<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import * as budgetRoutes from '@/routes/budgets';
import type { Card, CategoryBudgetRow } from '@/types';

const props = defineProps<{
    budgets: CategoryBudgetRow[];
    totalBudget: number;
    cards: Card[];
}>();

const setBudgets = computed(() =>
    props.budgets.filter((row) => row.monthly_budget !== null),
);
const emptyBudgets = computed(() =>
    props.budgets.filter((row) => row.monthly_budget === null),
);

const activeTab = ref<'set' | 'empty'>('set');
const visibleBudgets = computed(() =>
    activeTab.value === 'set' ? setBudgets.value : emptyBudgets.value,
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Budget', href: budgetRoutes.index() }],
    },
});

function formatCurrency(value: number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

const editingId = ref<number | null>(null);
const editingValue = ref('');
const errors = ref<Record<number, string>>({});

function startEditing(row: CategoryBudgetRow) {
    editingId.value = row.category_id;
    editingValue.value =
        row.monthly_budget !== null ? String(row.monthly_budget) : '';
    delete errors.value[row.category_id];
}

function cancelEditing() {
    editingId.value = null;
}

// Accepts both "200.50" and the Italian "200,50" (and "1.200,50"), since a
// native number input silently blanks out comma input instead of accepting it.
function normalizeAmount(raw: string): string {
    const trimmed = raw.trim();

    if (trimmed === '') {
        return '';
    }

    const hasComma = trimmed.includes(',');
    const hasDot = trimmed.includes('.');

    if (hasComma && hasDot) {
        return trimmed.replace(/\./g, '').replace(',', '.');
    }

    if (hasComma) {
        return trimmed.replace(',', '.');
    }

    return trimmed;
}

function updateCard(row: CategoryBudgetRow, value: string) {
    router.patch(
        budgetRoutes.update(row.category_id).url,
        { card_id: value === '' ? null : Number(value) },
        { preserveScroll: true, preserveState: true },
    );
}

function saveBudget(row: CategoryBudgetRow) {
    const amount = normalizeAmount(editingValue.value);

    if (amount === (row.monthly_budget?.toString() ?? '')) {
        editingId.value = null;

        return;
    }

    router.patch(
        budgetRoutes.update(row.category_id).url,
        { monthly_amount: amount === '' ? null : amount },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                delete errors.value[row.category_id];
                editingId.value = null;
            },
            onError: (pageErrors) => {
                errors.value[row.category_id] =
                    pageErrors.monthly_amount ?? 'Valore non valido, riprova.';
            },
        },
    );
}
</script>

<template>
    <Head title="Budget" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            title="Budget"
            description="Imposta quanto vorresti spendere al mese per ciascuna categoria. Il confronto con la spesa reale arriva in un secondo momento."
        />

        <Tabs v-model="activeTab">
            <TabsList>
                <TabsTrigger value="set"
                    >Impostati ({{ setBudgets.length }})</TabsTrigger
                >
                <TabsTrigger value="empty"
                    >Da impostare ({{ emptyBudgets.length }})</TabsTrigger
                >
            </TabsList>
        </Tabs>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Categoria</TableHead>
                    <TableHead>Carta</TableHead>
                    <TableHead class="text-right">Budget mensile</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-if="visibleBudgets.length === 0">
                    <TableCell
                        colspan="3"
                        class="text-center text-muted-foreground"
                    >
                        {{
                            activeTab === 'set'
                                ? 'Nessun budget impostato.'
                                : 'Tutte le categorie hanno un budget impostato.'
                        }}
                    </TableCell>
                </TableRow>
                <TableRow v-for="row in visibleBudgets" :key="row.category_id">
                    <TableCell>
                        <span class="flex items-center gap-2">
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{
                                    backgroundColor: row.color ?? '#71717a',
                                }"
                            />
                            {{ row.name }}
                        </span>
                    </TableCell>
                    <TableCell>
                        <select
                            :value="row.card_id ?? ''"
                            :disabled="row.monthly_budget === null"
                            :title="
                                row.monthly_budget === null
                                    ? 'Imposta prima un budget mensile'
                                    : 'Associa questo budget a una carta'
                            "
                            class="h-8 w-full max-w-48 rounded-md border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                            @change="
                                updateCard(
                                    row,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">Tutte le carte</option>
                            <option
                                v-for="card in cards"
                                :key="card.id"
                                :value="card.id"
                            >
                                {{ card.name }}
                            </option>
                        </select>
                    </TableCell>
                    <TableCell class="text-right">
                        <div v-if="editingId === row.category_id">
                            <input
                                v-model="editingValue"
                                type="text"
                                inputmode="decimal"
                                autofocus
                                placeholder="200 oppure 200,50"
                                class="h-8 w-32 rounded-md border border-input bg-transparent px-2 text-right text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                @keyup.enter="
                                    ($event.target as HTMLInputElement).blur()
                                "
                                @keyup.esc="cancelEditing"
                                @blur="saveBudget(row)"
                            />
                            <p
                                v-if="errors[row.category_id]"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ errors[row.category_id] }}
                            </p>
                        </div>
                        <button
                            v-else
                            type="button"
                            class="hover:underline"
                            :class="{
                                'text-muted-foreground':
                                    row.monthly_budget === null,
                            }"
                            title="Modifica budget mensile"
                            @click="startEditing(row)"
                        >
                            {{
                                row.monthly_budget !== null
                                    ? formatCurrency(row.monthly_budget)
                                    : 'Imposta budget'
                            }}
                        </button>
                    </TableCell>
                </TableRow>
            </TableBody>
            <TableFooter>
                <TableRow>
                    <TableCell class="font-medium">Totale</TableCell>
                    <TableCell />
                    <TableCell class="text-right font-medium">
                        {{ formatCurrency(totalBudget) }}
                    </TableCell>
                </TableRow>
            </TableFooter>
        </Table>
    </div>
</template>
