<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, ChevronDown, EllipsisVertical, FileDown, Pencil, PiggyBank, Plus, Settings, Trash2, Users } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import monthlyBudgets from '@/routes/monthly-budgets';
import budgetCategories from '@/routes/budget-categories';
import budgetSubcategories from '@/routes/budget-subcategories';
import budgetExpenses from '@/routes/budget-expenses';
import BudgetCategoryRow from '@/components/budget/BudgetCategoryRow.vue';
import BudgetSummaryRing from '@/components/budget/BudgetSummaryRing.vue';
import BudgetExpenseSummary from '@/components/budget/BudgetExpenseSummary.vue';
import BudgetCategoryManager from '@/components/budget/BudgetCategoryManager.vue';
import BudgetColorPicker from '@/components/budget/BudgetColorPicker.vue';
import SharedWith from '@/components/SharedWith.vue';
import type { SharedPerson } from '@/types/sharing';
import MonthlyBudgetController from '@/actions/App/Http/Controllers/Budget/MonthlyBudgetController';
import { formatAmount, formatCurrency } from '@/lib/balance-sheet-format';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

/** Un budget nel selettore: è identificato dal suo proprietario. */
interface BudgetOption {
    id: number;
    name: string;
    is_shared: boolean;
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
    budgets: BudgetOption[];
    budget: {
        id: number;
        name: string;
        is_owner: boolean;
        people: SharedPerson[];
    };
}>();

const page = usePage();

// Il budget condiviso viaggia in query string sulle letture e nel payload
// sulle scritture: senza, ogni azione tornerebbe a scrivere sul proprio.
const sharedBudgetQuery = computed(() =>
    props.budget.is_owner ? {} : { budget: props.budget.id });

const budgetOwnerId = computed(() => props.budget.is_owner ? null : props.budget.id);

const budgetTitle = computed(() =>
    props.budget.is_owner ? 'Budget' : `Budget di ${props.budget.name}`);

const goToBudget = (option: BudgetOption) => {
    router.get(monthlyBudgets.index.url({
        query: {
            year: currentYear.value,
            month: currentMonth.value,
            ...(option.is_shared ? { budget: option.id } : {}),
        },
    }));
};

const removeBudgetMember = (person: SharedPerson) => {
    if (!confirm(`Rimuovere ${person.name} dal tuo budget? Non lo vedrà più.`)) return;

    router.delete(MonthlyBudgetController.destroyMember([props.budget.id, person.id]).url, {
        preserveScroll: true,
    });
};

const leaveBudget = (person: SharedPerson) => {
    if (!confirm(`Uscire dal budget di ${props.budget.name}? Non lo vedrai più finché non ti reinvitano.`)) return;

    router.delete(MonthlyBudgetController.destroyMember([props.budget.id, person.id]).url);
};

const months = [
    'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
    'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
];

const currentYear = ref(props.year);
const currentMonth = ref(props.month);

const budgetData = ref<Record<number, number>>({ ...props.budgetLines });
const expenseData = ref<Record<number, number>>({ ...props.expenses });
// Una categoria aperta alla volta: aprirne un'altra chiude la precedente.
const expandedCategoryId = ref<number | null>(null);

// Voci con un importo previsto modificato ma non ancora salvato: vanno
// preservate quando Inertia rinfresca le props (es. dopo un movimento).
const unsavedLines = ref<Set<number>>(new Set());
const loadedMonth = ref(`${props.year}-${props.month}`);

const isAddCategoryOpen = ref(false);
const isCategoryManagerOpen = ref(false);
// Tengo l'id e non l'oggetto: dopo un salvataggio le props arrivano nuove.
const managedCategoryId = ref<number | null>(null);
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
    budget_user_id: null as number | null,
});

const subcategoryForm = useForm({
    name: '',
    scope: 'month',
    year: props.year,
    month: props.month,
});

const movementForm = useForm({
    budget_user_id: null as number | null,
    year: props.year,
    month: props.month,
    budget_subcategory_id: null as number | null,
    amount: '',
    description: '',
    recorded_at: '',
});

// Il dialog dei movimenti serve sia a crearne uno sia a modificarlo.
const editingTransaction = ref<Transaction | null>(null);
// Creazione al volo di una voce mancante, senza uscire dal dialog.
const isAddingMovementSubcategory = ref(false);
const quickSubcategoryForm = useForm({
    name: '',
    scope: 'month',
    year: props.year,
    month: props.month,
});

