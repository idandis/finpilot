<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, Check, Plus, Trash2 } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import monthlyBudgets from '@/routes/monthly-budgets';
import budgetCategories from '@/routes/budget-categories';
import budgetSubcategories from '@/routes/budget-subcategories';
import budgetExpenses from '@/routes/budget-expenses';
import BudgetCategoryCard from '@/components/budget/BudgetCategoryCard.vue';
import BudgetExpenseSummary from '@/components/budget/BudgetExpenseSummary.vue';
import Heading from '@/components/Heading.vue';
import { formatCurrency } from '@/lib/balance-sheet-format';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Direction = 'income' | 'expense';

interface Subcategory {
    id: number;
    name: string;
    monthly_budget_id: number | null;
}

interface Category {
    id: number;
    name: string;
    color: string;
    type: Direction;
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
    direction: Direction;
}

const props = defineProps<{
    year: number;
    month: number;
    categories: Category[];
    budgetLines: Record<number, number>;
    expenses: Record<number, number>;
    transactions: Transaction[];
    monthlyBudget: number | null;
}>();

const months = [
    'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
    'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
];

const currentYear = ref(props.year);
const currentMonth = ref(props.month);

const budgetData = ref<Record<number, number>>({ ...props.budgetLines });
const expenseData = ref<Record<number, number>>({ ...props.expenses });
const expandedCategories = ref<Set<number>>(new Set());

// Voci con un importo previsto modificato ma non ancora salvato: vanno
// preservate quando Inertia rinfresca le props (es. dopo un movimento).
const unsavedLines = ref<Set<number>>(new Set());
const loadedMonth = ref(`${props.year}-${props.month}`);

const isAddCategoryOpen = ref(false);
const isAddSubcategoryOpen = ref(false);
const isAddMovementOpen = ref(false);
const selectedCategoryId = ref<number | null>(null);

const categoryForm = useForm({
    name: '',
    color: '#3b82f6',
    type: 'expense' as Direction,
    scope: 'month',
    year: props.year,
    month: props.month,
});

const subcategoryForm = useForm({
    name: '',
    scope: 'month',
    year: props.year,
    month: props.month,
});

const movementForm = useForm({
    year: props.year,
    month: props.month,
    budget_subcategory_id: null as number | null,
    amount: '',
    description: '',
    recorded_at: '',
});

const movementDirection = ref<Direction>('expense');
const movementCategoryId = ref<number | null>(null);
const movementDate = ref('');
const movementTime = ref('');

const predefinedColors = [
    '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
    '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#06b6d4',
];

// Salvataggio automatico degli importi previsti.
let saveTimer: ReturnType<typeof setTimeout> | undefined;
let queuedSave = false;
let pendingNavigation: (() => void) | null = null;
const savingLines = ref<number[]>([]);
const hasSavedOnce = ref(false);
const isSaving = computed(() => savingLines.value.length > 0);

const monthLabel = computed(() => `${months[currentMonth.value - 1]} ${currentYear.value}`);

// Inertia riusa il componente: al ritorno dal server gli importi vanno
// riallineati, senza però buttare via quello che si sta digitando.
watch(
    () => [props.year, props.month, props.budgetLines, props.expenses] as const,
    () => {
        const monthKey = `${props.year}-${props.month}`;
        const monthChanged = monthKey !== loadedMonth.value;

        loadedMonth.value = monthKey;
        currentYear.value = props.year;
        currentMonth.value = props.month;
        expenseData.value = { ...props.expenses };

        if (monthChanged) {
            transactionFilter.value = 'all';
            unsavedLines.value.clear();
            budgetData.value = { ...props.budgetLines };

            return;
        }

        const merged: Record<number, number> = { ...props.budgetLines };
        unsavedLines.value.forEach((id) => {
            merged[id] = budgetData.value[id] ?? 0;
        });
        budgetData.value = merged;
    },
);

const planned = (subcategoryId: number) => budgetData.value[subcategoryId] || 0;
const actual = (subcategoryId: number) => expenseData.value[subcategoryId] || 0;

