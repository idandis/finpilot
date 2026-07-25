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
    is_investment_card: boolean;
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
    is_crypto: boolean;
    quantity: number;
    invested: number;
    average_price: number;
    opened_at: string;
    current_price: number | null;
    market_value: number | null;
    unrealized_gain: number | null;
    unrealized_gain_percent: number | null;
    price_date: string | null;
    price_currency: string | null;
    current_price_original: number | null;
    market_value_original: number | null;
    realized_gain: number;
};

export type ClosedInvestmentPosition = {
    isin: string;
    name: string;
    is_crypto: boolean;
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

export type PortfolioHistoryPoint = {
    date: string;
    invested: number;
    market_value: number | null;
};

export type UnpricedPosition = {
    isin: string;
    name: string;
    quantity: number;
    invested: number;
};

export type PortfolioHistory = {
    points: PortfolioHistoryPoint[];
    market_data_since: string | null;
    unpriced_positions: UnpricedPosition[];
};

export type PositionTransaction = {
    id: number;
    transaction_date: string;
    description: string;
    amount: string;
    direction: TransactionDirection;
    isin: string | null;
    quantity: string | null;
};

export type InvestmentNote = {
    id: number;
    body: string;
    created_at: string;
};

export type InvestmentNewsArticle = {
    id: number;
    title: string;
    content: string | null;
    url: string | null;
    published_at: string;
    sentiment_polarity: number | null;
    tags: string[] | null;
};

export type InvestmentNewsHighlight = {
    id: number;
    title: string;
    url: string | null;
    published_at: string;
    sentiment_polarity: number | null;
    figures: string[];
};

export type InvestmentNews = {
    articles: InvestmentNewsArticle[];
    fetchedAt: string | null;
    resolvable: boolean;
    highlights: InvestmentNewsHighlight[];
};

export type MarketCandle = {
    time: string;
    open: number;
    high: number;
    low: number;
    close: number;
};

export type MarketAnalysis = {
    sma20: number | null;
    sma20_signal: 'above' | 'below' | null;
    rsi14: number | null;
    rsi14_signal: 'overbought' | 'oversold' | 'neutral' | null;
    support: number | null;
    resistance: number | null;
};

export type BuffettAnswer = {
    key: string;
    label: string;
    answer: boolean | null;
    notes: string | null;
};

export type CompanyAnalysisScores = {
    revenue_growth: number | null;
    eps_growth: number | null;
    revenue_cagr_5y: number | null;
    eps_cagr_5y: number | null;
    operating_margin: number | null;
    net_margin: number | null;
    gross_margin: number | null;
    roe: number | null;
    roic: number | null;
    debt_to_ebitda: number | null;
    interest_coverage: number | null;
    current_ratio: number | null;
    pe_ratio: number | null;
    ev_to_ebitda: number | null;
    ev_to_fcf: number | null;
    price_to_sales: number | null;
    peg_ratio: number | null;
    fcf_yield: number | null;
    fcf_margin: number | null;
};

export type ValuationVerdict = 'undervalued' | 'fair' | 'expensive' | 'very_expensive';

export type CompanyValuation = {
    deviation_percent: number | null;
    verdict: ValuationVerdict | null;
    recommended_action: string | null;
    entry_price: number | null;
    accumulate_price: number | null;
};

export type CompanyAnalysis = {
    id: number;
    name: string;
    symbol: string;
    current_price: number | null;
    market_cap: number | null;
    revenue_growth: number | null;
    eps_growth: number | null;
    revenue_cagr_5y: number | null;
    eps_cagr_5y: number | null;
    free_cash_flow: number | null;
    fcf_margin: number | null;
    operating_margin: number | null;
    net_margin: number | null;
    gross_margin: number | null;
    roe: number | null;
    roic: number | null;
    debt_to_ebitda: number | null;
    interest_coverage: number | null;
    current_ratio: number | null;
    pe_ratio: number | null;
    ev_to_ebitda: number | null;
    ev_to_fcf: number | null;
    price_to_sales: number | null;
    peg_ratio: number | null;
    fcf_yield: number | null;
    fair_value: number | null;
    historical_comparison: string | null;
    competitor_comparison: string | null;
    indicators_currency: string | null;
    indicators_fetched_at: string | null;
    price_history_fetched_at: string | null;
    created_at: string;
    updated_at: string;
    scores: CompanyAnalysisScores;
    valuation: CompanyValuation;
};

export type MarketInstrument = {
    isin: string;
    name: string;
    is_crypto: boolean;
    current_price: number | null;
    current_price_original: number | null;
    price_currency: string | null;
    day_change: number | null;
    day_change_percent: number | null;
    candles: MarketCandle[];
    analysis: MarketAnalysis;
};

export type InvestmentTab = {
    id: string;
    name: string;
    cashFlow: YearlyInvestmentFlow[];
    positions: InvestmentPositions;
    portfolioHistory: PortfolioHistory;
    accountBalance: number | null;
    wealthHistory: PortfolioHistory | null;
};

export type CategoryBudgetRow = {
    category_id: number;
    name: string;
    color: string | null;
    monthly_budget: number | null;
    card_id: number | null;
};
