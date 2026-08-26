export type BalanceSheetEntryType =
    'income' | 'expense' | 'asset' | 'liability' | 'time' | 'goal';

export type BalanceSheetGenericEntryType =
    'income' | 'expense' | 'liability' | 'time' | 'goal';

export type BalanceSheetTimeKind = 'consumes' | 'frees';

export type BalanceSheetFrequency = 'monthly' | 'one_time';

export type BalanceSheetEntry = {
    id: number;
    user_id: number;
    type: BalanceSheetEntryType;
    name: string;
    category: string | null;
    amount: string | null;
    cash_used: string | null;
    hours_per_month: string | null;
    time_kind: BalanceSheetTimeKind | null;
    frequency: BalanceSheetFrequency | null;
    progress_percent: number | null;
    active: boolean;
    linked_asset_id: number | null;
    linked_liability_id: number | null;
    linked_income?: BalanceSheetEntry | null;
    linked_liability?: BalanceSheetEntry | null;
    linked_expense?: BalanceSheetEntry | null;
    linked_time?: BalanceSheetEntry | null;
};

export type BalanceSheetProfile = {
    id: number;
    user_id: number;
    cash_balance: string;
    closed_months: number;
    base_monthly_hours: string;
};

export type BalanceSheetMonthClosure = {
    id: number;
    user_id: number;
    income_total: string;
    expense_total: string;
    cash_flow: string;
    cash_balance_after: string;
    created_at: string;
};

export type BalanceSheetOverview = {
    cash_balance: number;
    monthly_income: number;
    monthly_expense: number;
    cash_flow: number;
    total_assets: number;
    total_liabilities: number;
    net_worth: number;
    hours_consumed: number;
    hours_freed: number;
    free_time: number;
    hourly_value: number | null;
};

export type BalanceSheetLiabilityOption = {
    id: number;
    name: string;
};