const categoriesOf = (direction: Direction) =>
    props.categories.filter((category) => category.type === direction);

const sumOf = (direction: Direction, amountFor: (subcategoryId: number) => number) =>
    categoriesOf(direction)
        .flatMap((category) => category.subcategories)
        .reduce((sum, sub) => sum + amountFor(sub.id), 0);

const incomePlanned = computed(() => sumOf('income', planned));
const incomeActual = computed(() => sumOf('income', actual));
const expensePlanned = computed(() => sumOf('expense', planned));
const expenseActual = computed(() => sumOf('expense', actual));

const balanceActual = computed(() => incomeActual.value - expenseActual.value);
const balancePlanned = computed(() => incomePlanned.value - expensePlanned.value);

const sections = computed(() => [
    {
        direction: 'income' as Direction,
        title: 'Entrate',
        emptyLabel: 'Nessuna categoria di entrata per questo mese.',
        addLabel: 'Categoria di entrata',
        categories: categoriesOf('income'),
    },
    {
        direction: 'expense' as Direction,
        title: 'Uscite',
        emptyLabel: 'Nessuna categoria di uscita per questo mese.',
        addLabel: 'Categoria di uscita',
        categories: categoriesOf('expense'),
    },
]);

// Barra unica: il fondo scala sulle entrate del mese, il riempimento sono
// le uscite. Finché non è entrato nulla si usa quanto è atteso.
const budgetBase = computed(() => incomeActual.value > 0 ? incomeActual.value : incomePlanned.value);

const budgetBaseLabel = computed(() => incomeActual.value > 0 ? 'incassati' : 'attesi');

const budgetUsage = computed(() => {
    if (budgetBase.value <= 0) return expenseActual.value > 0 ? 100 : 0;

    return (expenseActual.value / budgetBase.value) * 100;
});

const toggleCategory = (categoryId: number) => {
    if (expandedCategories.value.has(categoryId)) {
        expandedCategories.value.delete(categoryId);
    } else {
        expandedCategories.value.add(categoryId);
    }
};

const today = new Date();

const yearOptions = computed(() => {
    const years = new Set<number>();

    for (let year = today.getFullYear() - 5; year <= today.getFullYear() + 5; year++) {
        years.add(year);
    }

    years.add(props.year);

    return [...years].sort((a, b) => a - b);
});

const isToday = (year: number, month: number) =>
    today.getFullYear() === year && today.getMonth() + 1 === month;

const monthRow = ref<HTMLElement | null>(null);

const scrollActiveMonthIntoView = () => {
    monthRow.value
        ?.querySelector('[data-active="true"]')
        ?.scrollIntoView({ inline: 'center', block: 'nearest' });
};

onMounted(scrollActiveMonthIntoView);
watch(() => [props.year, props.month], () => nextTick(scrollActiveMonthIntoView));

const selectedYear = computed({
    get: () => currentYear.value,
    set: (year: number) => goToMonth(year, currentMonth.value),
});

const goToMonth = (year: number, month: number) => {
    if (year === currentYear.value && month === currentMonth.value) return;

    currentYear.value = year;
    currentMonth.value = month;
    navigateToMonth();
};

const navigateToMonth = () => {
    const go = () => router.get(monthlyBudgets.index.url({
        query: {
            year: currentYear.value,
            month: currentMonth.value,
        }
    }));

    // Un salvataggio in volo verrebbe annullato dal cambio pagina.
    flushSave();

    if (isSaving.value || unsavedLines.value.size > 0) {
        pendingNavigation = go;

        return;
    }

    go();
};

const runPendingNavigation = () => {
    if (!pendingNavigation || isSaving.value) return;

    const go = pendingNavigation;
    pendingNavigation = null;
    go();
};