const movementDirection = ref<Direction>('expense');
const movementCategoryId = ref<number | null>(null);
const movementDate = ref('');
const movementTime = ref('');

// Salvataggio automatico degli importi previsti.
let saveTimer: ReturnType<typeof setTimeout> | undefined;
let queuedSave = false;
let pendingNavigation: (() => void) | null = null;
const savingLines = ref<number[]>([]);
const isSaving = computed(() => savingLines.value.length > 0);

const monthName = computed(() => months[currentMonth.value - 1]);

const monthLabel = computed(() => `${monthName.value} ${currentYear.value}`);

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
        totalLabel: 'Totale da incassare',
        emptyLabel: 'Nessuna categoria di entrata per questo mese.',
        addLabel: 'Categoria di entrata',
        categories: categoriesOf('income'),
        planned: incomePlanned.value,
        actual: incomeActual.value,
    },
    {
        direction: 'expense' as Direction,
        title: 'Budget',
        totalLabel: 'Totale residuo',
        emptyLabel: 'Nessuna categoria di uscita per questo mese.',
        addLabel: 'Categoria di uscita',
        categories: categoriesOf('expense'),
        planned: expensePlanned.value,
        actual: expenseActual.value,
    },
]);

// Come nelle righe: sulle entrate un residuo negativo è un incasso in più,
// sulle uscite è invece uno sforamento.
const residualLabel = (direction: Direction, residual: number) =>
    direction === 'income' && residual < 0 ? `+${formatAmount(-residual)}` : formatAmount(residual);

const isOverspent = (direction: Direction, residual: number) =>
    direction === 'expense' && residual < 0;

// Barra unica: il fondo scala sulle entrate del mese, il riempimento sono
// le uscite. Finché non è entrato nulla si usa quanto è atteso.
const budgetBase = computed(() => incomeActual.value > 0 ? incomeActual.value : incomePlanned.value);

const budgetBaseLabel = computed(() => incomeActual.value > 0 ? 'incassati' : 'attesi');

const budgetUsage = computed(() => {
    if (budgetBase.value <= 0) return expenseActual.value > 0 ? 100 : 0;

    return (expenseActual.value / budgetBase.value) * 100;
});

