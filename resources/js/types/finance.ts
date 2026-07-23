export type AccountType =
    'checking' | 'debit_card' | 'credit_card' | 'prepaid_card' | 'cash';

export type CardType = 'debit' | 'credit' | 'prepaid';

export type Card = {
    id: number;
    user_id: number;
    financial_account_id: number | null;
    name: string;
    type: CardType;
    last_four_digits: string | null;
    circuit: string | null;
    color: string | null;
    icon: string | null;
    owner_name: string | null;
    iban: string | null;
    is_active: boolean;
    financial_account?: Pick<
        FinancialAccount,
        'id' | 'name' | 'bank_name' | 'color' | 'currency'
    > | null;
};

export type FinancialAccount = {
    id: number;
    user_id: number;
    name: string;
    type: AccountType;
    bank_name: string | null;
    currency: string;
    initial_balance: string;
    color: string | null;
    icon: string | null;
    is_active: boolean;
    cards?: Card[];
};

export type TransactionCategory = {
    id: number;
    user_id: number | null;
    name: string;
    color: string | null;
    icon: string | null;
    is_system: boolean;
};

export type TransactionDirection = 'income' | 'expense';

export type Transaction = {
    id: number;
    financial_account_id: number | null;
    card_id: number | null;
    transaction_category_id: number | null;
    transaction_date: string;
    description: string;
    amount: string;
    direction: TransactionDirection;
    category?: TransactionCategory | null;
};

export type CategoryBreakdownItem = {
    category_id: number | null;
    name: string;
    color: string | null;
    amount: number;
};

export type CategoryRule = {
    id: number;
    user_id: number;
    transaction_category_id: number;
    pattern: string;
    priority: number;
    times_applied: number;
    is_active: boolean;
    category?: TransactionCategory;
};

export type MonthlyOverview = {
    month: number;
    income: number;
    expense: number;
};

export type YearlyOverview = {
    year: number;
    months: MonthlyOverview[];
    totals: {
        income: number;
        expense: number;
    };
    categoryBreakdown: CategoryBreakdownItem[];
};

export type OverviewTab = {
    id: string;
    name: string;
    overview: YearlyOverview[];
};

export type MonthlyInvestmentFlow = {
    month: number;
    versato: number;
    rientrato: number;
};

export type YearlyInvestmentFlow = {
    year: number;
    months: MonthlyInvestmentFlow[];
    totals: {
        versato: number;
        rientrato: number;
    };
};

export type OpenInvestmentPosition = {
    isin: string;
    name: string;
    quantity: number;
    invested: number;
    average_price: number;
    opened_at: string;
};

export type ClosedInvestmentPosition = {
    isin: string;
    name: string;
    invested: number;
    received: number;
    realized_gain: number;
    opened_at: string;
    closed_at: string;
};

export type InvestmentPositions = {
    open: OpenInvestmentPosition[];
    closed: ClosedInvestmentPosition[];
};

export type InvestmentTab = {
    id: string;
    name: string;
    cashFlow: YearlyInvestmentFlow[];
    positions: InvestmentPositions;
};

export type CategoryBudgetRow = {
    category_id: number;
    name: string;
    color: string | null;
    monthly_budget: number | null;
    card_id: number | null;
};
