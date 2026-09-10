<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowRightLeft,
    ArrowUpRight,
    CalendarDays,
    Check,
    ChevronDown,
    ChevronRight,
    CircleDashed,
    EllipsisVertical,
    FileDown,
    Pencil,
    PenLine,
    PiggyBank,
    Plus,
    Settings,
    SlidersHorizontal,
    Star,
    Tag,
    Tags,
    Trash2,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import monthlyBudgets from '@/routes/monthly-budgets';
import budgetCategories from '@/routes/budget-categories';
import budgetSubcategories from '@/routes/budget-subcategories';
import budgetExpenses from '@/routes/budget-expenses';
import accountTransfers from '@/routes/account-transfers';
import budgetAccounts from '@/routes/budget-accounts';
import BudgetCategoryRow from '@/components/budget/BudgetCategoryRow.vue';
import BudgetSummaryRing from '@/components/budget/BudgetSummaryRing.vue';
import BudgetExpenseSummary from '@/components/budget/BudgetExpenseSummary.vue';
import BudgetCategoryManager from '@/components/budget/BudgetCategoryManager.vue';
import BudgetColorPicker from '@/components/budget/BudgetColorPicker.vue';
import {
    accountIcon,
    accountTypeLabels,
    selectableAccounts,
} from '@/lib/budget-accounts';
import BudgetAccountTiles from '@/components/budget/BudgetAccountTiles.vue';
import type { BudgetAccount } from '@/lib/budget-accounts';
import SharedWith from '@/components/SharedWith.vue';
import type { SharedPerson } from '@/types/sharing';
import MonthlyBudgetController from '@/actions/App/Http/Controllers/Budget/MonthlyBudgetController';
import { formatAmount, formatCurrency } from '@/lib/balance-sheet-format';
import { useSheetDrag } from '@/composables/useSheetDrag';
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
import { avatarStyle } from '@/lib/avatar-color';

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
    /** Quello che si apre all'avvio, anche se è di qualcun altro. */
    is_default: boolean;
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
    direction: Direction | 'transfer';
    account_id: number | null;
    account_name: string | null;
    account_color: string | null;
    /** Chi l'ha registrato: nullo sui movimenti scritti prima che si segnasse. */
    recorded_by_id: number | null;
    recorded_by_name: string | null;
    // Un trasferimento non ha categoria: sposta soldi tra due conti.
    kind: 'movement' | 'transfer';
    to_account_id: number | null;
    to_account_name: string | null;
    to_account_color: string | null;
}

const props = defineProps<{
    year: number;
    month: number;
    categories: Category[];
    budgetLines: Record<number, number>;
    expenses: Record<number, number>;
    transactions: Transaction[];
    monthlyBudget: number | null;
    accounts: BudgetAccount[];
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
    props.budget.is_owner ? {} : { budget: props.budget.id },
);

const budgetOwnerId = computed(() =>
    props.budget.is_owner ? null : props.budget.id,
);

const budgetTitle = computed(() =>
    props.budget.is_owner ? 'Budget' : `Budget di ${props.budget.name}`,
);

const goToBudget = (option: BudgetOption) => {
    router.get(
        monthlyBudgets.index.url({
            query: {
                year: currentYear.value,
                month: currentMonth.value,
                // Sempre esplicito, anche per il proprio: senza `budget` si
                // apre il predefinito, che può essere quello di qualcun altro.
                budget: option.id,
            },
        }),
    );
};

/**
 * Quale budget si apre all'avvio.
 *
 * Sceglierne uno condiviso è il senso della stella: chi tiene i conti di casa
 * su quello di un'altra persona non deve pescarlo dal menù ogni volta.
 */
const makeDefaultBudget = (option: BudgetOption) => {
    if (option.is_default) {
        return;
    }

    router.post(
        MonthlyBudgetController.setDefaultBudget.url(),
        { budget_user_id: option.id },
        { preserveScroll: true, preserveState: true, only: ['budgets'] },
    );
};

const removeBudgetMember = (person: SharedPerson) => {
    if (!confirm(`Rimuovere ${person.name} dal tuo budget? Non lo vedrà più.`))
        return;

    router.delete(
        MonthlyBudgetController.destroyMember([props.budget.id, person.id]).url,
        {
            preserveScroll: true,
        },
    );
};

const leaveBudget = (person: SharedPerson) => {
    if (
        !confirm(
            `Uscire dal budget di ${props.budget.name}? Non lo vedrai più finché non ti reinvitano.`,
        )
    )
        return;

    router.delete(
        MonthlyBudgetController.destroyMember([props.budget.id, person.id]).url,
    );
};

const months = [
    'Gennaio',
    'Febbraio',
    'Marzo',
    'Aprile',
    'Maggio',
    'Giugno',
    'Luglio',
    'Agosto',
    'Settembre',
    'Ottobre',
    'Novembre',
    'Dicembre',
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
    financial_account_id: null as number | null,
    to_financial_account_id: null as number | null,
    amount: '',
    description: '',
    recorded_at: '',
});

// Il select non regge un valore nullo: i contanti hanno una voce tutta loro,
// che al salvataggio torna a essere "nessun conto".
const CASH = 'cash' as const;
const movementAccountId = ref<number | typeof CASH>(CASH);
// Il conto di arrivo di un trasferimento.
const transferAccountId = ref<number | typeof CASH>(CASH);

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

type MovementKind = Direction | 'transfer';

const movementKind = ref<MovementKind>('expense');
const isTransfer = computed(() => movementKind.value === 'transfer');

const movementKinds: Array<{ value: MovementKind; label: string }> = [
    { value: 'expense', label: 'Uscita' },
    { value: 'income', label: 'Entrata' },
    { value: 'transfer', label: 'Trasferimento' },
];

const dialogTitle = computed(() => {
    const noun = isTransfer.value ? 'trasferimento' : 'movimento';

    return editingTransaction.value
        ? `Modifica ${noun}`
        : `Nuovo ${noun.charAt(0).toUpperCase()}${noun.slice(1)}`;
});

const submitLabel = computed(() => {
    const noun = isTransfer.value ? 'trasferimento' : 'movimento';

    return editingTransaction.value ? `Salva ${noun}` : `Registra ${noun}`;
});

/**
 * Un movimento non diventa un trasferimento a metà modifica: sono due cose
 * diverse in due tabelle diverse. Per cambiarne la natura si cancella e si
 * riscrive; il verso di un movimento, invece, resta libero.
 */
const isKindLocked = (kind: MovementKind) => {
    const editing = editingTransaction.value;

    if (!editing) return false;

    return (editing.kind === 'transfer') !== (kind === 'transfer');
};

/** I contanti non sono un conto: valgono come "nessun conto scelto". */
const accountKeyToId = (key: number | typeof CASH) =>
    key === CASH ? null : key;

/**
 * Il conto di un movimento scritto da qualcun altro non è fra i miei - in un
 * budget condiviso i conti restano personali - ma va comunque mostrato come
 * scelto, altrimenti aprendo la modifica sembrerebbe sparito. Da lì lo si può
 * solo lasciare dov'è o spostare su una carta propria.
 */
const foreignAccount = computed<BudgetAccount | null>(() => {
    const editing = editingTransaction.value;
    const id = editing?.account_id ?? null;

    if (id === null || props.accounts.some((account) => account.id === id)) {
        return null;
    }

    return {
        id,
        name: editing?.account_name ?? "Conto di un'altra persona",
        type: '',
        color: editing?.account_color ?? null,
        icon: null,
        balance: 0,
        hidden_from_stats: false,
        excluded_from_stats: false,
    };
});

/** I conti scegliibili più, se serve, quello altrui già sul movimento. */
const accountsForMovement = computed(() =>
    foreignAccount.value
        ? [...props.accounts, foreignAccount.value]
        : props.accounts,
);

// I conti archiviati non si propongono: si sceglie fra quelli ancora in uso.
const movementAccountOptions = computed(() =>
    selectableAccounts(
        accountsForMovement.value,
        accountKeyToId(movementAccountId.value),
    ),
);

const transferAccountOptions = computed(() =>
    selectableAccounts(props.accounts, accountKeyToId(transferAccountId.value)),
);

/**
 * La firma sui movimenti si mostra solo quando il budget è di più persone:
 * da soli, "l'ha scritto Iana" su ogni riga è rumore.
 */
const isSharedBudget = computed(() => props.budget.people.length > 1);

/** Sulla pastiglia ci sta il nome di battesimo, non il nome intero. */
const recordedByLabel = (transaction: Transaction) =>
    transaction.recorded_by_name?.split(' ')[0] ?? null;