const toggleCategory = (categoryId: number) => {
    expandedCategoryId.value = expandedCategoryId.value === categoryId ? null : categoryId;
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

const isViewingToday = computed(() => isToday(currentYear.value, currentMonth.value));

const goToToday = () => goToMonth(today.getFullYear(), today.getMonth() + 1);

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
            ...sharedBudgetQuery.value,
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
            budget_user_id: budgetOwnerId.value,
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

const isDownloadingPdf = ref(false);

const pdfUrl = () => monthlyBudgets.pdf.url({
    query: {
        year: currentYear.value,
        month: currentMonth.value,
        ...sharedBudgetQuery.value,
    },
});

// In app installata una <a href> al PDF sostituisce la pagina e non si torna
// più indietro: lo scarico come blob e lascio l'app dov'è.
const downloadPdf = async () => {
    if (isDownloadingPdf.value) return;

    isDownloadingPdf.value = true;

    try {
        const response = await fetch(pdfUrl(), { credentials: 'same-origin' });

        if (!response.ok) throw new Error(String(response.status));

        const objectUrl = URL.createObjectURL(await response.blob());
        const link = document.createElement('a');

        link.href = objectUrl;
        link.download = `budget-${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();

        // Safari annulla il download se l'URL viene revocato subito.
        setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
    } catch {
        window.open(pdfUrl(), '_blank', 'noopener');
    } finally {
        isDownloadingPdf.value = false;
    }
};

const openAddCategory = (direction: Direction) => {
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryForm.type = direction;
    isAddCategoryOpen.value = true;
};

const submitCategory = () => {
    categoryForm.year = currentYear.value;
    categoryForm.month = currentMonth.value;
    categoryForm.budget_user_id = budgetOwnerId.value;

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

const managedCategory = computed(() =>
    props.categories.find((category) => category.id === managedCategoryId.value) ?? null);

const managedTransactions = computed(() =>
    props.transactions.filter((transaction) => transaction.category_id === managedCategoryId.value));

const openCategoryManager = (category: Category) => {
    managedCategoryId.value = category.id;
    isCategoryManagerOpen.value = true;
};

// Due pannelli modali sovrapposti si contendono il focus: chiudo il gestore
// prima di aprire quello che parte da dentro.
const deleteCategoryFromManager = () => {
    if (!managedCategory.value) return;

    isCategoryManagerOpen.value = false;
    deleteCategory(managedCategory.value);
};

const addSubcategoryFromManager = () => {
    if (!managedCategoryId.value) return;

    const categoryId = managedCategoryId.value;
    isCategoryManagerOpen.value = false;
    openAddSubcategory(categoryId);
};

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

// Da qui si cancella solo quello che è nato in questo mese: le categorie e le
// voci valide per tutti i mesi si gestiscono dalla pagina Categorie.
const deleteCategory = (category: Category) => {
    if (!category.monthly_budget_id) return;

    const confirmed = confirm(
        `Eliminare "${category.name}" e le sue voci solo da ${monthLabel.value}? `
        + 'Verranno persi anche gli importi attesi e i movimenti collegati.',
    );

    if (!confirmed) return;

    router.delete(budgetCategories.destroy.url(category.id), { preserveScroll: true });
};

const deleteSubcategory = (category: Category, subcategoryId: number) => {
    const subcategory = category.subcategories.find((sub) => sub.id === subcategoryId);

    if (!subcategory?.monthly_budget_id) return;

    const confirmed = confirm(
        `Eliminare "${subcategory.name}" solo da ${monthLabel.value}? `
        + 'Verranno persi anche gli importi attesi e i movimenti collegati.',
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

watch(movementCategoryId, (categoryId) => {
    const stillValid = props.categories
        .find((category) => category.id === categoryId)
        ?.subcategories
        .some((sub) => sub.id === movementForm.budget_subcategory_id);

    if (!stillValid) {
        movementForm.budget_subcategory_id = null;
    }
});

const setMovementDate = (date: Date) => {
    movementDate.value = [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
    movementTime.value = date.toTimeString().slice(0, 5);
};

const resetQuickSubcategory = () => {
    isAddingMovementSubcategory.value = false;
    quickSubcategoryForm.reset();
    quickSubcategoryForm.clearErrors();
};

const createMovementSubcategory = () => {
    const categoryId = movementCategoryId.value;
    const name = quickSubcategoryForm.name.trim();

    if (!categoryId || name === '') return;

    quickSubcategoryForm.year = currentYear.value;
    quickSubcategoryForm.month = currentMonth.value;

    quickSubcategoryForm.post(budgetCategories.subcategories.store.url(categoryId), {
        preserveScroll: true,
        preserveState: true,
        only: ['categories'],
        onSuccess: () => {
            // La voce appena creata viene selezionata: è quella che serviva.
            const created = props.categories
                .find((category) => category.id === categoryId)
                ?.subcategories.find((sub) => sub.name === name);

            if (created) {
                movementForm.budget_subcategory_id = created.id;
            }

            resetQuickSubcategory();
        },
    });
};

const openEditMovement = (transaction: Transaction) => {
    editingTransaction.value = transaction;
    resetQuickSubcategory();
    movementForm.reset();
    movementForm.clearErrors();

    movementDirection.value = transaction.direction;
    movementCategoryId.value = transaction.category_id;
    movementForm.budget_subcategory_id = transaction.subcategory_id;
    movementForm.amount = String(transaction.amount);
    movementForm.description = transaction.description ?? '';
    setMovementDate(transaction.recorded_at ? new Date(transaction.recorded_at) : new Date());

    isAddMovementOpen.value = true;
};

const openAddMovement = () => {
    editingTransaction.value = null;
    resetQuickSubcategory();
    movementForm.reset();
    movementForm.clearErrors();
    movementCategoryId.value = null;
    movementDirection.value = 'expense';

    // Se sto guardando un altro mese il movimento nasce dentro quel mese.
    const now = new Date();
    const isCurrentMonth =
        now.getFullYear() === currentYear.value && now.getMonth() + 1 === currentMonth.value;
    const day = isCurrentMonth ? now : new Date(currentYear.value, currentMonth.value - 1, 1);

    setMovementDate(day);

    if (!isCurrentMonth) movementTime.value = '12:00';

    isAddMovementOpen.value = true;
};

const submitMovement = () => {
    movementForm.budget_user_id = budgetOwnerId.value;
    movementForm.year = currentYear.value;
    movementForm.month = currentMonth.value;
    movementForm.amount = movementForm.amount.replace(',', '.');
    movementForm.recorded_at = `${movementDate.value} ${movementTime.value}`;

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isAddMovementOpen.value = false;
            editingTransaction.value = null;
            movementForm.reset();
            movementCategoryId.value = null;
        },
    };

    if (editingTransaction.value) {
        movementForm.put(budgetExpenses.update.url(editingTransaction.value.id), options);

        return;
    }

    movementForm.post(budgetExpenses.store.url(), options);
};

const deleteTransaction = (transaction: Transaction) => {
    if (!confirm(`Eliminare il movimento di ${formatCurrency(transaction.amount)}?`)) return;

    router.delete(budgetExpenses.destroy.url(transaction.id), { preserveScroll: true });
};

const transactionFilter = ref<number | 'all'>('all');
const isTransactionsOpen = ref(true);

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
    <Head title="Budget" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4 pb-28">
        <div class="flex items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <DropdownMenu v-if="budgets.length > 1">
                    <DropdownMenuTrigger as-child>
                        <button
                            class="flex min-w-0 items-center gap-1 text-xl font-semibold tracking-tight"
                            title="Cambia budget"
                        >
                            <span class="truncate">{{ budgetTitle }}</span>
                            <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem
                            v-for="option in budgets"
                            :key="option.id"
                            @click="goToBudget(option)"
                        >
                            <component :is="option.is_shared ? Users : PiggyBank" class="size-4" />
                            {{ option.name }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <h2 v-else class="shrink-0 truncate text-xl font-semibold tracking-tight">
                    {{ budgetTitle }}
                </h2>

                <SharedWith
                    class="shrink-0"
                    size="md"
                    :title="budget.is_owner ? 'Condividi il tuo budget' : `Budget di ${budget.name}`"
                    :people="budget.people"
                    :is-owner="budget.is_owner"
                    :current-user-id="page.props.auth.user.id"
                    :invite-form="MonthlyBudgetController.storeMember.form()"
                    permission-hint="Deve essere già registrata sulla piattaforma. Chi entra vede e modifica categorie, importi attesi e movimenti come te."
                    leave-label="Esci dal budget"
                    @remove="removeBudgetMember"
                    @leave="leaveBudget"
                />
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="isDownloadingPdf"
                    title="Scarica il budget del mese in PDF"
                    @click="downloadPdf"
                >
                    <FileDown class="mr-2 size-4" />
                    PDF
                </Button>

                <Button variant="outline" size="icon-sm" as-child>
                    <Link
                        :href="budgetCategories.index.url({ query: sharedBudgetQuery })"
                        title="Configura categorie e voci"
                        aria-label="Configura categorie e voci"
                    >
                        <Settings class="size-4" />
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Mese e anno: scorre da bordo a bordo, il padding sta dentro allo
             scroller così la prima e l'ultima pillola restano allineate al resto -->
        <div class="-mx-4">
            <div ref="monthRow" class="flex items-center gap-2 overflow-x-auto px-4 pb-1">
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

            <div v-if="!isViewingToday" class="px-4 pt-2">
                <button
                    class="text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
                    @click="goToToday"
                >
                    Torna a oggi
                </button>
            </div>
        </div>

        <!-- Riepilogo del mese: anelli su mobile, valori per esteso su desktop -->
        <div class="space-y-4">
            <div class="flex items-center justify-center pt-4 sm:hidden">
                <BudgetSummaryRing
                    class="-mr-6"
                    label="Entrate"
                    :amount="incomeActual"
                    :expected="incomePlanned"
                    expected-label="attese"
                />
                <BudgetSummaryRing
                    class="z-10 -translate-y-4"
                    size="lg"
                    accent
                    label="Saldo"
                    :amount="balanceActual"
                    :expected="balancePlanned"
                    expected-label="atteso"
                />
                <BudgetSummaryRing
                    class="-ml-6"
                    label="Uscite"
                    :amount="expenseActual"
                    :expected="expensePlanned"
                    expected-label="attese"
                    over-is-bad
                />
            </div>

            <div class="hidden gap-4 sm:grid sm:grid-cols-3">
                <div class="rounded-2xl bg-muted/60 px-4 py-3 dark:bg-muted/50">
                    <p class="text-xs text-muted-foreground">Entrate incassate</p>
                    <p class="text-2xl font-bold tabular-nums">{{ formatAmount(incomeActual) }}</p>
                    <p class="text-xs text-muted-foreground tabular-nums">
                        attese {{ formatAmount(incomePlanned) }}
                    </p>
                </div>
                <div class="rounded-2xl bg-muted/60 px-4 py-3 dark:bg-muted/50">
                    <p class="text-xs text-muted-foreground">Uscite</p>
                    <p class="text-2xl font-bold tabular-nums">{{ formatAmount(expenseActual) }}</p>
                    <p class="text-xs text-muted-foreground tabular-nums">
                        attese {{ formatAmount(expensePlanned) }}
                    </p>
                </div>
                <div class="rounded-2xl bg-muted/60 px-4 py-3 dark:bg-muted/50">
                    <p class="text-xs text-muted-foreground">Saldo effettivo</p>
                    <p
                        class="text-2xl font-bold tabular-nums"
                        :class="balanceActual >= 0 ? 'text-green-600' : 'text-red-500'"
                    >
                        {{ formatAmount(balanceActual) }}
                    </p>
                    <p class="text-xs text-muted-foreground tabular-nums">
                        atteso {{ formatAmount(balancePlanned) }}
                    </p>
                </div>
            </div>

            <div>
                <div class="mb-1 flex items-baseline justify-between">
                    <span class="text-sm text-muted-foreground">Uscite sulle entrate</span>
                    <span class="text-sm font-semibold tabular-nums">{{ budgetUsage.toFixed(0) }}%</span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-sm bg-foreground/15">
                    <div
                        class="h-full rounded-sm transition-all"
                        :style="{
                            width: `${Math.min(budgetUsage, 100)}%`,
                            backgroundColor: budgetUsage > 100 ? '#ef4444' : '#10b981',
                        }"
                    />
                </div>
                <p class="mt-2 text-xs text-muted-foreground tabular-nums">
                    {{ formatAmount(expenseActual) }} di uscite su
                    {{ formatAmount(budgetBase) }} {{ budgetBaseLabel }}
                </p>
            </div>

            <BudgetExpenseSummary
                :categories="categories"
                :planned="budgetData"
                :actual="expenseData"
            />
        </div>

        <!-- Entrate e uscite: un blocco per verso -->
        <section
            v-for="section in sections"
            :key="section.direction"
            class="rounded-2xl bg-muted/60 dark:bg-muted/50"
        >
            <div class="flex items-start justify-between gap-4 px-5 pt-5">
                <h2 class="text-xl font-bold">
                    {{ section.title }}
                    <span class="text-base font-normal text-muted-foreground">(EUR)</span>
                </h2>
                <Button
                    variant="ghost"
                    size="sm"
                    class="-mr-2 text-muted-foreground"
                    :title="section.addLabel"
                    @click="openAddCategory(section.direction)"
                >
                    <Plus class="mr-1 size-4" />
                    Categoria
                </Button>
            </div>

            <p class="mt-3 border-t border-border/60 px-5 pt-3 font-semibold">
                {{ monthLabel }}
            </p>

            <div v-if="section.categories.length" class="pb-2">
                <BudgetCategoryRow
                    v-for="(category, index) in section.categories"
                    :key="category.id"
                    :category="category"
                    :position="index + 1"
                    :month-name="monthName"
                    :planned="budgetData"
                    :actual="expenseData"
                    :expanded="expandedCategoryId === category.id"
                    @toggle="toggleCategory(category.id)"
                    @update="updateBudgetLine"
                    @flush="flushSave()"
                    @add-subcategory="openAddSubcategory(category.id)"
                    @manage="openCategoryManager(category)"
                    @delete-subcategory="deleteSubcategory(category, $event)"
                />
            </div>

            <p v-else class="px-5 py-6 text-center text-sm text-muted-foreground">
                {{ section.emptyLabel }}
            </p>

            <div
                v-if="section.categories.length"
                class="flex items-baseline justify-between gap-4 border-t border-border/60 px-5 py-4 font-semibold"
            >
                <span>{{ section.totalLabel }}</span>
                <span
                    class="tabular-nums"
                    :class="isOverspent(section.direction, section.planned - section.actual)
                        ? 'text-red-500'
                        : ''"
                >
                    {{ residualLabel(section.direction, section.planned - section.actual) }}
                    <span
                        :class="isOverspent(section.direction, section.planned - section.actual)
                            ? ''
                            : 'font-normal text-muted-foreground'"
                    >
                        / {{ formatAmount(section.planned) }}
                    </span>
                </span>
            </div>
        </section>

        <!-- Movimenti del mese -->
        <div class="rounded-2xl bg-muted/60 dark:bg-muted/50">
            <div
                class="flex flex-nowrap items-center justify-between gap-3 px-5 py-4"
                :class="isTransactionsOpen ? 'border-b border-border/60' : ''"
            >
                <button
                    class="flex min-w-0 items-center gap-2 text-left"
                    :aria-expanded="isTransactionsOpen"
                    @click="isTransactionsOpen = !isTransactionsOpen"
                >
                    <span class="min-w-0 truncate text-xl font-bold">
                        Movimenti ({{ filteredTransactions.length }})
                    </span>
                    <ChevronDown
                        class="size-4 shrink-0 text-muted-foreground transition-transform"
                        :class="{ 'rotate-180': isTransactionsOpen }"
                    />
                </button>

                <Select v-if="transactionCategories.length" v-model="transactionFilter">
                    <SelectTrigger class="h-8 w-32 shrink-0 text-xs sm:w-44">
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
            </div>

            <div v-if="isTransactionsOpen && transactionDays.length" class="divide-y">
                <div v-for="day in transactionDays" :key="day.label">
                    <div class="flex items-center justify-between gap-4 bg-foreground/5 px-5 py-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            {{ day.label }}
                        </span>
                        <span class="text-xs text-muted-foreground tabular-nums">{{ formatAmount(day.total) }}</span>
                    </div>

                    <div
                        v-for="transaction in day.items"
                        :key="transaction.id"
                        class="group flex items-center gap-3 px-5 py-3 hover:bg-foreground/5"
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
                            class="shrink-0 text-sm font-semibold tabular-nums"
                            :class="transaction.direction === 'income' ? 'text-green-600' : ''"
                        >
                            {{ transaction.direction === 'income' ? '+' : '' }}{{ formatAmount(signedAmount(transaction)) }}
                        </p>

                        <!-- Su mobile due icone rubano spazio alla descrizione. -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="shrink-0 text-muted-foreground sm:hidden"
                                    :title="`Azioni sul movimento di ${formatCurrency(transaction.amount)}`"
                                >
                                    <EllipsisVertical class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="openEditMovement(transaction)">
                                    <Pencil class="size-4" />
                                    Modifica
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    @click="deleteTransaction(transaction)"
                                >
                                    <Trash2 class="size-4" />
                                    Elimina
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <div class="-mr-1 hidden shrink-0 items-center sm:flex">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground opacity-60 transition-opacity hover:text-foreground focus-visible:opacity-100 group-hover:opacity-100"
                                :title="`Modifica il movimento di ${formatCurrency(transaction.amount)}`"
                                @click="openEditMovement(transaction)"
                            >
                                <Pencil class="size-4" />
                            </Button>

                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground opacity-60 transition-opacity hover:bg-destructive/10 hover:text-destructive focus-visible:opacity-100 group-hover:opacity-100"
                                :title="`Elimina il movimento di ${formatCurrency(transaction.amount)}`"
                                @click="deleteTransaction(transaction)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="isTransactionsOpen" class="px-5 py-10 text-center">
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
        size="icon"
        class="fixed bottom-[calc(5.5rem_+_env(safe-area-inset-bottom))] right-4 z-40 size-14 rounded-full shadow-lg md:bottom-6 md:right-6"
        title="Nuovo movimento"
        aria-label="Nuovo movimento"
        @click="openAddMovement()"
    >
        <Plus class="size-6" />
    </Button>

    <!-- Sheet: gestione di una categoria e dei suoi movimenti -->
    <BudgetCategoryManager
        v-model:open="isCategoryManagerOpen"
        :category="managedCategory"
        :planned="budgetData"
        :actual="expenseData"
        :transactions="managedTransactions"
        :month-label="monthLabel"
        :year="currentYear"
        :month="currentMonth"
        @update-planned="updateBudgetLine"
        @flush="flushSave()"
        @add-subcategory="addSubcategoryFromManager"
        @delete-subcategory="managedCategory && deleteSubcategory(managedCategory, $event)"
        @delete-category="deleteCategoryFromManager"
        @delete-transaction="deleteTransaction"
    />

    <!-- Sheet: Aggiungi Categoria -->
    <Sheet v-model:open="isAddCategoryOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>
                    {{ categoryForm.type === 'income' ? 'Entrata' : 'Uscita' }} solo per {{ monthLabel }}
                </SheetTitle>
            </SheetHeader>

            <div class="space-y-4 px-4 pb-6">
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
                    <BudgetColorPicker v-model="categoryForm.color" />
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

            <div class="mt-2 space-y-4">
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
                <DialogTitle>
                    {{ editingTransaction ? 'Modifica movimento' : 'Nuovo movimento' }} · {{ monthLabel }}
                </DialogTitle>
            </DialogHeader>

            <div class="mt-2 space-y-4">
                <div class="grid grid-cols-2 gap-2 rounded-lg bg-muted dark:bg-muted/50 p-1">
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

                    <!-- La voce che serve può non esistere ancora: si crea da qui. -->
                    <template v-if="movementCategory">
                        <Button
                            v-if="!isAddingMovementSubcategory"
                            variant="ghost"
                            size="sm"
                            class="justify-start px-0 text-muted-foreground"
                            @click="isAddingMovementSubcategory = true"
                        >
                            <Plus class="mr-1 size-4" />
                            Nuova voce in {{ movementCategory.name }}
                        </Button>

                        <div v-else class="grid gap-2">
                            <div class="flex items-center gap-2">
                                <Input
                                    v-model="quickSubcategoryForm.name"
                                    placeholder="Nome della voce"
                                    aria-label="Nome della nuova voce"
                                    @keyup.enter="createMovementSubcategory"
                                />
                                <Button
                                    size="sm"
                                    class="shrink-0"
                                    :disabled="quickSubcategoryForm.processing || quickSubcategoryForm.name.trim() === ''"
                                    @click="createMovementSubcategory"
                                >
                                    Crea
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="shrink-0"
                                    @click="resetQuickSubcategory"
                                >
                                    Annulla
                                </Button>
                            </div>
                            <p v-if="quickSubcategoryForm.errors.name" class="text-sm text-destructive">
                                {{ quickSubcategoryForm.errors.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Vale solo per {{ monthLabel.toLowerCase() }}.
                            </p>
                        </div>
                    </template>
                </div>

                <p v-if="movementSubcategory" class="rounded-md bg-muted dark:bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                    <template v-if="movementDirection === 'income'">
                        Atteso {{ formatAmount(planned(movementSubcategory.id)) }} · già incassato
                        {{ formatAmount(actual(movementSubcategory.id)) }}
                    </template>
                    <template v-else>
                        Atteso {{ formatAmount(planned(movementSubcategory.id)) }} · già speso
                        {{ formatAmount(actual(movementSubcategory.id)) }} · rimane
                        <span
                            :class="planned(movementSubcategory.id) - actual(movementSubcategory.id) >= 0
                                ? 'text-green-600'
                                : 'text-red-500'"
                        >
                            {{ formatAmount(planned(movementSubcategory.id) - actual(movementSubcategory.id)) }}
                        </span>
                    </template>
                </p>

                <div class="grid gap-2">
                    <Label for="movement-description">Descrizione</Label>
                    <Input
                        id="movement-description"
                        v-model="movementForm.description"
                        placeholder="Facoltativa"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="movement-amount">Importo</Label>
                    <Input
                        id="movement-amount"
                        v-model="movementForm.amount"
                        inputmode="decimal"
                        placeholder="0,00"
                    />
                    <p v-if="movementForm.errors.amount" class="text-sm text-destructive">
                        {{ movementForm.errors.amount }}
                    </p>
                </div>

                <!-- I controlli nativi di data e ora hanno una larghezza minima:
                     su schermi stretti vanno stretti di padding e testo. -->
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label for="movement-date">Data</Label>
                        <Input
                            id="movement-date"
                            v-model="movementDate"
                            type="date"
                            class="px-2 text-center text-sm"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="movement-time">Ora</Label>
                        <Input
                            id="movement-time"
                            v-model="movementTime"
                            type="time"
                            class="px-2 text-center text-sm"
                        />
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
                    {{ editingTransaction ? 'Salva movimento' : 'Registra movimento' }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
