import {
    BookOpen,
    Building,
    Building2,
    CalendarDays,
    ChartCandlestick,
    ClipboardCheck,
    Clock,
    CreditCard,
    Dumbbell,
    HeartPulse,
    KanbanSquare,
    KeyRound,
    Landmark,
    LineChart,
    ListChecks,
    PiggyBank,
    Scale,
    ShoppingCart,
    Sprout,
    Tag,
    Tags,
    Target,
    TrendingDown,
    TrendingUp,
    UtensilsCrossed,
    Wallet,
} from '@lucide/vue';
import balanceSheetOverview from '@/routes/balance-sheet';
import * as balanceSheetAssets from '@/routes/balance-sheet/assets';
import * as balanceSheetEntries from '@/routes/balance-sheet/entries';
import * as budgetAccounts from '@/routes/budget-accounts';
import * as budgetCategories from '@/routes/budget-categories';
import * as monthlyBudgets from '@/routes/monthly-budgets';
import * as budgetExpenses from '@/routes/budget-expenses';
import * as budgets from '@/routes/budgets';
import * as calendar from '@/routes/calendar';
import * as cards from '@/routes/cards';
import * as categories from '@/routes/categories';
import * as categoryRules from '@/routes/category-rules';
import * as companyAnalyses from '@/routes/company-analyses';
import * as investments from '@/routes/investments';
import * as life from '@/routes/life';
import * as macro from '@/routes/macro';
import * as market from '@/routes/market';
import * as meals from '@/routes/meals';
import * as passwords from '@/routes/passwords';
import * as shoppingLists from '@/routes/shopping-lists';
import * as tasks from '@/routes/tasks';
import * as workouts from '@/routes/workouts';
import type { NavItem } from '@/types';

// The `key` on each hideable group/item is the stable id used by
// useSidebarModules() to remember what the user turned off - it's
// persisted to localStorage, so it must stay stable across renames of
// `title` (which is just the display label).
export const mainNavItems: NavItem[] = [
    {
        title: 'Finanza',
        key: 'finance',
        icon: Wallet,
        items: [
            {
                title: 'Categorie',
                key: 'finance.categories',
                href: categories.index(),
                icon: Tag,
            },
            {
                title: 'Regole categorie',
                key: 'finance.category-rules',
                href: categoryRules.index(),
                icon: Tags,
            },
            {
                title: 'Carte',
                key: 'finance.cards',
                href: cards.index(),
                icon: CreditCard,
            },
            {
                title: 'Budget',
                key: 'finance.budgets',
                href: budgets.index(),
                icon: PiggyBank,
            },
        ],
    },
    {
        title: 'Bilancio patrimoniale',
        key: 'balance-sheet',
        icon: Scale,
        items: [
            {
                title: 'Libro contabile',
                key: 'balance-sheet.ledger',
                href: balanceSheetOverview.ledger(),
                icon: BookOpen,
            },
            {
                title: 'Budget Mensile',
                key: 'balance-sheet.budget.dashboard',
                href: monthlyBudgets.index(),
                icon: PiggyBank,
            },
            {
                title: 'Registra Spese',
                key: 'balance-sheet.budget.expenses',
                href: budgetExpenses.index(),
                icon: BookOpen,
            },
            {
                title: 'Conti e carte',
                key: 'balance-sheet.budget.accounts',
                href: budgetAccounts.index(),
                icon: CreditCard,
            },
            {
                title: 'Configurazione',
                key: 'balance-sheet.budget.config',
                href: budgetCategories.index(),
                icon: PiggyBank,
            },
        ],
    },
    {
        title: 'Investimenti',
        key: 'investments',
        icon: LineChart,
        items: [
            {
                title: 'Investimenti personali',
                key: 'investments.portfolio',
                href: investments.index(),
                icon: TrendingUp,
            },
            {
                title: 'Mercato',
                key: 'investments.market',
                href: market.index(),
                icon: ChartCandlestick,
            },
            {
                title: 'Macro',
                key: 'investments.macro',
                href: macro.index(),
                icon: Landmark,
            },
            {
                title: 'Analisi aziendale',
                key: 'investments.company-analyses',
                href: companyAnalyses.index(),
                icon: ClipboardCheck,
            },
        ],
    },
    {
        title: 'Produttività',
        key: 'productivity',
        icon: ListChecks,
        items: [
            {
                title: 'Task',
                key: 'productivity.tasks',
                href: tasks.index(),
                icon: KanbanSquare,
            },
            {
                title: 'Calendario',
                key: 'productivity.calendar',
                href: calendar.index(),
                icon: CalendarDays,
            },
            {
                title: 'Password',
                key: 'productivity.passwords',
                href: passwords.index(),
                icon: KeyRound,
            },
        ],
    },
    {
        title: 'Vita',
        key: 'life',
        icon: HeartPulse,
        items: [
            {
                title: 'Pasti',
                key: 'life.meals',
                href: meals.index(),
                icon: UtensilsCrossed,
            },
            {
                title: 'Lista della spesa',
                key: 'life.shopping-lists',
                href: shoppingLists.index(),
                icon: ShoppingCart,
            },
            {
                title: 'Allenamenti',
                key: 'life.workouts',
                href: workouts.index(),
                icon: Dumbbell,
            },
            {
                title: 'Ricordi',
                key: 'life.memories',
                href: life.index(),
                icon: Sprout,
            },
        ],
    },
];
