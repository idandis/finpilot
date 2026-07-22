export type AccountType =
    'checking' | 'debit_card' | 'credit_card' | 'prepaid_card' | 'cash';

export type CardType = 'debit' | 'credit' | 'prepaid';

export type Card = {
    id: number;
    financial_account_id: number;
    name: string;
    type: CardType;
    last_four_digits: string | null;
    circuit: string | null;
    is_active: boolean;
    financial_account?: Pick<
        FinancialAccount,
        'id' | 'name' | 'bank_name' | 'color' | 'currency'
    >;
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
    financial_account_id: number;
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
};