function flushSave() {
    clearTimeout(saveTimer);

    if (isSaving.value) {
        queuedSave = true;

        return;
    }

    const ids = [...unsavedLines.value];

    if (ids.length === 0) {
        runPendingNavigation();

        return;
    }

    savingLines.value = ids;

    router.post(
        monthlyBudgets.store.url(),
        {
            // Il mese di riferimento è quello caricato, non quello che
            // l'utente sta per aprire con le pillole.
            year: props.year,
            month: props.month,
            budget_lines: ids.map((id) => ({
                subcategory_id: id,
                planned_amount: budgetData.value[id] ?? 0,
            })),
        },
        {
            preserveScroll: true,
            only: ['budgetLines', 'expenses', 'transactions', 'monthlyBudget'],
            onSuccess: () => {
                ids.forEach((id) => unsavedLines.value.delete(id));
                hasSavedOnce.value = true;
            },
            onFinish: () => {
                savingLines.value = [];

                if (queuedSave) {
                    queuedSave = false;
                    flushSave();

                    return;
                }

                runPendingNavigation();
            },
        },
    );
}

const updateBudgetLine = (subcategoryId: number, amount: string) => {
    // Accetta solo numeri e punto
    const sanitized = amount.replace(',', '.').replace(/[^\d.]/g, '');
    const numAmount = parseFloat(sanitized) || 0;

    budgetData.value[subcategoryId] = numAmount;
    unsavedLines.value.add(subcategoryId);

    clearTimeout(saveTimer);
    saveTimer = setTimeout(flushSave, 900);
};

const saveStatus = computed(() => {
    if (isSaving.value) return 'saving';
    if (unsavedLines.value.size > 0) return 'pending';

    return hasSavedOnce.value ? 'saved' : 'idle';
});

const saveStatusLabel = computed(() => ({
    saving: 'Salvataggio…',
    pending: 'Modifiche in attesa',
    saved: 'Salvato',
    idle: '',
}[saveStatus.value]));

const openAddCategory = (direction: Direction) => {
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryForm.type = direction;
    isAddCategoryOpen.value = true;
};

const submitCategory = () => {
    categoryForm.year = currentYear.value;
    categoryForm.month = currentMonth.value;

    categoryForm.post(budgetCategories.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            isAddCategoryOpen.value = false;
            categoryForm.reset();
        },
    });
};

const selectedCategory = computed(() =>
    props.categories.find((category) => category.id === selectedCategoryId.value) ?? null);

const openAddSubcategory = (categoryId: number) => {
    selectedCategoryId.value = categoryId;
    subcategoryForm.reset();
    subcategoryForm.clearErrors();
    isAddSubcategoryOpen.value = true;
};

const submitSubcategory = () => {
    if (!selectedCategoryId.value) return;

    subcategoryForm.year = currentYear.value;
    subcategoryForm.month = currentMonth.value;

    subcategoryForm.post(budgetCategories.subcategories.store.url(selectedCategoryId.value), {
        preserveScroll: true,
        onSuccess: () => {
            isAddSubcategoryOpen.value = false;
            subcategoryForm.reset();
        },
    });
};

const scopeLabel = (monthlyBudgetId: number | null) =>
    monthlyBudgetId ? `solo da ${monthLabel.value}` : 'da tutti i mesi';

const deleteCategory = (category: Category) => {
    const confirmed = confirm(
        `Eliminare "${category.name}" e le sue voci ${scopeLabel(category.monthly_budget_id)}? `
        + 'Verranno persi anche gli importi previsti e i movimenti collegati.',
    );

    if (!confirmed) return;

    router.delete(budgetCategories.destroy.url(category.id), { preserveScroll: true });
};

const deleteSubcategory = (category: Category, subcategoryId: number) => {
    const subcategory = category.subcategories.find((sub) => sub.id === subcategoryId);

    if (!subcategory) return;

    const confirmed = confirm(
        `Eliminare "${subcategory.name}" ${scopeLabel(subcategory.monthly_budget_id)}? `
        + 'Verranno persi anche gli importi previsti e i movimenti collegati.',
    );

    if (!confirmed) return;

    router.delete(budgetSubcategories.destroy.url(subcategoryId), { preserveScroll: true });
};

const movementCategories = computed(() => categoriesOf(movementDirection.value));

const movementCategory = computed(() =>
    props.categories.find((category) => category.id === movementCategoryId.value) ?? null);