/**
 * Lo stesso colore dell'avatar in cima alla pagina - `avatarStyle` lo ricava
 * dal nome intero, quindi va passato quello anche se sulla pastiglia si legge
 * solo il nome di battesimo.
 */
const recordedByStyle = (transaction: Transaction) =>
    avatarStyle(transaction.recorded_by_name ?? '');

/** Il valore mostrato a destra nella riga, come su un elenco di iPhone. */
const accountValueLabel = computed(() => {
    const account = accountsForMovement.value.find(
        (item) => item.id === movementAccountId.value,
    );

    return account?.name ?? 'Non indicato';
});

const transferValueLabel = computed(() => {
    const account = props.accounts.find(
        (item) => item.id === transferAccountId.value,
    );

    return account?.name ?? 'Non indicato';
});

// Il pannello si butta giù col dito: il gesto è lo stesso dei conti.
const {
    dragStyle: sheetDragStyle,
    start: startSheetDrag,
    move: moveSheetDrag,
    end: endSheetDrag,
} = useSheetDrag(() => (isAddMovementOpen.value = false));

const {
    dragStyle: filtersDragStyle,
    start: startFiltersDrag,
    move: moveFiltersDrag,
    end: endFiltersDrag,
} = useSheetDrag(() => (isFiltersOpen.value = false));

const amountTone = computed(() => {
    if (isTransfer.value) return 'text-foreground';

    return movementKind.value === 'income' ? 'text-green-600' : 'text-red-500';
});

const amountSign = computed(() => {
    if (isTransfer.value) return '';

    return movementKind.value === 'income' ? '+' : '−';
});

const accountLabel = computed(() => {
    if (isTransfer.value) return 'Dal conto';

    return movementKind.value === 'income'
        ? 'Su quale conto'
        : 'Da quale conto';
});
// Un trasferimento non ha un verso: per categorie e importi vale come uscita.
const movementDirection = computed<Direction>(() =>
    movementKind.value === 'income' ? 'income' : 'expense',
);
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
            resetFilters();
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

const sumOf = (
    direction: Direction,
    amountFor: (subcategoryId: number) => number,
) =>
    categoriesOf(direction)
        .flatMap((category) => category.subcategories)
        .reduce((sum, sub) => sum + amountFor(sub.id), 0);

const incomePlanned = computed(() => sumOf('income', planned));
const incomeActual = computed(() => sumOf('income', actual));
const expensePlanned = computed(() => sumOf('expense', planned));
const expenseActual = computed(() => sumOf('expense', actual));

const balanceActual = computed(() => incomeActual.value - expenseActual.value);
const balancePlanned = computed(
    () => incomePlanned.value - expensePlanned.value,
);

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
    direction === 'income' && residual < 0
        ? `+${formatAmount(-residual)}`
        : formatAmount(residual);

const isOverspent = (direction: Direction, residual: number) =>
    direction === 'expense' && residual < 0;

// Barra unica: il fondo scala sulle entrate del mese, il riempimento sono
// le uscite. Finché non è entrato nulla si usa quanto è atteso.
const budgetBase = computed(() =>
    incomeActual.value > 0 ? incomeActual.value : incomePlanned.value,
);

const budgetBaseLabel = computed(() =>
    incomeActual.value > 0 ? 'incassati' : 'attesi',
);

const budgetUsage = computed(() => {
    if (budgetBase.value <= 0) return expenseActual.value > 0 ? 100 : 0;

    return (expenseActual.value / budgetBase.value) * 100;
});

const toggleCategory = (categoryId: number) => {
    expandedCategoryId.value =
        expandedCategoryId.value === categoryId ? null : categoryId;
};

const today = new Date();

const yearOptions = computed(() => {
    const years = new Set<number>();

    for (
        let year = today.getFullYear() - 5;
        year <= today.getFullYear() + 5;
        year++
    ) {
        years.add(year);
    }

    years.add(props.year);

    return [...years].sort((a, b) => a - b);
});

const isToday = (year: number, month: number) =>
    today.getFullYear() === year && today.getMonth() + 1 === month;

const isViewingToday = computed(() =>
    isToday(currentYear.value, currentMonth.value),
);

const goToToday = () => goToMonth(today.getFullYear(), today.getMonth() + 1);

const monthRow = ref<HTMLElement | null>(null);

const scrollActiveMonthIntoView = () => {
    monthRow.value
        ?.querySelector('[data-active="true"]')
        ?.scrollIntoView({ inline: 'center', block: 'nearest' });
};

onMounted(scrollActiveMonthIntoView);
watch(
    () => [props.year, props.month],
    () => nextTick(scrollActiveMonthIntoView),
);

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
    const go = () =>
        router.get(
            monthlyBudgets.index.url({
                query: {
                    year: currentYear.value,
                    month: currentMonth.value,
                    ...sharedBudgetQuery.value,
                },
            }),
        );

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

