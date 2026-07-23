<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import TransactionImportController from '@/actions/App/Http/Controllers/Finance/TransactionImportController';
import BankCard from '@/components/finance/BankCard.vue';
import CategorySpendingChart from '@/components/finance/CategorySpendingChart.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import * as cardRoutes from '@/routes/cards';
import * as cardTransactionRoutes from '@/routes/cards/transactions';
import * as transactionRoutes from '@/routes/transactions';
import type {
    Card,
    CategoryBreakdownItem,
    Transaction,
    TransactionCategory,
} from '@/types';

const props = defineProps<{
    card: Card;
    transactions: Transaction[];
    categories: TransactionCategory[];
    totals: { income: number; expense: number };
    categoryBreakdown: CategoryBreakdownItem[];
    incomeCategoryBreakdown: CategoryBreakdownItem[];
    filters: { year: number; month: number };
    cardTransactionsCount: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Carte', href: cardRoutes.index() },
            { title: 'Movimenti', href: cardRoutes.index() },
        ],
    },
});

const currency = computed(
    () => props.card.financial_account?.currency ?? 'EUR',
);

const monthLabel = computed(() => {
    const date = new Date(props.filters.year, props.filters.month - 1, 1);
    const label = date.toLocaleDateString('it-IT', {
        month: 'long',
        year: 'numeric',
    });

    return label.charAt(0).toUpperCase() + label.slice(1);
});

function formatCurrency(value: number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency.value,
    }).format(value);
}

function formatDate(value: string) {
    return new Date(value).toLocaleDateString('it-IT');
}