const movementSubcategory = computed(() => {
    if (!movementForm.budget_subcategory_id) return null;

    return props.categories
        .flatMap((category) => category.subcategories)
        .find((sub) => sub.id === movementForm.budget_subcategory_id) ?? null;
});

const canSubmitMovement = computed(() =>
    Boolean(movementForm.budget_subcategory_id) && movementForm.amount.trim() !== '');

const setMovementDirection = (direction: Direction) => {
    if (direction === movementDirection.value) return;

    movementDirection.value = direction;
    movementCategoryId.value = null;
    movementForm.budget_subcategory_id = null;
};

watch(movementCategoryId, (categoryId, previous) => {
    if (previous !== null && categoryId !== previous) {
        movementForm.budget_subcategory_id = null;
    }
});

const openAddMovement = () => {
    movementForm.reset();
    movementForm.clearErrors();
    movementCategoryId.value = null;
    movementDirection.value = 'expense';

    // Se sto guardando un altro mese il movimento nasce dentro quel mese.
    const now = new Date();
    const isCurrentMonth =
        now.getFullYear() === currentYear.value && now.getMonth() + 1 === currentMonth.value;
    const day = isCurrentMonth ? now : new Date(currentYear.value, currentMonth.value - 1, 1);

    movementDate.value = [
        day.getFullYear(),
        String(day.getMonth() + 1).padStart(2, '0'),
        String(day.getDate()).padStart(2, '0'),
    ].join('-');
    movementTime.value = isCurrentMonth ? now.toTimeString().slice(0, 5) : '12:00';

    isAddMovementOpen.value = true;
};

const submitMovement = () => {
    movementForm.year = currentYear.value;
    movementForm.month = currentMonth.value;
    movementForm.amount = movementForm.amount.replace(',', '.');
    movementForm.recorded_at = `${movementDate.value} ${movementTime.value}`;

    movementForm.post(budgetExpenses.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            isAddMovementOpen.value = false;
            movementForm.reset();
            movementCategoryId.value = null;
        },
    });
};

const deleteTransaction = (transaction: Transaction) => {
    if (!confirm(`Eliminare il movimento di ${formatCurrency(transaction.amount)}?`)) return;

    router.delete(budgetExpenses.destroy.url(transaction.id), { preserveScroll: true });
};

const transactionFilter = ref<number | 'all'>('all');

// Solo le categorie che compaiono davvero nei movimenti del mese.
const transactionCategories = computed(() => {
    const seen = new Map<number, { id: number; name: string; color: string }>();

    props.transactions.forEach((transaction) => {
        if (transaction.category_id === null || seen.has(transaction.category_id)) return;

        seen.set(transaction.category_id, {
            id: transaction.category_id,
            name: transaction.category_name ?? '—',
            color: transaction.category_color ?? '#3b82f6',
        });
    });

    return [...seen.values()].sort((a, b) => a.name.localeCompare(b.name, 'it'));
});

const filteredTransactions = computed(() => transactionFilter.value === 'all'
    ? props.transactions
    : props.transactions.filter((transaction) => transaction.category_id === transactionFilter.value));

const signedAmount = (transaction: Transaction) =>
    transaction.direction === 'income' ? transaction.amount : -transaction.amount;