const pdfUrl = () =>
    monthlyBudgets.pdf.url({
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

const selectedCategory = computed(
    () =>
        props.categories.find(
            (category) => category.id === selectedCategoryId.value,
        ) ?? null,
);

const managedCategory = computed(
    () =>
        props.categories.find(
            (category) => category.id === managedCategoryId.value,
        ) ?? null,
);

const managedTransactions = computed(() =>
    props.transactions.filter(
        (transaction) => transaction.category_id === managedCategoryId.value,
    ),
);

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

    subcategoryForm.post(
        budgetCategories.subcategories.store.url(selectedCategoryId.value),
        {
            preserveScroll: true,
            onSuccess: () => {
                isAddSubcategoryOpen.value = false;
                subcategoryForm.reset();
            },
        },
    );
};

// Da qui si cancella solo quello che è nato in questo mese: le categorie e le
// voci valide per tutti i mesi si gestiscono dalla pagina Categorie.
const deleteCategory = (category: Category) => {
    if (!category.monthly_budget_id) return;

    const confirmed = confirm(
        `Eliminare "${category.name}" e le sue voci solo da ${monthLabel.value}? ` +
            'Verranno persi anche gli importi attesi e i movimenti collegati.',
    );

    if (!confirmed) return;

    router.delete(budgetCategories.destroy.url(category.id), {
        preserveScroll: true,
    });
};

const deleteSubcategory = (category: Category, subcategoryId: number) => {
    const subcategory = category.subcategories.find(
        (sub) => sub.id === subcategoryId,
    );

    if (!subcategory?.monthly_budget_id) return;

    const confirmed = confirm(
        `Eliminare "${subcategory.name}" solo da ${monthLabel.value}? ` +
            'Verranno persi anche gli importi attesi e i movimenti collegati.',
    );

    if (!confirmed) return;

    router.delete(budgetSubcategories.destroy.url(subcategoryId), {
        preserveScroll: true,
    });
};

// Quasi tutti pagano sempre con la stessa carta: si riparte da quella usata
// per ultima, o dall'unico conto se ce n'è uno solo. I conti archiviati non
// valgono come proposta: non si devono nemmeno vedere su un movimento nuovo.
const defaultAccountId = computed<number | typeof CASH>(() => {
    const visible = selectableAccounts(props.accounts);
    const isVisible = (id: number) =>
        visible.some((account) => account.id === id);

    const lastUsed = props.transactions.find(
        (transaction) =>
            transaction.account_id !== null &&
            isVisible(transaction.account_id),
    );

    if (lastUsed?.account_id) return lastUsed.account_id;

    return visible.length === 1 ? visible[0].id : CASH;
});

const movementCategories = computed(() =>
    categoriesOf(movementDirection.value),
);

const movementCategory = computed(
    () =>
        props.categories.find(
            (category) => category.id === movementCategoryId.value,
        ) ?? null,
);

const movementSubcategory = computed(() => {
    if (!movementForm.budget_subcategory_id) return null;

    return (
        props.categories
            .flatMap((category) => category.subcategories)
            .find((sub) => sub.id === movementForm.budget_subcategory_id) ??
        null
    );
});

const canSubmitMovement = computed(() => {
    if (movementForm.amount.trim() === '') return false;

    // Spostare soldi da una tasca a sé stessa non è un trasferimento.
    if (isTransfer.value)
        return movementAccountId.value !== transferAccountId.value;

    return Boolean(movementForm.budget_subcategory_id);
});

const setMovementKind = (kind: MovementKind) => {
    if (kind === movementKind.value) return;

    movementKind.value = kind;
    movementCategoryId.value = null;
    movementForm.budget_subcategory_id = null;
    resetQuickSubcategory();
};

watch(movementCategoryId, (categoryId) => {
    const stillValid = props.categories
        .find((category) => category.id === categoryId)
        ?.subcategories.some(
            (sub) => sub.id === movementForm.budget_subcategory_id,
        );

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

    quickSubcategoryForm.post(
        budgetCategories.subcategories.store.url(categoryId),
        {
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
        },
    );
};

const openEditMovement = (transaction: Transaction) => {
    editingTransaction.value = transaction;
    resetQuickSubcategory();
    movementForm.reset();
    movementForm.clearErrors();

    movementKind.value =
        transaction.kind === 'transfer'
            ? 'transfer'
            : (transaction.direction as Direction);
    movementCategoryId.value = transaction.category_id;
    movementForm.budget_subcategory_id = transaction.subcategory_id;
    movementAccountId.value = transaction.account_id ?? CASH;
    transferAccountId.value = transaction.to_account_id ?? CASH;
    movementForm.amount = String(transaction.amount);
    movementForm.description = transaction.description ?? '';
    setMovementDate(
        transaction.recorded_at
            ? new Date(transaction.recorded_at)
            : new Date(),
    );

    isAddMovementOpen.value = true;
};

const openAddMovement = () => {
    editingTransaction.value = null;
    resetQuickSubcategory();
    movementForm.reset();
    movementForm.clearErrors();
    movementCategoryId.value = null;
    movementKind.value = 'expense';
    movementAccountId.value = defaultAccountId.value;
    transferAccountId.value = CASH;

    // Se sto guardando un altro mese il movimento nasce dentro quel mese.
    const now = new Date();
    const isCurrentMonth =
        now.getFullYear() === currentYear.value &&
        now.getMonth() + 1 === currentMonth.value;
    const day = isCurrentMonth
        ? now
        : new Date(currentYear.value, currentMonth.value - 1, 1);

    setMovementDate(day);

    if (!isCurrentMonth) movementTime.value = '12:00';

    isAddMovementOpen.value = true;
};

const submitMovement = () => {
    movementForm.budget_user_id = budgetOwnerId.value;
    movementForm.year = currentYear.value;
    movementForm.month = currentMonth.value;
    movementForm.financial_account_id = accountKeyToId(movementAccountId.value);
    movementForm.to_financial_account_id = accountKeyToId(
        transferAccountId.value,
    );
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

    const editing = editingTransaction.value;

    if (isTransfer.value) {
        // Il trasferimento ha un capo in più e nessuna categoria: gli stessi
        // campi del pannello, rinominati per il suo endpoint.
        movementForm.transform((data) => ({
            budget_user_id: data.budget_user_id,
            from_financial_account_id: data.financial_account_id,
            to_financial_account_id: data.to_financial_account_id,
            amount: data.amount,
            description: data.description,
            recorded_at: data.recorded_at,
        }));

        if (editing?.kind === 'transfer') {
            movementForm.put(accountTransfers.update.url(editing.id), options);

            return;
        }

        movementForm.post(accountTransfers.store.url(), options);

        return;
    }

    movementForm.transform((data) => data);

    if (editing?.kind === 'movement') {
        movementForm.put(budgetExpenses.update.url(editing.id), options);

        return;
    }

    movementForm.post(budgetExpenses.store.url(), options);
};

const deleteTransaction = (transaction: Transaction) => {
    const label =
        transaction.kind === 'transfer' ? 'trasferimento' : 'movimento';

    if (
        !confirm(
            `Eliminare il ${label} di ${formatCurrency(transaction.amount)}?`,
        )
    )
        return;

    const route =
        transaction.kind === 'transfer'
            ? accountTransfers.destroy.url(transaction.id)
            : budgetExpenses.destroy.url(transaction.id);

    router.delete(route, { preserveScroll: true });
};

// L'elenco parte chiuso: in cima alla pagina contano i totali, i singoli
// movimenti si aprono quando si va a cercarli.
const isTransactionsOpen = ref(false);
const isFiltersOpen = ref(false);

interface TransactionFilters {
    kinds: MovementKind[];
    /** Il conto di provenienza, con `CASH` per i movimenti in contanti. */
    accounts: Array<number | typeof CASH>;
    categories: number[];
    fromDay: string;
    toDay: string;
}

const emptyFilters = (): TransactionFilters => ({
    kinds: [],
    accounts: [],
    categories: [],
    fromDay: '',
    toDay: '',
});

const filters = ref<TransactionFilters>(emptyFilters());

const resetFilters = () => {
    filters.value = emptyFilters();
};

/** Una lista vuota non filtra niente: vale come "tutte". */
const activeFilterCount = computed(() => {
    const { kinds, accounts, categories, fromDay, toDay } = filters.value;

    return [
        kinds.length > 0,
        accounts.length > 0,
        categories.length > 0,
        fromDay !== '' || toDay !== '',
    ].filter(Boolean).length;
});

// Solo le categorie che compaiono davvero nei movimenti del mese: filtrare
// per una categoria senza movimenti darebbe sempre un elenco vuoto.
const transactionCategories = computed(() => {
    const seen = new Map<number, { id: number; name: string; color: string }>();

    props.transactions.forEach((transaction) => {
        if (
            transaction.category_id === null ||
            seen.has(transaction.category_id)
        )
            return;

        seen.set(transaction.category_id, {
            id: transaction.category_id,
            name: transaction.category_name ?? '—',
            color: transaction.category_color ?? '#3b82f6',
        });
    });

    return [...seen.values()].sort((a, b) =>
        a.name.localeCompare(b.name, 'it'),
    );
});

interface AccountFilter {
    value: number | typeof CASH;
    name: string;
    color: string | null;
    icon: string | null;
}

/**
 * I conti su cui si può filtrare sono quelli che compaiono davvero nei
 * movimenti del mese.
 *
 * Non basta l'elenco dei propri: in un budget condiviso le righe portano
 * anche le carte dell'altra persona, che si leggono sul movimento ma non si
 * possiedono. E all'incontrario, un conto proprio senza movimenti - o escluso
 * dalle statistiche - darebbe sempre un elenco vuoto. I contanti restano in
 * fondo: sono un metodo di pagamento anche se non sono un conto.
 */
const filterAccounts = computed<AccountFilter[]>(() => {
    const seen = new Map<number, AccountFilter>();

    const remember = (
        id: number | null,
        name: string | null,
        color: string | null,
    ) => {
        if (id === null || seen.has(id)) {
            return;
        }

        seen.set(id, { value: id, name: name ?? 'Conto', color, icon: null });
    };

    props.transactions.forEach((transaction) => {
        remember(
            transaction.account_id,
            transaction.account_name,
            transaction.account_color,
        );
        remember(
            transaction.to_account_id,
            transaction.to_account_name,
            transaction.to_account_color,
        );
    });

    // I propri per primi, nell'ordine scelto trascinandoli; poi quelli di chi
    // condivide il budget, in ordine alfabetico.
    const mine = props.accounts
        .filter((account) => seen.has(account.id))
        .map((account) => ({
            value: account.id,
            name: account.name,
            color: account.color,
            icon: account.icon,
        }));

    const others = [...seen.values()]
        .filter(
            (entry) =>
                !props.accounts.some((account) => account.id === entry.value),
        )
        .sort((a, b) => a.name.localeCompare(b.name, 'it'));

    return [
        ...mine,
        ...others,
        { value: CASH, name: 'Non indicato', color: null, icon: null },
    ];
});

const kindOf = (transaction: Transaction): MovementKind =>
    transaction.kind === 'transfer'
        ? 'transfer'
        : (transaction.direction as Direction);

/** Il giorno del movimento come "2026-09-04", per confrontarlo con i due estremi. */
const dayOf = (transaction: Transaction) => {
    const date = transaction.recorded_at
        ? new Date(transaction.recorded_at)
        : new Date();

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
};

// Un trasferimento tocca due conti: basta che uno dei due sia tra quelli scelti.
const matchesAccount = (
    transaction: Transaction,
    chosen: Array<number | typeof CASH>,
) => {
    const sides: Array<number | typeof CASH> = [transaction.account_id ?? CASH];

    if (transaction.kind === 'transfer')
        sides.push(transaction.to_account_id ?? CASH);

    return sides.some((side) => chosen.includes(side));
};

const filteredTransactions = computed(() => {
    const { kinds, accounts, categories, fromDay, toDay } = filters.value;

    return props.transactions.filter((transaction) => {
        if (kinds.length > 0 && !kinds.includes(kindOf(transaction)))
            return false;

        if (accounts.length > 0 && !matchesAccount(transaction, accounts))
            return false;

        if (
            categories.length > 0 &&
            (transaction.category_id === null ||
                !categories.includes(transaction.category_id))
        ) {
            return false;
        }

        const day = dayOf(transaction);

        if (fromDay !== '' && day < fromDay) return false;

        if (toDay !== '' && day > toDay) return false;

        return true;
    });
});

/** Aggiunge o toglie una voce da una lista di filtri. */
const toggleFilter = <T,>(list: T[], value: T): T[] =>
    list.includes(value)
        ? list.filter((item) => item !== value)
        : [...list, value];

/**
 * Le sezioni del pannello filtri si chiudono: conti e categorie sono elenchi
 * lunghi, e da chiusi dicono in una riga quante scelte hanno dentro. Il
 * periodo no: due campi data non hanno niente da riassumere, e nasconderli
 * vorrebbe dire aprirli ogni volta per vedere se sono pieni.
 */
type FilterSection = 'accounts' | 'categories';

const openFilterSections = ref<FilterSection[]>([]);

const isFilterSectionOpen = (section: FilterSection) =>
    openFilterSections.value.includes(section);

const toggleFilterSection = (section: FilterSection) => {
    openFilterSections.value = toggleFilter(openFilterSections.value, section);
};

/** Quello che si legge a destra del titolo quando la sezione è chiusa. */
const chosenLabel = (count: number) => {
    if (count === 0) return 'Tutti';

    return count === 1 ? '1 scelto' : `${count} scelti`;
};

// Riaprendo i filtri si torna dove si era già scelto qualcosa: una spunta
// dentro a una sezione chiusa si ritroverebbe solo per caso.
watch(isFiltersOpen, (open) => {
    if (!open) return;

    const sections: FilterSection[] = ['accounts', 'categories'];

    openFilterSections.value = sections.filter(
        (section) => filters.value[section].length > 0,
    );
});

const toggleKind = (kind: MovementKind) => {
    filters.value.kinds = toggleFilter(filters.value.kinds, kind);
};

const toggleAccount = (account: number | typeof CASH) => {
    filters.value.accounts = toggleFilter(filters.value.accounts, account);
};

const toggleCategoryFilter = (categoryId: number) => {
    filters.value.categories = toggleFilter(
        filters.value.categories,
        categoryId,
    );
};

// Il periodo si muove dentro al mese aperto: fuori da qui i movimenti non
// sono nemmeno stati caricati.
const monthBounds = computed(() => {
    const pad = (value: number) => String(value).padStart(2, '0');
    const lastDay = new Date(
        currentYear.value,
        currentMonth.value,
        0,
    ).getDate();
    const month = `${currentYear.value}-${pad(currentMonth.value)}`;

    return { from: `${month}-01`, to: `${month}-${pad(lastDay)}` };
});

// I soldi che cambiano solo tasca non spostano il totale del giorno.
const signedAmount = (transaction: Transaction) => {
    if (transaction.kind === 'transfer') return 0;

    return transaction.direction === 'income'
        ? transaction.amount
        : -transaction.amount;
};

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
    const groups = new Map<
        string,
        { label: string; total: number; items: Transaction[] }
    >();

    filteredTransactions.value.forEach((transaction) => {
        const date = transaction.recorded_at
            ? new Date(transaction.recorded_at)
            : new Date();
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
                            <ChevronDown
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem
                            v-for="option in budgets"
                            :key="option.id"
                            class="gap-3 pr-1"
                            @click="goToBudget(option)"
                        >
                            <component
                                :is="option.is_shared ? Users : PiggyBank"
                                class="size-4 shrink-0"
                            />
                            <span class="min-w-0 flex-1 truncate">
                                {{ option.name }}
                            </span>

                            <!-- La stella non cambia budget: dice solo quale
                                 aprire la prossima volta. -->
                            <button
                                class="-my-1 shrink-0 rounded-md p-1 transition-colors"
                                :class="
                                    option.is_default
                                        ? 'text-primary'
                                        : 'text-muted-foreground/50 hover:text-foreground'
                                "
                                :title="
                                    option.is_default
                                        ? `${option.name} è il budget che si apre all'avvio`
                                        : `Apri ${option.name} all'avvio`
                                "
                                :aria-pressed="option.is_default"
                                @pointerdown.stop
                                @click.stop.prevent="makeDefaultBudget(option)"
                            >
                                <Star
                                    class="size-4"
                                    :class="
                                        option.is_default ? 'fill-current' : ''
                                    "
                                />
                            </button>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <h2
                    v-else
                    class="shrink-0 truncate text-xl font-semibold tracking-tight"
                >
                    {{ budgetTitle }}
                </h2>

                <SharedWith
                    class="shrink-0"
                    size="md"
                    :title="
                        budget.is_owner
                            ? 'Condividi il tuo budget'
                            : `Budget di ${budget.name}`
                    "
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
                    size="icon-sm"
                    :disabled="isDownloadingPdf"
                    title="Scarica il budget del mese in PDF"
                    aria-label="Scarica il budget del mese in PDF"
                    @click="downloadPdf"
                >
                    <FileDown class="size-4" />
                </Button>

                <Button variant="outline" size="icon-sm" as-child>
                    <Link
                        :href="
                            budgetAccounts.index.url({
                                query: sharedBudgetQuery,
                            })
                        "
                        title="Conti e carte"
                        aria-label="Conti e carte"
                    >
                        <Wallet class="size-4" />
                    </Link>
                </Button>

                <Button variant="outline" size="icon-sm" as-child>
                    <Link
                        :href="
                            budgetCategories.index.url({
                                query: sharedBudgetQuery,
                            })
                        "
                        title="Configura categorie e voci"
                        aria-label="Configura categorie e voci"
                    >
                        <Settings class="size-4" />
                    </Link>
                </Button>
            </div>
        </div>

        <BudgetAccountTiles
            :accounts="accounts"
            :manage-href="
                budgetAccounts.index.url({ query: sharedBudgetQuery })
            "
        />

        <!-- Mese e anno: scorre da bordo a bordo, il padding sta dentro allo
             scroller così la prima e l'ultima pillola restano allineate al resto -->
        <div class="-mx-4">
            <div
                ref="monthRow"
                class="flex items-center gap-2 overflow-x-auto px-4 pb-1"
            >
                <Select v-model="selectedYear">
                    <SelectTrigger
                        class="h-9 w-24 shrink-0 rounded-full border-0 bg-muted/50 shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="year in yearOptions"
                            :key="year"
                            :value="year"
                        >
                            {{ year }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <button
                    v-for="(label, index) in months"
                    :key="label"
                    :data-active="index + 1 === currentMonth"
                    class="shrink-0 rounded-full px-4 py-2 text-sm whitespace-nowrap transition-colors"
                    :class="[
                        index + 1 === currentMonth
                            ? 'bg-primary font-medium text-primary-foreground'
                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground',
                        isToday(currentYear, index + 1) &&
                        index + 1 !== currentMonth
                            ? 'ring-1 ring-primary/40'
                            : '',
                    ]"
                    :aria-current="
                        index + 1 === currentMonth ? 'true' : undefined
                    "
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
                    tone="income"
                    label="Entrate"
                    :amount="incomeActual"
                    :expected="incomePlanned"
                    expected-label="attese"
                />
                <BudgetSummaryRing
                    class="z-10 -translate-y-4"
                    size="lg"
                    tone="accent"
                    label="Saldo"
                    :amount="balanceActual"
                    :expected="balancePlanned"
                    expected-label="atteso"
                />
                <BudgetSummaryRing
                    class="-ml-6"
                    tone="expense"
                    label="Uscite"
                    :amount="expenseActual"
                    :expected="expensePlanned"
                    expected-label="attese"
                />
            </div>

            <div class="hidden gap-4 sm:grid sm:grid-cols-3">
                <div class="rounded-2xl bg-muted/60 px-4 py-3 dark:bg-muted/50">
                    <p class="text-xs text-muted-foreground">
                        Entrate incassate
                    </p>
                    <p class="text-2xl font-bold tabular-nums">
                        {{ formatAmount(incomeActual) }}
                    </p>
                    <p class="text-xs text-muted-foreground tabular-nums">
                        attese {{ formatAmount(incomePlanned) }}
                    </p>
                </div>
                <div class="rounded-2xl bg-muted/60 px-4 py-3 dark:bg-muted/50">
                    <p class="text-xs text-muted-foreground">Uscite</p>
                    <p class="text-2xl font-bold tabular-nums">
                        {{ formatAmount(expenseActual) }}
                    </p>
                    <p class="text-xs text-muted-foreground tabular-nums">
                        attese {{ formatAmount(expensePlanned) }}
                    </p>
                </div>
                <div class="rounded-2xl bg-muted/60 px-4 py-3 dark:bg-muted/50">
                    <p class="text-xs text-muted-foreground">Saldo effettivo</p>
                    <p
                        class="text-2xl font-bold tabular-nums"
                        :class="
                            balanceActual >= 0
                                ? 'text-green-600'
                                : 'text-red-500'
                        "
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
                    <span class="text-sm text-muted-foreground"
                        >Uscite sulle entrate</span
                    >
                    <span class="text-sm font-semibold tabular-nums"
                        >{{ budgetUsage.toFixed(0) }}%</span
                    >
                </div>
                <div class="h-2.5 overflow-hidden rounded-sm bg-foreground/15">
                    <div
                        class="h-full rounded-sm transition-all"
                        :style="{
                            width: `${Math.min(budgetUsage, 100)}%`,
                            backgroundColor:
                                budgetUsage > 100 ? '#ef4444' : '#10b981',
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
                    <span class="text-base font-normal text-muted-foreground"
                        >(EUR)</span
                    >
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

            <p
                v-else
                class="px-5 py-6 text-center text-sm text-muted-foreground"
            >
                {{ section.emptyLabel }}
            </p>

            <div
                v-if="section.categories.length"
                class="flex items-baseline justify-between gap-4 border-t border-border/60 px-5 py-4 font-semibold"
            >
                <span>{{ section.totalLabel }}</span>
                <span
                    class="tabular-nums"
                    :class="
                        isOverspent(
                            section.direction,
                            section.planned - section.actual,
                        )
                            ? 'text-red-500'
                            : ''
                    "
                >
                    {{
                        residualLabel(
                            section.direction,
                            section.planned - section.actual,
                        )
                    }}
                    <span
                        :class="
                            isOverspent(
                                section.direction,
                                section.planned - section.actual,
                            )
                                ? ''
                                : 'font-normal text-muted-foreground'
                        "
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

                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 shrink-0"
                    :title="
                        activeFilterCount
                            ? `${activeFilterCount} filtri attivi`
                            : 'Filtra i movimenti'
                    "
                    @click="isFiltersOpen = true"
                >
                    <SlidersHorizontal class="size-4 sm:mr-1" />
                    <span class="hidden sm:inline">Filtri</span>
                    <span
                        v-if="activeFilterCount"
                        class="ml-1 flex size-5 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-primary-foreground tabular-nums"
                    >
                        {{ activeFilterCount }}
                    </span>
                </Button>
            </div>

            <div
                v-if="isTransactionsOpen && transactionDays.length"
                class="divide-y"
            >
                <div v-for="day in transactionDays" :key="day.label">
                    <div
                        class="flex items-center justify-between gap-4 bg-foreground/5 px-5 py-2"
                    >
                        <span
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ day.label }}
                        </span>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ formatAmount(day.total) }}</span
                        >
                    </div>

                    <div
                        v-for="transaction in day.items"
                        :key="`${transaction.kind}-${transaction.id}`"
                        class="group flex items-center gap-3 px-5 py-3 hover:bg-foreground/5"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full"
                            :style="{
                                backgroundColor:
                                    transaction.kind === 'transfer'
                                        ? undefined
                                        : `${transaction.category_color ?? '#3b82f6'}20`,
                            }"
                            :class="
                                transaction.kind === 'transfer'
                                    ? 'bg-muted-foreground/15'
                                    : ''
                            "
                        >
                            <ArrowRightLeft
                                v-if="transaction.kind === 'transfer'"
                                class="size-4 text-muted-foreground"
                            />
                            <ArrowDownLeft
                                v-else-if="transaction.direction === 'income'"
                                class="size-4 text-green-600"
                            />
                            <ArrowUpRight
                                v-else
                                class="size-4"
                                :style="{
                                    color:
                                        transaction.category_color ?? '#3b82f6',
                                }"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <span class="truncate">
                                    {{
                                        transaction.description ||
                                        (transaction.kind === 'transfer'
                                            ? 'Trasferimento'
                                            : transaction.subcategory_name)
                                    }}
                                </span>
                                <span
                                    v-if="
                                        isSharedBudget &&
                                        recordedByLabel(transaction)
                                    "
                                    class="max-w-24 shrink-0 truncate rounded-full px-2 py-0.5 text-[11px] font-semibold text-white"
                                    :style="recordedByStyle(transaction)"
                                    :title="`Registrato da ${transaction.recorded_by_name}`"
                                >
                                    {{ recordedByLabel(transaction) }}
                                </span>
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                <template
                                    v-if="transaction.kind === 'transfer'"
                                >
                                    {{
                                        transaction.account_name ??
                                        'Non indicato'
                                    }}
                                    →
                                    {{
                                        transaction.to_account_name ??
                                        'Non indicato'
                                    }}
                                    ·
                                </template>
                                <template v-else>
                                    {{ transaction.category_name }} ·
                                    {{ transaction.subcategory_name }} ·
                                    {{
                                        transaction.account_name ??
                                        'Non indicato'
                                    }}
                                    ·
                                </template>
                                {{ timeLabel(transaction.recorded_at) }}
                            </p>
                        </div>

                        <p
                            v-if="transaction.kind === 'transfer'"
                            class="shrink-0 text-sm font-semibold text-muted-foreground tabular-nums"
                        >
                            {{ formatAmount(transaction.amount) }}
                        </p>

                        <p
                            v-else
                            class="shrink-0 text-sm font-semibold tabular-nums"
                            :class="
                                transaction.direction === 'income'
                                    ? 'text-green-600'
                                    : ''
                            "
                        >
                            {{ transaction.direction === 'income' ? '+' : ''
                            }}{{ formatAmount(signedAmount(transaction)) }}
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
                                <DropdownMenuItem
                                    @click="openEditMovement(transaction)"
                                >
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
                                class="text-muted-foreground opacity-60 transition-opacity group-hover:opacity-100 hover:text-foreground focus-visible:opacity-100"
                                :title="`Modifica il movimento di ${formatCurrency(transaction.amount)}`"
                                @click="openEditMovement(transaction)"
                            >
                                <Pencil class="size-4" />
                            </Button>

                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground opacity-60 transition-opacity group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive focus-visible:opacity-100"
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
                <template v-if="activeFilterCount === 0">
                    <p class="text-sm text-muted-foreground">
                        Nessun movimento registrato questo mese.
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Usa il pulsante in basso a destra per aggiungerne uno.
                    </p>
                </template>
                <template v-else>
                    <p class="text-sm text-muted-foreground">
                        Nessun movimento con questi filtri.
                    </p>
                    <Button variant="link" size="sm" @click="resetFilters">
                        Azzera i filtri
                    </Button>
                </template>
            </div>
        </div>
    </div>

    <!-- Bottone fisso: nuovo movimento -->
    <Button
        size="icon"
        class="fixed right-4 bottom-22 z-40 size-14 rounded-full shadow-lg md:right-6 md:bottom-6"
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
        :show-recorded-by="isSharedBudget"
        :month-label="monthLabel"
        :year="currentYear"
        :month="currentMonth"
        @update-planned="updateBudgetLine"
        @flush="flushSave()"
        @add-subcategory="addSubcategoryFromManager"
        @delete-subcategory="
            managedCategory && deleteSubcategory(managedCategory, $event)
        "
        @delete-category="deleteCategoryFromManager"
        @delete-transaction="deleteTransaction"
    />

    <!-- Il pannello dei filtri: stesso foglio dei movimenti -->
    <Dialog v-model:open="isFiltersOpen">
        <DialogContent
            class="sheet-panel top-auto bottom-0 left-0 flex w-full max-w-none translate-x-0 translate-y-0 flex-col gap-0 overflow-hidden rounded-t-2xl rounded-b-none border-x-0 border-b-0 p-0 data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:max-w-md sm:translate-x-[-50%] sm:translate-y-[-50%] sm:rounded-2xl sm:border max-sm:[&>[data-slot=dialog-close]]:hidden"
            :style="filtersDragStyle"
        >
            <div
                class="shrink-0 touch-none select-none sm:cursor-default"
                @pointerdown="startFiltersDrag"
                @pointermove="moveFiltersDrag"
                @pointerup="endFiltersDrag"
                @pointercancel="endFiltersDrag"
            >
                <div class="flex justify-center pt-3 pb-2 sm:hidden">
                    <span
                        class="h-1.5 w-10 rounded-full bg-muted-foreground/30"
                    />
                </div>

                <DialogHeader
                    class="p-0 sm:border-b sm:border-border/60 sm:px-12 sm:py-4"
                >
                    <DialogTitle
                        class="sr-only text-base font-semibold sm:not-sr-only sm:text-center"
                    >
                        Filtra i movimenti di {{ monthLabel }}
                    </DialogTitle>
                </DialogHeader>
            </div>

            <div
                class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-background"
            >
                <p
                    class="px-4 pt-5 pb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Tipo
                </p>

                <!-- Le stesse pillole del pannello "Nuovo movimento", ma qui
                     se ne possono accendere anche due insieme. -->
                <div class="bg-muted/60 px-4 py-4 dark:bg-muted/40">
                    <div class="flex gap-1 rounded-full bg-foreground/5 p-1">
                        <button
                            v-for="kind in movementKinds"
                            :key="kind.value"
                            class="flex-1 rounded-full px-2 py-2.5 text-sm transition-colors"
                            :class="
                                filters.kinds.includes(kind.value)
                                    ? 'bg-foreground/20 font-semibold text-foreground shadow-sm'
                                    : 'font-medium text-muted-foreground hover:text-foreground'
                            "
                            @click="toggleKind(kind.value)"
                        >
                            {{ kind.label }}
                        </button>
                    </div>
                </div>

                <button
                    class="flex w-full items-center gap-2 px-4 pt-5 pb-2 text-left"
                    :aria-expanded="isFilterSectionOpen('accounts')"
                    @click="toggleFilterSection('accounts')"
                >
                    <span
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Metodo di pagamento
                    </span>
                    <span class="ml-auto text-[13px] text-muted-foreground">
                        {{ chosenLabel(filters.accounts.length) }}
                    </span>
                    <ChevronDown
                        class="size-4 shrink-0 text-muted-foreground/60 transition-transform"
                        :class="
                            isFilterSectionOpen('accounts') ? '' : '-rotate-90'
                        "
                    />
                </button>

                <div
                    v-if="isFilterSectionOpen('accounts')"
                    class="ios-group bg-muted/60 dark:bg-muted/40"
                >
                    <button
                        v-for="account in filterAccounts"
                        :key="String(account.value)"
                        class="flex w-full items-center gap-4 px-4 py-4 text-left"
                        @click="toggleAccount(account.value)"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl text-white"
                            :style="{
                                backgroundColor: account.color ?? undefined,
                            }"
                            :class="
                                account.color
                                    ? ''
                                    : 'bg-muted-foreground/15 text-muted-foreground'
                            "
                        >
                            <component
                                :is="
                                    account.value === CASH
                                        ? CircleDashed
                                        : accountIcon(account.icon)
                                "
                                class="size-4"
                            />
                        </span>
                        <span class="min-w-0 flex-1 truncate text-[15px]">{{
                            account.name
                        }}</span>
                        <span
                            class="flex size-5 shrink-0 items-center justify-center rounded-md border"
                            :class="
                                filters.accounts.includes(account.value)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-input'
                            "
                        >
                            <Check
                                v-if="filters.accounts.includes(account.value)"
                                class="size-3.5"
                            />
                        </span>
                    </button>
                </div>

                <template v-if="transactionCategories.length">
                    <button
                        class="flex w-full items-center gap-2 px-4 pt-5 pb-2 text-left"
                        :aria-expanded="isFilterSectionOpen('categories')"
                        @click="toggleFilterSection('categories')"
                    >
                        <span
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Categorie
                        </span>
                        <span class="ml-auto text-[13px] text-muted-foreground">
                            {{ chosenLabel(filters.categories.length) }}
                        </span>
                        <ChevronDown
                            class="size-4 shrink-0 text-muted-foreground/60 transition-transform"
                            :class="
                                isFilterSectionOpen('categories')
                                    ? ''
                                    : '-rotate-90'
                            "
                        />
                    </button>

                    <div
                        v-if="isFilterSectionOpen('categories')"
                        class="ios-group bg-muted/60 dark:bg-muted/40"
                    >
                        <button
                            v-for="category in transactionCategories"
                            :key="category.id"
                            class="flex w-full items-center gap-4 px-4 py-4 text-left"
                            @click="toggleCategoryFilter(category.id)"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl"
                                :style="{
                                    backgroundColor: `${category.color}26`,
                                }"
                            >
                                <span
                                    class="size-3 rounded-full"
                                    :style="{ backgroundColor: category.color }"
                                />
                            </span>
                            <span class="min-w-0 flex-1 truncate text-[15px]">{{
                                category.name
                            }}</span>
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded-md border"
                                :class="
                                    filters.categories.includes(category.id)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-input'
                                "
                            >
                                <Check
                                    v-if="
                                        filters.categories.includes(category.id)
                                    "
                                    class="size-3.5"
                                />
                            </span>
                        </button>
                    </div>
                </template>

                <p
                    class="px-4 pt-5 pb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Periodo
                </p>

                <div class="ios-group bg-muted/60 dark:bg-muted/40">
                    <div class="flex items-center gap-4 px-4 py-4">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                        >
                            <CalendarDays
                                class="size-4 text-muted-foreground"
                            />
                        </span>
                        <span class="shrink-0 text-[15px]">Dal giorno</span>
                        <Input
                            v-model="filters.fromDay"
                            type="date"
                            aria-label="Dal giorno"
                            :min="monthBounds.from"
                            :max="monthBounds.to"
                            class="ml-auto h-9 w-auto min-w-0 border-0 bg-transparent px-1 text-right text-sm text-muted-foreground shadow-none focus-visible:ring-0 dark:bg-transparent"
                        />
                    </div>

                    <div class="flex items-center gap-4 px-4 py-4">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                        >
                            <CalendarDays
                                class="size-4 text-muted-foreground"
                            />
                        </span>
                        <span class="shrink-0 text-[15px]">Al giorno</span>
                        <Input
                            v-model="filters.toDay"
                            type="date"
                            aria-label="Al giorno"
                            :min="monthBounds.from"
                            :max="monthBounds.to"
                            class="ml-auto h-9 w-auto min-w-0 border-0 bg-transparent px-1 text-right text-sm text-muted-foreground shadow-none focus-visible:ring-0 dark:bg-transparent"
                        />
                    </div>
                </div>

                <p class="px-4 pt-2 text-xs text-muted-foreground">
                    Dentro a {{ monthLabel.toLowerCase() }}: per gli altri mesi
                    usa le pillole in cima alla pagina.
                </p>
            </div>

            <div
                class="flex shrink-0 items-center gap-2 border-t border-border/60 p-4 pb-[calc(1rem_+_env(safe-area-inset-bottom))] sm:pb-4"
            >
                <Button
                    variant="ghost"
                    class="h-12 shrink-0"
                    :disabled="activeFilterCount === 0"
                    @click="resetFilters"
                >
                    Azzera
                </Button>
                <Button
                    class="h-12 flex-1 text-base"
                    @click="isFiltersOpen = false"
                >
                    Mostra {{ filteredTransactions.length }}
                    {{
                        filteredTransactions.length === 1
                            ? 'movimento'
                            : 'movimenti'
                    }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Sheet: Aggiungi Categoria -->
    <Sheet v-model:open="isAddCategoryOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>
                    {{ categoryForm.type === 'income' ? 'Entrata' : 'Uscita' }}
                    solo per {{ monthLabel }}
                </SheetTitle>
            </SheetHeader>

            <div class="space-y-4 px-4 pb-6">
                <div class="grid gap-2">
                    <Label for="category-name">Nome</Label>
                    <Input
                        id="category-name"
                        v-model="categoryForm.name"
                        :placeholder="
                            categoryForm.type === 'income'
                                ? 'Es. Stipendio'
                                : 'Es. Bollette'
                        "
                        autofocus
                    />
                    <p
                        v-if="categoryForm.errors.name"
                        class="text-sm text-destructive"
                    >
                        {{ categoryForm.errors.name }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label>Colore</Label>
                    <BudgetColorPicker v-model="categoryForm.color" />
                </div>

                <Button
                    class="mt-6 w-full"
                    :disabled="categoryForm.processing"
                    @click="submitCategory"
                >
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
                    {{
                        selectedCategory?.type === 'income'
                            ? 'Voce'
                            : 'Sottocategoria'
                    }}
                    solo per {{ monthLabel }}
                </DialogTitle>
            </DialogHeader>

            <div class="mt-2 space-y-4">
                <div class="grid gap-2">
                    <Label for="subcategory-name">Nome</Label>
                    <Input
                        id="subcategory-name"
                        v-model="subcategoryForm.name"
                        :placeholder="
                            selectedCategory?.type === 'income'
                                ? 'Es. tredicesima'
                                : 'Es. enel energia'
                        "
                        autofocus
                    />
                    <p
                        v-if="subcategoryForm.errors.name"
                        class="text-sm text-destructive"
                    >
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

    <!-- Dialog: nuovo movimento -->
    <Dialog v-model:open="isAddMovementOpen">
        <DialogContent
            class="sheet-panel top-auto bottom-0 left-0 flex w-full max-w-none translate-x-0 translate-y-0 flex-col gap-0 overflow-hidden rounded-t-2xl rounded-b-none border-x-0 border-b-0 p-0 data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:max-w-md sm:translate-x-[-50%] sm:translate-y-[-50%] sm:rounded-2xl sm:border max-sm:[&>[data-slot=dialog-close]]:hidden"
            :style="sheetDragStyle"
        >
            <!-- La maniglia: si afferra e si butta giù per chiudere -->
            <div
                class="shrink-0 touch-none select-none sm:cursor-default"
                @pointerdown="startSheetDrag"
                @pointermove="moveSheetDrag"
                @pointerup="endSheetDrag"
                @pointercancel="endSheetDrag"
            >
                <div class="flex justify-center pt-3 pb-2 sm:hidden">
                    <span
                        class="h-1.5 w-10 rounded-full bg-muted-foreground/30"
                    />
                </div>

                <!--
                    Sul telefono il titolo resta solo per chi legge con lo
                    screen reader: la maniglia dice già che foglio è, e le tre
                    pillole qui sotto dicono che cosa si sta scrivendo.
                -->
                <DialogHeader
                    class="p-0 sm:border-b sm:border-border/60 sm:px-12 sm:py-4"
                >
                    <DialogTitle
                        class="sr-only text-base font-semibold sm:not-sr-only sm:text-center"
                    >
                        {{ dialogTitle }} · {{ monthLabel }}
                    </DialogTitle>
                </DialogHeader>
            </div>

            <div
                class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-background"
            >
                <!-- Tipo e importo stanno insieme, su una fascia sola -->
                <div class="bg-muted/60 px-4 pt-4 pb-2 dark:bg-muted/40">
                    <div class="flex gap-1 rounded-full bg-foreground/5 p-1">
                        <button
                            v-for="kind in movementKinds"
                            :key="kind.value"
                            class="flex-1 rounded-full px-2 py-2.5 text-sm transition-colors"
                            :class="
                                movementKind === kind.value
                                    ? 'bg-foreground/20 font-semibold text-foreground shadow-sm'
                                    : isKindLocked(kind.value)
                                      ? 'cursor-not-allowed font-medium text-muted-foreground/40'
                                      : 'font-medium text-muted-foreground hover:text-foreground'
                            "
                            :disabled="isKindLocked(kind.value)"
                            :title="
                                isKindLocked(kind.value)
                                    ? 'Per cambiare tipo elimina questo movimento e riscrivilo'
                                    : undefined
                            "
                            @click="setMovementKind(kind.value)"
                        >
                            {{ kind.label }}
                        </button>
                    </div>

                    <!-- L'importo: la cifra è la prima cosa che si digita -->
                    <div class="flex items-center gap-3 py-5">
                        <span
                            class="shrink-0 rounded-full bg-background/70 px-4 py-2 text-sm font-medium text-muted-foreground dark:bg-background/50"
                        >
                            EUR
                        </span>
                        <label
                            class="flex min-w-0 flex-1 items-center justify-end gap-1 text-4xl font-semibold tabular-nums sm:text-5xl"
                            :class="amountTone"
                        >
                            <span v-if="amountSign" class="shrink-0">{{
                                amountSign
                            }}</span>
                            <input
                                v-model="movementForm.amount"
                                inputmode="decimal"
                                placeholder="0"
                                aria-label="Importo"
                                class="w-full min-w-0 bg-transparent text-right text-current outline-none placeholder:text-current placeholder:opacity-40"
                            />
                        </label>
                    </div>

                    <p
                        v-if="movementForm.errors.amount"
                        class="pb-2 text-right text-sm text-destructive"
                    >
                        {{ movementForm.errors.amount }}
                    </p>
                </div>

                <p
                    class="px-4 pt-5 pb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Generale
                </p>

                <div class="ios-group bg-muted/60 dark:bg-muted/40">
                    <!-- Il conto di partenza -->
                    <Select v-model="movementAccountId">
                        <SelectTrigger
                            class="h-auto w-full rounded-none border-0 bg-transparent px-4 py-5 shadow-none focus-visible:ring-0 dark:bg-transparent dark:hover:bg-transparent [&>svg]:hidden"
                        >
                            <span class="flex min-w-0 items-center gap-4">
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                                >
                                    <Wallet
                                        class="size-4 text-muted-foreground"
                                    />
                                </span>
                                <span class="truncate text-[15px]">{{
                                    accountLabel
                                }}</span>
                            </span>
                            <span
                                class="ml-auto flex min-w-0 items-center gap-1.5"
                            >
                                <span
                                    class="truncate text-[15px] text-muted-foreground"
                                    >{{ accountValueLabel }}</span
                                >
                                <ChevronRight
                                    class="size-4 shrink-0 text-muted-foreground/60"
                                />
                            </span>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="account in movementAccountOptions"
                                :key="account.id"
                                :value="account.id"
                            >
                                <span class="flex items-center gap-2">
                                    <span
                                        class="flex size-5 items-center justify-center rounded text-white"
                                        :style="{
                                            backgroundColor:
                                                account.color ?? '#3b82f6',
                                        }"
                                    >
                                        <component
                                            :is="accountIcon(account.icon)"
                                            class="size-3"
                                        />
                                    </span>
                                    {{ account.name }}
                                    <span class="text-xs text-muted-foreground">
                                        {{
                                            accountTypeLabels[account.type] ??
                                            account.type
                                        }}
                                    </span>
                                </span>
                            </SelectItem>
                            <SelectItem :value="CASH">
                                <span class="flex items-center gap-2">
                                    <span
                                        class="flex size-5 items-center justify-center rounded bg-muted-foreground/20"
                                    >
                                        <CircleDashed class="size-3" />
                                    </span>
                                    Non indicato
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- L'altro capo del giro: un conto, oppure i contanti
                         quando si preleva allo sportello. -->
                    <Select v-if="isTransfer" v-model="transferAccountId">
                        <SelectTrigger
                            class="h-auto w-full rounded-none border-0 bg-transparent px-4 py-5 shadow-none focus-visible:ring-0 dark:bg-transparent dark:hover:bg-transparent [&>svg]:hidden"
                        >
                            <span class="flex min-w-0 items-center gap-4">
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                                >
                                    <ArrowRightLeft
                                        class="size-4 text-muted-foreground"
                                    />
                                </span>
                                <span class="truncate text-[15px]"
                                    >Al conto</span
                                >
                            </span>
                            <span
                                class="ml-auto flex min-w-0 items-center gap-1.5"
                            >
                                <span
                                    class="truncate text-[15px] text-muted-foreground"
                                    >{{ transferValueLabel }}</span
                                >
                                <ChevronRight
                                    class="size-4 shrink-0 text-muted-foreground/60"
                                />
                            </span>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="account in transferAccountOptions"
                                :key="account.id"
                                :value="account.id"
                            >
                                <span class="flex items-center gap-2">
                                    <span
                                        class="flex size-5 items-center justify-center rounded text-white"
                                        :style="{
                                            backgroundColor:
                                                account.color ?? '#3b82f6',
                                        }"
                                    >
                                        <component
                                            :is="accountIcon(account.icon)"
                                            class="size-3"
                                        />
                                    </span>
                                    {{ account.name }}
                                    <span class="text-xs text-muted-foreground">
                                        {{
                                            accountTypeLabels[account.type] ??
                                            account.type
                                        }}
                                    </span>
                                </span>
                            </SelectItem>
                            <SelectItem :value="CASH">
                                <span class="flex items-center gap-2">
                                    <span
                                        class="flex size-5 items-center justify-center rounded bg-muted-foreground/20"
                                    >
                                        <CircleDashed class="size-3" />
                                    </span>
                                    Non indicato
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <template v-if="!isTransfer">
                        <!-- La categoria -->
                        <Select v-model="movementCategoryId">
                            <SelectTrigger
                                class="h-auto w-full rounded-none border-0 bg-transparent px-4 py-5 shadow-none focus-visible:ring-0 dark:bg-transparent dark:hover:bg-transparent [&>svg]:hidden"
                            >
                                <span class="flex min-w-0 items-center gap-4">
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                                    >
                                        <Tag
                                            class="size-4 text-muted-foreground"
                                        />
                                    </span>
                                    <span class="truncate text-[15px]"
                                        >Categoria</span
                                    >
                                </span>
                                <span
                                    class="ml-auto flex min-w-0 items-center gap-2 text-[15px]"
                                    :class="
                                        movementCategory
                                            ? 'text-muted-foreground'
                                            : 'text-destructive'
                                    "
                                >
                                    <span
                                        v-if="movementCategory"
                                        class="size-2.5 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                movementCategory.color,
                                        }"
                                    />
                                    <span class="truncate">{{
                                        movementCategory?.name ?? 'Obbligatorio'
                                    }}</span>
                                    <ChevronRight
                                        class="size-4 shrink-0 text-muted-foreground/60"
                                    />
                                </span>
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
                                            :style="{
                                                backgroundColor: category.color,
                                            }"
                                        />
                                        {{ category.name }}
                                    </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <!-- La voce dentro alla categoria -->
                        <Select
                            v-model="movementForm.budget_subcategory_id"
                            :disabled="!movementCategory"
                        >
                            <SelectTrigger
                                class="h-auto w-full rounded-none border-0 bg-transparent px-4 py-5 shadow-none focus-visible:ring-0 disabled:opacity-100 dark:bg-transparent dark:hover:bg-transparent [&>svg]:hidden"
                            >
                                <span class="flex min-w-0 items-center gap-4">
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                                    >
                                        <Tags
                                            class="size-4 text-muted-foreground"
                                        />
                                    </span>
                                    <span class="truncate text-[15px]"
                                        >Voce</span
                                    >
                                </span>
                                <span
                                    class="ml-auto flex min-w-0 items-center gap-1.5 text-[15px]"
                                    :class="
                                        movementSubcategory
                                            ? 'text-muted-foreground'
                                            : movementCategory
                                              ? 'text-destructive'
                                              : 'text-muted-foreground'
                                    "
                                >
                                    <span class="truncate">
                                        {{
                                            movementSubcategory?.name ??
                                            (movementCategory
                                                ? 'Obbligatorio'
                                                : 'Scegli prima la categoria')
                                        }}
                                    </span>
                                    <ChevronRight
                                        class="size-4 shrink-0 text-muted-foreground/60"
                                    />
                                </span>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="sub in movementCategory?.subcategories ??
                                    []"
                                    :key="sub.id"
                                    :value="sub.id"
                                >
                                    {{ sub.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <!-- La voce che serve può non esistere ancora: si crea da qui. -->
                        <button
                            v-if="
                                movementCategory && !isAddingMovementSubcategory
                            "
                            class="flex w-full items-center gap-4 px-4 py-5 text-left"
                            @click="isAddingMovementSubcategory = true"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                            >
                                <Plus class="size-4 text-muted-foreground" />
                            </span>
                            <span
                                class="truncate text-[15px] text-muted-foreground"
                            >
                                Nuova voce in {{ movementCategory.name }}
                            </span>
                        </button>

                        <div
                            v-else-if="movementCategory"
                            class="space-y-2 px-4 py-3"
                        >
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
                                    :disabled="
                                        quickSubcategoryForm.processing ||
                                        quickSubcategoryForm.name.trim() === ''
                                    "
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
                            <p
                                v-if="quickSubcategoryForm.errors.name"
                                class="text-sm text-destructive"
                            >
                                {{ quickSubcategoryForm.errors.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Vale solo per {{ monthLabel.toLowerCase() }}.
                            </p>
                        </div>
                    </template>

                    <!-- Descrizione -->
                    <div class="flex items-center gap-4 px-4 py-5">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                        >
                            <PenLine class="size-4 text-muted-foreground" />
                        </span>
                        <span class="shrink-0 text-[15px]">Descrizione</span>
                        <Input
                            v-model="movementForm.description"
                            placeholder="Facoltativa"
                            aria-label="Descrizione"
                            class="h-9 border-0 bg-transparent text-right text-[15px] shadow-none focus-visible:ring-0 dark:bg-transparent"
                        />
                    </div>
                </div>

                <p
                    v-if="movementForm.errors.budget_subcategory_id"
                    class="px-4 pt-2 text-sm text-destructive"
                >
                    {{ movementForm.errors.budget_subcategory_id }}
                </p>

                <p
                    v-if="
                        isTransfer &&
                        movementForm.errors.to_financial_account_id
                    "
                    class="px-4 pt-2 text-sm text-destructive"
                >
                    {{ movementForm.errors.to_financial_account_id }}
                </p>

                <p
                    v-else-if="isTransfer"
                    class="px-4 pt-2 text-xs text-muted-foreground"
                >
                    I soldi cambiano tasca: il mese non li conta né come entrata
                    né come uscita.
                </p>

                <Link
                    v-if="accounts.length === 0"
                    :href="
                        budgetAccounts.index.url({ query: sharedBudgetQuery })
                    "
                    class="block px-4 pt-2 text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
                >
                    Nessun conto: aggiungi le tue carte
                </Link>

                <p
                    v-if="!isTransfer && movementCategories.length === 0"
                    class="px-4 pt-2 text-xs text-muted-foreground"
                >
                    Nessuna categoria di
                    {{ movementDirection === 'income' ? 'entrata' : 'uscita' }}:
                    creane una dalla sezione qui sopra.
                </p>

                <p
                    class="px-4 pt-5 pb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Dettagli
                </p>

                <div class="ios-group bg-muted/60 dark:bg-muted/40">
                    <div class="flex items-center gap-4 px-4 py-5">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted-foreground/15"
                        >
                            <CalendarDays
                                class="size-4 text-muted-foreground"
                            />
                        </span>
                        <span class="shrink-0 text-[15px]">Data e ora</span>
                        <div class="ml-auto flex min-w-0 items-center gap-1">
                            <Input
                                v-model="movementDate"
                                type="date"
                                aria-label="Data"
                                class="h-9 w-auto min-w-0 border-0 bg-transparent px-1 text-right text-sm text-muted-foreground shadow-none focus-visible:ring-0 dark:bg-transparent"
                            />
                            <Input
                                v-model="movementTime"
                                type="time"
                                aria-label="Ora"
                                class="h-9 w-auto min-w-0 border-0 bg-transparent px-1 text-right text-sm text-muted-foreground shadow-none focus-visible:ring-0 dark:bg-transparent"
                            />
                        </div>
                    </div>
                </div>

                <p
                    v-if="movementForm.errors.recorded_at"
                    class="px-4 pt-2 text-sm text-destructive"
                >
                    {{ movementForm.errors.recorded_at }}
                </p>

                <!-- Come sta andando la voce scelta, prima di confermare -->
                <p
                    v-if="movementSubcategory"
                    class="px-4 py-3 text-xs text-muted-foreground"
                >
                    <template v-if="movementDirection === 'income'">
                        Atteso
                        {{ formatAmount(planned(movementSubcategory.id)) }} ·
                        già incassato
                        {{ formatAmount(actual(movementSubcategory.id)) }}
                    </template>
                    <template v-else>
                        Atteso
                        {{ formatAmount(planned(movementSubcategory.id)) }} ·
                        già speso
                        {{ formatAmount(actual(movementSubcategory.id)) }} ·
                        rimane
                        <span
                            :class="
                                planned(movementSubcategory.id) -
                                    actual(movementSubcategory.id) >=
                                0
                                    ? 'text-green-600'
                                    : 'text-red-500'
                            "
                        >
                            {{
                                formatAmount(
                                    planned(movementSubcategory.id) -
                                        actual(movementSubcategory.id),
                                )
                            }}
                        </span>
                    </template>
                </p>
            </div>

            <div
                class="shrink-0 border-t border-border/60 p-4 pb-[calc(1rem_+_env(safe-area-inset-bottom))] sm:pb-4"
            >
                <Button
                    class="h-12 w-full text-base"
                    :disabled="movementForm.processing || !canSubmitMovement"
                    @click="submitMovement"
                >
                    {{ submitLabel }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/*
 * Divisori come su un elenco di iPhone: partono dopo l'icona invece che dal
 * bordo, così le righe si leggono come un blocco solo.
 */
.ios-group > * + * {
    position: relative;
}

.ios-group > * + *::before {
    content: '';
    position: absolute;
    top: 0;
    left: 4rem;
    right: 0;
    height: 1px;
    background-color: var(--border);
}
</style>
