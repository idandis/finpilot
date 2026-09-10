<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import budgetCategories from '@/routes/budget-categories';
import budgetExpenses from '@/routes/budget-expenses';
import budgetSubcategories from '@/routes/budget-subcategories';
import BudgetColorPicker from '@/components/budget/BudgetColorPicker.vue';
import { formatAmount } from '@/lib/balance-sheet-format';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { avatarStyle } from '@/lib/avatar-color';

interface Subcategory {
    id: number;
    name: string;
    monthly_budget_id: number | null;
}

interface Category {
    id: number;
    name: string;
    color: string;
    type: string;
    subcategories: Subcategory[];
    monthly_budget_id: number | null;
}

interface Transaction {
    id: number;
    amount: number;
    description: string | null;
    recorded_at: string | null;
    subcategory_id: number;
    subcategory_name: string | null;
    category_id: number | null;
    category_name: string | null;
    category_color: string | null;
    direction: 'income' | 'expense' | 'transfer';
    account_id: number | null;
    account_name: string | null;
    account_color: string | null;
    recorded_by_id: number | null;
    recorded_by_name: string | null;
    kind: 'movement' | 'transfer';
    to_account_id: number | null;
    to_account_name: string | null;
    to_account_color: string | null;
}

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    category: Category | null;
    planned: Record<number, number>;
    actual: Record<number, number>;
    transactions: Transaction[];
    /** Solo in un budget condiviso: da soli la firma su ogni riga è rumore. */
    showRecordedBy: boolean;
    monthLabel: string;
    year: number;
    month: number;
}>();

const emit = defineEmits<{
    updatePlanned: [subcategoryId: number, value: string];
    flush: [];
    addSubcategory: [];
    deleteSubcategory: [subcategoryId: number];
    deleteCategory: [];
    deleteTransaction: [transaction: Transaction];
}>();

const isIncome = computed(() => props.category?.type === 'income');

const labels = computed(() => isIncome.value
    ? { actual: 'Incassato', residual: 'Da incassare' }
    : { actual: 'Speso', residual: 'Residuo' });

const plannedOf = (subcategoryId: number) => props.planned[subcategoryId] || 0;
const actualOf = (subcategoryId: number) => props.actual[subcategoryId] || 0;

const subcategories = computed(() => props.category?.subcategories ?? []);

// Una riga per voce, con sotto i movimenti che la compongono: l'effettivo non
// si digita, si ottiene modificando i movimenti.
const rows = computed(() => subcategories.value.map((sub) => ({
    sub,
    planned: plannedOf(sub.id),
    actual: actualOf(sub.id),
    residual: plannedOf(sub.id) - actualOf(sub.id),
    transactions: props.transactions.filter((transaction) => transaction.subcategory_id === sub.id),
})));

const plannedTotal = computed(() =>
    subcategories.value.reduce((sum, sub) => sum + plannedOf(sub.id), 0));

const actualTotal = computed(() =>
    subcategories.value.reduce((sum, sub) => sum + actualOf(sub.id), 0));

const residualTotal = computed(() => plannedTotal.value - actualTotal.value);

const isOver = computed(() => !isIncome.value && residualTotal.value < 0);

const usage = computed(() => {
    if (plannedTotal.value <= 0) return actualTotal.value > 0 ? 100 : 0;

    return Math.min((actualTotal.value / plannedTotal.value) * 100, 100);
});

// Nome e colore della categoria si salvano solo quando cambiano davvero.
const categoryName = ref('');
const categoryColor = ref('#3b82f6');

// Un nome per voce, così si può correggere senza salvare tutto insieme.
const subcategoryNames = ref<Record<number, string>>({});

watch(() => props.category, (category) => {
    categoryName.value = category?.name ?? '';
    categoryColor.value = category?.color ?? '#3b82f6';
    subcategoryNames.value = Object.fromEntries(
        (category?.subcategories ?? []).map((sub) => [sub.id, sub.name]),
    );
}, { immediate: true });

const isCategoryDirty = computed(() => Boolean(props.category)
    && categoryName.value.trim() !== ''
    && (categoryName.value.trim() !== props.category?.name
        || categoryColor.value !== props.category?.color));

const saveCategory = () => {
    if (!props.category || !isCategoryDirty.value) return;

    router.put(
        budgetCategories.update.url(props.category.id),
        { name: categoryName.value.trim(), color: categoryColor.value },
        { preserveScroll: true },
    );
};

const renameSubcategory = (sub: Subcategory) => {
    const name = (subcategoryNames.value[sub.id] ?? '').trim();

    if (name === '' || name === sub.name) {
        subcategoryNames.value[sub.id] = sub.name;

        return;
    }

    router.patch(budgetSubcategories.update.url(sub.id), { name }, { preserveScroll: true });
};