function goToMonth(year: number, month: number) {
    router.get(
        cardRoutes.show.url(props.card.id),
        { year, month },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function shiftMonth(delta: number) {
    let month = props.filters.month + delta;
    let year = props.filters.year;

    if (month < 1) {
        month = 12;
        year -= 1;
    } else if (month > 12) {
        month = 1;
        year += 1;
    }

    goToMonth(year, month);
}

const directionFilter = ref<'all' | 'income' | 'expense'>('all');
const categoryFilter = ref<'all' | 'uncategorized' | string>('all');

const filteredTransactions = computed(() => {
    return props.transactions.filter((transaction) => {
        if (
            directionFilter.value !== 'all' &&
            transaction.direction !== directionFilter.value
        ) {
            return false;
        }

        if (
            categoryFilter.value === 'uncategorized' &&
            transaction.transaction_category_id !== null
        ) {
            return false;
        }

        if (
            categoryFilter.value !== 'all' &&
            categoryFilter.value !== 'uncategorized' &&
            String(transaction.transaction_category_id) !== categoryFilter.value
        ) {
            return false;
        }

        return true;
    });
});

function updateCategory(transaction: Transaction, value: string) {
    router.patch(
        transactionRoutes.update(transaction.id).url,
        { transaction_category_id: value === '' ? null : Number(value) },
        { preserveScroll: true, preserveState: true },
    );
}

const editingId = ref<number | null>(null);
const editingDescription = ref('');

function startEditingDescription(transaction: Transaction) {
    editingId.value = transaction.id;
    editingDescription.value = transaction.description;
}

function cancelEditingDescription() {
    editingId.value = null;
}

function saveDescription(transaction: Transaction) {
    const value = editingDescription.value.trim();
    editingId.value = null;

    if (value === '' || value === transaction.description) {
        return;
    }

    router.patch(
        transactionRoutes.update(transaction.id).url,
        { description: value },
        { preserveScroll: true, preserveState: true },
    );
}

function destroyTransaction(transaction: Transaction) {
    if (confirm('Eliminare questa transazione?')) {
        router.delete(transactionRoutes.destroy(transaction.id).url, {
            preserveScroll: true,
        });
    }
}

function destroyAllTransactions() {
    const message = `Eliminare TUTTI i ${props.cardTransactionsCount} movimenti di questa carta (tutti i mesi)? L'operazione non può essere annullata. Potrai reimportare l'estratto conto subito dopo.`;

    if (confirm(message)) {
        router.delete(cardTransactionRoutes.destroy(props.card.id).url);
    }
}
</script>

<template>
    <Head :title="`${card.name} · ${monthLabel}`" />

    <div class="flex flex-col gap-6 p-4">
        <div class="grid gap-6 lg:grid-cols-[300px_1fr]">
            <div class="space-y-4">
                <BankCard :card="card" />
                <Button as-child variant="outline" size="sm" class="w-full">
                    <Link :href="cardRoutes.edit(card.id)">Modifica carta</Link>
                </Button>
            </div>

            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <Heading title="Movimenti" :description="card.name" />
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="icon-sm"
                            @click="shiftMonth(-1)"
                        >
                            <ChevronLeft />
                        </Button>
                        <span class="min-w-36 text-center text-sm font-medium">
                            {{ monthLabel }}
                        </span>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            @click="shiftMonth(1)"
                        >
                            <ChevronRight />
                        </Button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border p-4">
                        <p class="text-xs text-muted-foreground">Entrate</p>
                        <p class="text-lg font-semibold text-green-600">
                            {{ formatCurrency(totals.income) }}
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-xs text-muted-foreground">Uscite</p>
                        <p class="text-lg font-semibold text-red-600">
                            {{ formatCurrency(totals.expense) }}
                        </p>
                    </div>
                </div>

                <Tabs default-value="movimenti">
                    <TabsList>
                        <TabsTrigger value="movimenti">Movimenti</TabsTrigger>
                        <TabsTrigger value="statistiche"
                            >Statistiche</TabsTrigger
                        >
                    </TabsList>

                    <TabsContent value="movimenti" class="space-y-6 pt-4">
                        <div
                            v-if="transactions.length > 0"
                            class="flex flex-wrap items-end gap-4 rounded-lg border p-4"
                        >
                            <div class="grid gap-2">
                                <label
                                    for="direction-filter"
                                    class="text-sm font-medium"
                                >
                                    Tipo
                                </label>
                                <select
                                    id="direction-filter"
                                    v-model="directionFilter"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <option value="all">Tutte</option>
                                    <option value="income">Entrate</option>
                                    <option value="expense">Uscite</option>
                                </select>
                            </div>

                            <div class="grid gap-2">
                                <label
                                    for="category-filter"
                                    class="text-sm font-medium"
                                >
                                    Categoria
                                </label>
                                <select
                                    id="category-filter"
                                    v-model="categoryFilter"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <option value="all">Tutte</option>
                                    <option value="uncategorized">
                                        Non categorizzato
                                    </option>
                                    <option
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="String(category.id)"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div
                            v-if="transactions.length === 0"
                            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                        >
                            Nessuna transazione per {{ monthLabel }}. Importa un
                            estratto conto CSV qui sotto per iniziare.
                        </div>

                        <div
                            v-else-if="filteredTransactions.length === 0"
                            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                        >
                            Nessuna transazione corrisponde ai filtri
                            selezionati.
                        </div>

                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Data</TableHead>
                                    <TableHead>Descrizione</TableHead>
                                    <TableHead>Categoria</TableHead>
                                    <TableHead class="text-right"
                                        >Importo</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Azioni</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="transaction in filteredTransactions"
                                    :key="transaction.id"
                                >
                                    <TableCell>{{
                                        formatDate(transaction.transaction_date)
                                    }}</TableCell>
                                    <TableCell class="max-w-64">
                                        <input
                                            v-if="editingId === transaction.id"
                                            v-model="editingDescription"
                                            type="text"
                                            autofocus
                                            class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            @keyup.enter="
                                                saveDescription(transaction)
                                            "
                                            @keyup.esc="
                                                cancelEditingDescription
                                            "
                                            @blur="saveDescription(transaction)"
                                        />
                                        <button
                                            v-else
                                            type="button"
                                            class="block w-full truncate text-left hover:underline"
                                            title="Modifica descrizione"
                                            @click="
                                                startEditingDescription(
                                                    transaction,
                                                )
                                            "
                                        >
                                            {{ transaction.description }}
                                        </button>
                                    </TableCell>
                                    <TableCell>
                                        <select
                                            :value="
                                                transaction.transaction_category_id ??
                                                ''
                                            "
                                            class="h-8 rounded-md border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            @change="
                                                updateCategory(
                                                    transaction,
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option value="">
                                                Non categorizzato
                                            </option>
                                            <option
                                                v-for="category in categories"
                                                :key="category.id"
                                                :value="category.id"
                                            >
                                                {{ category.name }}
                                            </option>
                                        </select>
                                    </TableCell>
                                    <TableCell
                                        class="text-right font-medium"
                                        :class="
                                            transaction.direction === 'expense'
                                                ? 'text-red-600'
                                                : 'text-green-600'
                                        "
                                    >
                                        {{
                                            formatCurrency(
                                                Number(transaction.amount) *
                                                    (transaction.direction ===
                                                    'expense'
                                                        ? -1
                                                        : 1),
                                            )
                                        }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                            title="Elimina transazione"
                                            @click="
                                                destroyTransaction(transaction)
                                            "
                                        >
                                            <Trash2 />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <Form
                            v-bind="
                                TransactionImportController.store.form(card.id)
                            "
                            reset-on-success
                            class="flex flex-wrap items-end gap-4 rounded-lg border p-4"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-2">
                                <label for="file" class="text-sm font-medium">
                                    Importa estratto conto (CSV o PDF Trade
                                    Republic)
                                </label>
                                <input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept=".csv,.txt,.pdf"
                                    required
                                    class="h-9 w-full max-w-sm rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none file:mr-3 file:h-full file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError :message="errors.file" />
                            </div>

                            <Button
                                type="submit"
                                variant="secondary"
                                :disabled="processing"
                            >
                                Importa
                            </Button>
                        </Form>

                        <div
                            v-if="cardTransactionsCount > 0"
                            class="flex items-center justify-between rounded-lg border border-destructive/30 p-4"
                        >
                            <div class="space-y-0.5">
                                <p class="text-sm font-medium">
                                    Import andato male?
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Elimina tutti i {{ cardTransactionsCount }}
                                    movimenti di questa carta (tutti i mesi) e
                                    reimporta l'estratto conto da capo.
                                </p>
                            </div>
                            <Button
                                variant="destructive"
                                size="sm"
                                @click="destroyAllTransactions"
                            >
                                Elimina movimenti carta
                            </Button>
                        </div>
                    </TabsContent>

                    <TabsContent value="statistiche" class="flex flex-col gap-4 pt-4">
                        <div class="@container rounded-lg border p-4">
                            <p class="mb-4 text-sm font-medium">
                                Spesa per categoria · {{ monthLabel }}
                            </p>
                            <CategorySpendingChart
                                :breakdown="categoryBreakdown"
                                :currency="currency"
                            />
                        </div>
                        <div class="@container rounded-lg border p-4">
                            <p class="mb-4 text-sm font-medium">
                                Entrate per categoria · {{ monthLabel }}
                            </p>
                            <CategorySpendingChart
                                :breakdown="incomeCategoryBreakdown"
                                :currency="currency"
                                center-label="Entrate"
                                empty-message="Nessuna entrata da mostrare per questo mese."
                            />
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    </div>
</template>
