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

export type BudgetComparisonRow = {
    category_id: number;
    name: string;
    spent: number;
    budget: number | null;
    remaining: number | null;
    percent_used: number | null;
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
    price_is_realtime: boolean;
    realtime_price_at: string | null;
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
    unit_price: string | null;
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

export type CompanyAnalysisOption = {
    id: number;
    name: string;
    symbol: string;
};

export type InvestmentMotivationOption = {
    key: string;
    label: string;
};

export type InvestmentTimeHorizon = 'short' | 'medium' | 'long';

export type InvestmentReviewDecision = 'hold' | 'increase' | 'reduce' | 'sell' | 'watch';

export type InvestmentReview = {
    id: number;
    investment_event_id: number | null;
    investment_event_title: string | null;
    review_date: string;
    decision: InvestmentReviewDecision;
    thesis_still_valid: boolean | null;
    score_before: number | null;
    score_after: number | null;
    note: string | null;
};

export type InvestmentEventType =
    | 'earnings_quarterly'
    | 'earnings_annual'
    | 'guidance'
    | 'investor_day'
    | 'acquisition'
    | 'management_change'
    | 'regulatory'
    | 'other';

export type InvestmentEventMetric = {
    label: string;
    value: string;
};

export type InvestmentEvent = {
    id: number;
    event_type: InvestmentEventType;
    title: string;
    event_date: string;
    metrics: InvestmentEventMetric[];
    summary: string | null;
};

export type InvestmentDecision = {
    id: number;
    company_analysis_id: number | null;
    motivation_reasons: string[];
    motivation_note: string | null;
    thesis: string | null;
    sell_conditions: string | null;
    time_horizon: InvestmentTimeHorizon | null;
    initial_confidence: number | null;
    current_confidence: number | null;
    next_review_date: string | null;
    next_review_note: string | null;
    created_at: string;
    updated_at: string;
    reviews: InvestmentReview[];
    events: InvestmentEvent[];
};

export type JournalEventType = 'buy' | 'increase' | 'reduce' | 'sell' | 'dividend' | 'note' | 'review';

export type JournalEvent = {
    type: JournalEventType;
    date: string;
    description: string | null;
    amount: number | null;
    quantity: number | null;
    transaction_id: number | null;
    journal_entry_id?: number;
    review_id?: number;
    decision?: InvestmentReviewDecision;
    score_before?: number | null;
    score_after?: number | null;
    notes: string[];
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

export type MacroIndicatorRegion = 'usa' | 'eurozone';

export type MacroIndicatorSource = 'fred' | 'ecb';

export type MacroIndicatorPoint = {
    date: string;
    value: number;
};

export type MacroIndicatorValue = {
    value: number;
    date: string;
};

export type MacroIndicatorTrend = 'improving' | 'worsening' | 'neutral' | 'stable';

export type MacroIndicator = {
    key: string;
    label: string;
    region: MacroIndicatorRegion;
    unit: string;
    source: MacroIndicatorSource;
    current: MacroIndicatorValue | null;
    previous: MacroIndicatorValue | null;
    change_absolute: number | null;
    change_percent: number | null;
    history: MacroIndicatorPoint[];
    published_at: string | null;
    narrative: string;
    trend: MacroIndicatorTrend;
};

export type MacroCategoryGroup = {
    key: string;
    label: string;
    indicators: MacroIndicator[];
};

export type MarketOverviewTrend = 'up' | 'down' | 'neutral';

export type MarketOverviewRotation = 'strong_accelerating' | 'strong_slowing' | 'weak_recovering' | 'weak_worsening';

export type MarketOverviewValue = {
    value: number;
    date: string;
};

export type MarketOverviewInstrument = {
    key: string;
    label: string;
    region: string;
    unit: string;
    current: MarketOverviewValue | null;
    day_change_percent: number | null;
    week_change_percent: number | null;
    month_change_percent: number | null;
    quarter_change_percent: number | null;
    ytd_change_percent: number | null;
    distance_from_high_percent: number | null;
    volatility_percent: number | null;
    trend: MarketOverviewTrend;
    rotation: MarketOverviewRotation | null;
    history: MacroIndicatorPoint[];
};

export type MarketOverviewCategoryGroup = {
    key: string;
    label: string;
    instruments: MarketOverviewInstrument[];
};

export type EconomicRegimeSignal = {
    label: string;
    satisfied: boolean;
};

export type EconomicRegime = {
    key: string;
    label: string;
    description: string;
    confidence_percent: number;
    signals: EconomicRegimeSignal[];
};

export type RiskSentiment = {
    condition: string;
    score: number;
    positive_signals: string[];
    caution_signals: string[];
};

export type MarketSentiment = {
    sentiment: string;
    score: number;
    positive_signals: string[];
    caution_signals: string[];
    omitted_signals: string[];
};