const scopeHint = computed(() => props.category?.monthly_budget_id
    ? `Questa categoria vive solo in ${props.monthLabel.toLowerCase()}.`
    : 'Categoria comune: nome e colore cambiano in tutti i mesi.');

// I movimenti si correggono sul posto: ogni riga tiene la sua bozza e la
// manda al server solo quando cambia davvero qualcosa.
interface Draft {
    description: string;
    amount: string;
    subcategoryId: number;
    date: string;
    time: string;
}

const pad = (value: number) => String(value).padStart(2, '0');

/** Sulla pastiglia ci sta il nome di battesimo, non il nome intero. */
const recordedByLabel = (transaction: Transaction) =>
    transaction.recorded_by_name?.split(' ')[0] ?? null;

/** Lo stesso colore dell'avatar: `avatarStyle` lo ricava dal nome intero. */
const recordedByStyle = (transaction: Transaction) =>
    avatarStyle(transaction.recorded_by_name ?? '');

const draftOf = (transaction: Transaction): Draft => {
    const moment = transaction.recorded_at ? new Date(transaction.recorded_at) : new Date();

    return {
        description: transaction.description ?? '',
        amount: String(transaction.amount),
        subcategoryId: transaction.subcategory_id,
        date: [moment.getFullYear(), pad(moment.getMonth() + 1), pad(moment.getDate())].join('-'),
        time: `${pad(moment.getHours())}:${pad(moment.getMinutes())}`,
    };
};

const dayFormatter = new Intl.DateTimeFormat('it-IT', { day: 'numeric', month: 'short' });

const dayLabel = (isoDate: string | null) =>
    isoDate ? dayFormatter.format(new Date(isoDate)) : '—';

const hourLabel = (isoDate: string | null) =>
    isoDate ? `${pad(new Date(isoDate).getHours())}:${pad(new Date(isoDate).getMinutes())}` : '';

const drafts = ref<Record<number, Draft>>({});

watch(() => props.transactions, (transactions) => {
    drafts.value = Object.fromEntries(
        transactions.map((transaction) => [transaction.id, draftOf(transaction)]),
    );
}, { immediate: true });

const saveTransaction = (transaction: Transaction) => {
    const draft = drafts.value[transaction.id];

    if (!draft) return;

    const amount = parseFloat(draft.amount.replace(',', '.'));

    // Un importo illeggibile o una riga identica all'originale: niente da fare.
    if (!Number.isFinite(amount) || amount <= 0) {
        drafts.value[transaction.id] = draftOf(transaction);

        return;
    }

    const original = draftOf(transaction);
    const normalized: Draft = { ...draft, amount: String(amount) };

    if (JSON.stringify(normalized) === JSON.stringify({ ...original, amount: String(parseFloat(original.amount)) })) {
        return;
    }

    router.put(
        budgetExpenses.update.url(transaction.id),
        {
            year: props.year,
            month: props.month,
            budget_subcategory_id: draft.subcategoryId,
            amount,
            description: draft.description.trim() || null,
            recorded_at: `${draft.date} ${draft.time}`,
        },
        { preserveScroll: true },
    );
};