const dayFormatter = new Intl.DateTimeFormat('it-IT', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

const dayLabel = (date: Date) => {
    const now = new Date();
    const yesterday = new Date();
    yesterday.setDate(now.getDate() - 1);

    const sameDay = (a: Date, b: Date) => a.toDateString() === b.toDateString();

    if (sameDay(date, now)) return 'Oggi';
    if (sameDay(date, yesterday)) return 'Ieri';

    return dayFormatter.format(date);
};

const timeLabel = (isoDate: string | null) =>
    isoDate ? new Date(isoDate).toTimeString().slice(0, 5) : '—';

const filteredTotal = computed(() =>
    filteredTransactions.value.reduce((sum, transaction) => sum + signedAmount(transaction), 0));

const transactionDays = computed(() => {
    const groups = new Map<string, { label: string; total: number; items: Transaction[] }>();

    filteredTransactions.value.forEach((transaction) => {
        const date = transaction.recorded_at ? new Date(transaction.recorded_at) : new Date();
        const key = date.toDateString();

        if (!groups.has(key)) {
            groups.set(key, { label: dayLabel(date), total: 0, items: [] });
        }

        const group = groups.get(key)!;
        group.total += signedAmount(transaction);
        group.items.push(transaction);
    });

    return Array.from(groups.values());
});
</script>

<template>
    <Head title="Budget Mensile" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4 pb-28">
        <Heading
            title="Budget Mensile"
            description="Prevedi entrate e uscite, registra i movimenti e guarda cosa resta"
        />

        <!-- Mese e anno -->
        <div>
            <div ref="monthRow" class="flex items-center gap-2 overflow-x-auto pb-1">
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
                    v-for="(label, index) in months"
                    :key="label"
                    :data-active="index + 1 === currentMonth"
                    class="shrink-0 whitespace-nowrap rounded-full px-4 py-2 text-sm transition-colors"
                    :class="[
                        index + 1 === currentMonth
                            ? 'bg-primary font-medium text-primary-foreground'
                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground',
                        isToday(currentYear, index + 1) && index + 1 !== currentMonth
                            ? 'ring-1 ring-primary/40'
                            : '',
                    ]"
                    :aria-current="index + 1 === currentMonth ? 'true' : undefined"
                    @click="goToMonth(currentYear, index + 1)"
                >
                    {{ label }}
                </button>
            </div>
        </div>

        <!-- Riepilogo del mese -->
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="rounded-lg bg-muted/50 p-3">
                    <p class="text-xs text-muted-foreground">Entrate</p>
                    <p class="text-lg font-bold">{{ formatCurrency(incomeActual) }}</p>
                    <p class="text-xs text-muted-foreground">incassate</p>
                    <div class="mt-2 flex items-baseline justify-between gap-2 border-t pt-2 text-xs">
                        <span class="text-muted-foreground">Attese</span>
                        <span class="font-medium tabular-nums">{{ formatCurrency(incomePlanned) }}</span>
                    </div>
                </div>
                <div class="rounded-lg bg-muted/50 p-3">
                    <p class="text-xs text-muted-foreground">Uscite</p>
                    <p class="text-lg font-bold">{{ formatCurrency(expenseActual) }}</p>
                    <p class="text-xs text-muted-foreground">spese</p>
                    <div class="mt-2 flex items-baseline justify-between gap-2 border-t pt-2 text-xs">
                        <span class="text-muted-foreground">Attese</span>
                        <span class="font-medium tabular-nums">{{ formatCurrency(expensePlanned) }}</span>
                    </div>
                </div>
                <div class="col-span-2 rounded-lg bg-muted/50 p-3 sm:col-span-1">
                    <p class="text-xs text-muted-foreground">Saldo del mese</p>
                    <p
                        class="text-lg font-bold"
                        :class="balanceActual >= 0 ? 'text-green-600' : 'text-red-600'"
                    >
                        {{ formatCurrency(balanceActual) }}
                    </p>
                    <p class="text-xs text-muted-foreground">effettivo</p>
                    <div class="mt-2 flex items-baseline justify-between gap-2 border-t pt-2 text-xs">
                        <span class="text-muted-foreground">Previsto</span>
                        <span
                            class="font-medium tabular-nums"
                            :class="balancePlanned >= 0 ? '' : 'text-red-600'"
                        >
                            {{ formatCurrency(balancePlanned) }}
                        </span>
                    </div>
                </div>
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">Uscite sulle entrate</span>
                    <span class="text-sm font-semibold">{{ budgetUsage.toFixed(0) }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full transition-all"
                        :style="{
                            width: `${Math.min(budgetUsage, 100)}%`,
                            backgroundColor: budgetUsage > 100 ? '#ef4444' : '#10b981',
                        }"
                    />
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ formatCurrency(expenseActual) }} di uscite su
                    {{ formatCurrency(budgetBase) }} {{ budgetBaseLabel }}
                </p>
            </div>

            <BudgetExpenseSummary
                :categories="categoriesOf('expense')"
                :planned="budgetData"
                :actual="expenseData"
            />

            <p
                v-if="saveStatus !== 'idle'"
                class="flex items-center justify-center gap-2 text-xs text-muted-foreground"
            >
                <Spinner v-if="saveStatus === 'saving'" class="size-3" />
                <Check v-else-if="saveStatus === 'saved'" class="size-3 text-green-600" />
                {{ saveStatusLabel }}
            </p>
        </div>

        <!-- Entrate e uscite -->
        <section v-for="section in sections" :key="section.direction" class="space-y-3">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold">{{ section.title }}</h2>
                <Button variant="outline" size="sm" @click="openAddCategory(section.direction)">
                    <Plus class="mr-2 size-4" />
                    {{ section.addLabel }}
                </Button>
            </div>

            <div v-if="section.categories.length" class="space-y-2">
                <BudgetCategoryCard
                    v-for="category in section.categories"
                    :key="category.id"
                    :category="category"
                    :planned="budgetData"
                    :actual="expenseData"
                    :expanded="expandedCategories.has(category.id)"
                    @toggle="toggleCategory(category.id)"
                    @update="updateBudgetLine"
                    @flush="flushSave()"
                    @add-subcategory="openAddSubcategory(category.id)"
                    @delete-category="deleteCategory(category)"
                    @delete-subcategory="deleteSubcategory(category, $event)"
                />
            </div>

            <div v-else class="rounded-xl border border-dashed bg-muted/30 p-6 text-center">
                <p class="text-sm text-muted-foreground">{{ section.emptyLabel }}</p>
            </div>
        </section>

        <!-- Movimenti del mese -->
        <div class="rounded-lg border bg-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                <div>
                    <h3 class="font-semibold">Movimenti</h3>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="transactionFilter === 'all'">
                            {{ transactions.length }} registrati a {{ monthLabel.toLowerCase() }}
                        </template>
                        <template v-else>
                            {{ filteredTransactions.length }} di {{ transactions.length }} a
                            {{ monthLabel.toLowerCase() }}
                        </template>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Select v-if="transactionCategories.length" v-model="transactionFilter">
                        <SelectTrigger class="h-8 w-44 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tutte le categorie</SelectItem>
                            <SelectItem
                                v-for="category in transactionCategories"
                                :key="category.id"
                                :value="category.id"
                            >
                                <span class="flex items-center gap-2">
                                    <span
                                        class="size-2.5 shrink-0 rounded-full"
                                        :style="{ backgroundColor: category.color }"
                                    />
                                    {{ category.name }}
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <p
                        class="text-sm font-semibold"
                        :class="filteredTotal >= 0 ? 'text-green-600' : 'text-red-600'"
                    >
                        {{ formatCurrency(filteredTotal) }}
                    </p>
                </div>
            </div>

            <div v-if="transactionDays.length" class="divide-y">
                <div v-for="day in transactionDays" :key="day.label">
                    <div class="flex items-center justify-between gap-4 bg-muted/40 px-5 py-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            {{ day.label }}
                        </span>
                        <span class="text-xs text-muted-foreground">{{ formatCurrency(day.total) }}</span>
                    </div>

                    <div
                        v-for="transaction in day.items"
                        :key="transaction.id"
                        class="group flex items-center gap-3 px-5 py-3 hover:bg-muted/30"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full"
                            :style="{ backgroundColor: `${transaction.category_color ?? '#3b82f6'}20` }"
                        >
                            <ArrowDownLeft
                                v-if="transaction.direction === 'income'"
                                class="size-4 text-green-600"
                            />
                            <ArrowUpRight
                                v-else
                                class="size-4"
                                :style="{ color: transaction.category_color ?? '#3b82f6' }"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ transaction.description || transaction.subcategory_name }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ transaction.category_name }} · {{ transaction.subcategory_name }} ·
                                {{ timeLabel(transaction.recorded_at) }}
                            </p>
                        </div>

                        <p
                            class="shrink-0 text-sm font-semibold"
                            :class="transaction.direction === 'income' ? 'text-green-600' : ''"
                        >
                            {{ transaction.direction === 'income' ? '+' : '' }}{{ formatCurrency(signedAmount(transaction)) }}
                        </p>

                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="shrink-0 text-muted-foreground opacity-60 transition-opacity hover:bg-destructive/10 hover:text-destructive focus-visible:opacity-100 group-hover:opacity-100"
                            :title="`Elimina il movimento di ${formatCurrency(transaction.amount)}`"
                            @click="deleteTransaction(transaction)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <div v-else class="px-5 py-10 text-center">
                <template v-if="transactionFilter === 'all'">
                    <p class="text-sm text-muted-foreground">
                        Nessun movimento registrato questo mese.
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Usa il pulsante in basso a destra per aggiungerne uno.
                    </p>
                </template>
                <template v-else>
                    <p class="text-sm text-muted-foreground">
                        Nessun movimento in questa categoria.
                    </p>
                    <Button variant="link" size="sm" @click="transactionFilter = 'all'">
                        Mostra tutte le categorie
                    </Button>
                </template>
            </div>
        </div>
    </div>

    <!-- Bottone fisso: nuovo movimento -->
    <Button
        class="fixed bottom-[calc(5.5rem_+_env(safe-area-inset-bottom))] right-4 z-40 h-14 gap-3 rounded-2xl px-6 text-base font-semibold shadow-lg md:bottom-6 md:right-6"
        @click="openAddMovement()"
    >
        Nuovo movimento
        <Plus class="size-5" />
    </Button>

    <!-- Sheet: Aggiungi Categoria -->
    <Sheet v-model:open="isAddCategoryOpen">
        <SheetContent>
            <SheetHeader>
                <SheetTitle>
                    {{ categoryForm.type === 'income' ? 'Entrata' : 'Uscita' }} solo per {{ monthLabel }}
                </SheetTitle>
            </SheetHeader>

            <div class="mt-6 space-y-4">
                <div class="grid gap-2">
                    <Label for="category-name">Nome</Label>
                    <Input
                        id="category-name"
                        v-model="categoryForm.name"
                        :placeholder="categoryForm.type === 'income' ? 'Es. Stipendio' : 'Es. Bollette'"
                        autofocus
                    />
                    <p v-if="categoryForm.errors.name" class="text-sm text-destructive">
                        {{ categoryForm.errors.name }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label>Colore</Label>
                    <div class="grid grid-cols-5 gap-2">
                        <button
                            v-for="color in predefinedColors"
                            :key="color"
                            class="rounded border-2 transition-transform"
                            :class="{
                                'border-foreground scale-110': categoryForm.color === color,
                                'border-transparent': categoryForm.color !== color,
                            }"
                            :style="{ backgroundColor: color }"
                            style="height: 32px"
                            @click="categoryForm.color = color"
                        />
                    </div>
                </div>

                <Button class="mt-6 w-full" :disabled="categoryForm.processing" @click="submitCategory">
                    Crea Categoria
                </Button>
            </div>
        </SheetContent>
    </Sheet>

    <!-- Dialog: Aggiungi Sottocategoria -->
    <Dialog v-model:open="isAddSubcategoryOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ selectedCategory?.type === 'income' ? 'Voce' : 'Sottocategoria' }}
                    solo per {{ monthLabel }}
                </DialogTitle>
            </DialogHeader>

            <div class="mt-6 space-y-4">
                <div class="grid gap-2">
                    <Label for="subcategory-name">Nome</Label>
                    <Input
                        id="subcategory-name"
                        v-model="subcategoryForm.name"
                        :placeholder="selectedCategory?.type === 'income' ? 'Es. tredicesima' : 'Es. enel energia'"
                        autofocus
                    />
                    <p v-if="subcategoryForm.errors.name" class="text-sm text-destructive">
                        {{ subcategoryForm.errors.name }}
                    </p>
                </div>

                <Button
                    class="mt-6 w-full"
                    :disabled="subcategoryForm.processing"
                    @click="submitSubcategory"
                >
                    Crea
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Dialog: Nuovo movimento -->
    <Dialog v-model:open="isAddMovementOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nuovo movimento · {{ monthLabel }}</DialogTitle>
            </DialogHeader>

            <div class="mt-6 space-y-4">
                <div class="grid grid-cols-2 gap-2 rounded-lg bg-muted/50 p-1">
                    <button
                        class="rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="movementDirection === 'expense'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="setMovementDirection('expense')"
                    >
                        Uscita
                    </button>
                    <button
                        class="rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="movementDirection === 'income'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="setMovementDirection('income')"
                    >
                        Entrata
                    </button>
                </div>

                <div class="grid gap-2">
                    <Label for="movement-category">Categoria</Label>
                    <Select v-model="movementCategoryId">
                        <SelectTrigger id="movement-category" class="w-full">
                            <SelectValue placeholder="Seleziona una categoria" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="category in movementCategories"
                                :key="category.id"
                                :value="category.id"
                            >
                                <span class="flex items-center gap-2">
                                    <span
                                        class="size-3 rounded"
                                        :style="{ backgroundColor: category.color }"
                                    />
                                    {{ category.name }}
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="movementCategories.length === 0" class="text-xs text-muted-foreground">
                        Nessuna categoria di
                        {{ movementDirection === 'income' ? 'entrata' : 'uscita' }}: creane una dalla sezione qui sopra.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="movement-subcategory">Voce</Label>
                    <Select v-model="movementForm.budget_subcategory_id" :disabled="!movementCategory">
                        <SelectTrigger id="movement-subcategory" class="w-full">
                            <SelectValue
                                :placeholder="movementCategory ? 'Seleziona una voce' : 'Scegli prima la categoria'"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="sub in movementCategory?.subcategories ?? []"
                                :key="sub.id"
                                :value="sub.id"
                            >
                                {{ sub.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="movementForm.errors.budget_subcategory_id" class="text-sm text-destructive">
                        {{ movementForm.errors.budget_subcategory_id }}
                    </p>
                </div>

                <p v-if="movementSubcategory" class="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                    <template v-if="movementDirection === 'income'">
                        Atteso {{ formatCurrency(planned(movementSubcategory.id)) }} · già incassato
                        {{ formatCurrency(actual(movementSubcategory.id)) }}
                    </template>
                    <template v-else>
                        Costo atteso {{ formatCurrency(planned(movementSubcategory.id)) }} · già speso
                        {{ formatCurrency(actual(movementSubcategory.id)) }} · rimane
                        <span
                            :class="planned(movementSubcategory.id) - actual(movementSubcategory.id) >= 0
                                ? 'text-green-600'
                                : 'text-red-600'"
                        >
                            {{ formatCurrency(planned(movementSubcategory.id) - actual(movementSubcategory.id)) }}
                        </span>
                    </template>
                </p>

                <div class="grid gap-2">
                    <Label for="movement-amount">Importo</Label>
                    <Input
                        id="movement-amount"
                        v-model="movementForm.amount"
                        inputmode="decimal"
                        placeholder="0.00"
                    />
                    <p v-if="movementForm.errors.amount" class="text-sm text-destructive">
                        {{ movementForm.errors.amount }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="movement-description">Descrizione</Label>
                    <Input
                        id="movement-description"
                        v-model="movementForm.description"
                        placeholder="Facoltativa"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label for="movement-date">Data</Label>
                        <Input id="movement-date" v-model="movementDate" type="date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="movement-time">Ora</Label>
                        <Input id="movement-time" v-model="movementTime" type="time" />
                    </div>
                </div>

                <p v-if="movementForm.errors.recorded_at" class="text-sm text-destructive">
                    {{ movementForm.errors.recorded_at }}
                </p>

                <Button
                    class="mt-6 w-full"
                    :disabled="movementForm.processing || !canSubmitMovement"
                    @click="submitMovement"
                >
                    Registra movimento
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