</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent v-if="category" class="w-full overflow-y-auto sm:max-w-lg">
            <SheetHeader>
                <SheetTitle>Modifica “{{ category.name }}”</SheetTitle>
                <p class="text-xs text-muted-foreground">{{ scopeHint }}</p>
            </SheetHeader>

            <div class="space-y-6 px-4 pb-6">
                <!-- Stato del mese, come nella riga chiusa ma per esteso -->
                <div class="rounded-2xl bg-muted/60 px-4 py-3 dark:bg-muted/50">
                    <p class="text-xs text-muted-foreground">Atteso di {{ monthLabel.toLowerCase() }}</p>
                    <p class="text-2xl font-bold tabular-nums">{{ formatAmount(plannedTotal) }}</p>

                    <div class="mt-3 h-2.5 overflow-hidden rounded-sm bg-foreground/15">
                        <div
                            class="h-full rounded-sm transition-all"
                            :style="{
                                width: `${usage}%`,
                                backgroundColor: isOver ? '#ef4444' : '#10b981',
                            }"
                        />
                    </div>

                    <div class="mt-2 flex justify-between text-xs tabular-nums">
                        <span class="text-muted-foreground">
                            {{ labels.actual }} {{ formatAmount(actualTotal) }}
                            su {{ transactions.length }} movimenti
                        </span>
                        <span :class="isOver ? 'text-red-500' : 'text-muted-foreground'">
                            {{ labels.residual }} {{ formatAmount(residualTotal) }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="manager-category-name">Nome della categoria</Label>
                    <Input id="manager-category-name" v-model="categoryName" />
                    <BudgetColorPicker v-model="categoryColor" />
                    <Button
                        size="sm"
                        class="w-full"
                        :disabled="!isCategoryDirty"
                        @click="saveCategory"
                    >
                        <Check class="mr-2 size-4" />
                        Salva categoria
                    </Button>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="row in rows"
                        :key="row.sub.id"
                        class="space-y-3 rounded-xl bg-muted/60 p-3 dark:bg-muted/50"
                    >
                        <div class="flex items-center gap-2">
                            <Input
                                v-model="subcategoryNames[row.sub.id]"
                                class="h-8 flex-1"
                                :aria-label="`Nome di ${row.sub.name}`"
                                @blur="renameSubcategory(row.sub)"
                                @keyup.enter="renameSubcategory(row.sub)"
                            />
                            <Button
                                v-if="row.sub.monthly_budget_id"
                                variant="ghost"
                                size="icon-sm"
                                class="shrink-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                :title="`Elimina ${row.sub.name}`"
                                @click="emit('deleteSubcategory', row.sub.id)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs tabular-nums">
                            <span class="text-muted-foreground">Atteso</span>
                            <input
                                type="text"
                                inputmode="decimal"
                                :value="planned[row.sub.id] || ''"
                                class="w-20 rounded-md border border-foreground/40 bg-background/50 px-2 py-1 text-right text-sm tabular-nums focus:border-foreground focus:outline-none"
                                placeholder="0,00"
                                :aria-label="`Atteso per ${row.sub.name}`"
                                @input="emit('updatePlanned', row.sub.id, ($event.target as HTMLInputElement).value)"
                                @blur="emit('flush')"
                            />
                            <span class="text-muted-foreground">
                                {{ labels.actual }} {{ formatAmount(row.actual) }}
                            </span>
                            <span
                                class="ml-auto font-semibold"
                                :class="!isIncome && row.residual < 0 ? 'text-red-500' : ''"
                            >
                                {{ labels.residual }} {{ formatAmount(row.residual) }}
                            </span>
                        </div>

                        <!-- L'effettivo è la somma di questi: si corregge qui. -->
                        <div v-if="row.transactions.length" class="space-y-2 border-t border-border/60 pt-2">
                            <p class="text-[11px] uppercase tracking-wide text-muted-foreground">
                                Movimenti registrati ({{ row.transactions.length }})
                            </p>

                            <div
                                v-for="transaction in row.transactions"
                                :key="transaction.id"
                                class="flex items-center gap-2"
                            >
                                <span class="w-12 shrink-0 text-[11px] leading-tight text-muted-foreground">
                                    {{ dayLabel(transaction.recorded_at) }}<br>
                                    {{ hourLabel(transaction.recorded_at) }}
                                </span>

                                <Input
                                    v-model="drafts[transaction.id].description"
                                    class="h-8 flex-1 text-xs"
                                    placeholder="Descrizione"
                                    :aria-label="`Descrizione del movimento di ${formatAmount(transaction.amount)}`"
                                    @blur="saveTransaction(transaction)"
                                    @keyup.enter="saveTransaction(transaction)"
                                />

                                <span v-if="showRecordedBy && recordedByLabel(transaction)" class="max-w-20 shrink-0 truncate rounded-full px-2 py-0.5 text-[11px] font-semibold text-white" :style="recordedByStyle(transaction)" :title="`Registrato da ${transaction.recorded_by_name}`">{{ recordedByLabel(transaction) }}</span>

                                <input
                                    v-model="drafts[transaction.id].amount"
                                    type="text"
                                    inputmode="decimal"
                                    class="w-20 rounded-md border border-foreground/40 bg-background/50 px-2 py-1 text-right text-sm tabular-nums focus:border-foreground focus:outline-none"
                                    :aria-label="`Importo del movimento di ${formatAmount(transaction.amount)}`"
                                    @blur="saveTransaction(transaction)"
                                    @keyup.enter="saveTransaction(transaction)"
                                />

                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="shrink-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    title="Elimina il movimento"
                                    @click="emit('deleteTransaction', transaction)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </div>

                        <p v-else class="border-t border-border/60 pt-2 text-xs text-muted-foreground">
                            Nessun movimento registrato su questa voce.
                        </p>
                    </div>

                    <p v-if="rows.length === 0" class="text-sm text-muted-foreground">
                        Nessuna voce in questa categoria.
                    </p>

                    <Button
                        variant="outline"
                        size="sm"
                        class="w-full"
                        @click="emit('addSubcategory')"
                    >
                        <Plus class="mr-2 size-4" />
                        Aggiungi alla lista
                    </Button>

                    <Button
                        v-if="category.monthly_budget_id"
                        variant="ghost"
                        size="sm"
                        class="w-full text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="emit('deleteCategory')"
                    >
                        <Trash2 class="mr-2 size-4" />
                        Elimina la categoria da {{ monthLabel.toLowerCase() }}
                    </Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
