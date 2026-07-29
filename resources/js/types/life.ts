export type LifeWeekMetrics = {
    productivity: number | null;
    workouts: number | null;
    mood: number | null;
    budget: number | null;
};

export type LifeWeekSummary = {
    week: number;
    start: string;
    end: string;
    isCurrent: boolean;
    metrics: LifeWeekMetrics;
};

export type LifeFinanceSummary = {
    income: number;
    expenses: number;
    savings: number;
    invested: number;
    net_worth: number | null;
};

export type LifeHealthSummary = {
    workouts_total: number;
    workouts_completed: number;
    meals_planned: number;
};

export type LifeOrganizationSummary = {
    tasks_total: number;
    tasks_completed: number;
};

export type LifeAdjacentWeek = {
    year: number;
    week: number;
};

export type Memory = {
    id: number;
    title: string;
    description: string | null;
    location: string | null;
    people: string | null;
    mood: string | null;
    photo_url: string | null;
};

/** Mood key -> Italian label. */
export type Moods = Record<string, string>;

export type LifeDay = {
    date: string;
    memories: Memory[];
    events: string[];
};

export type LifeWeekDetail = {
    year: number;
    week: number;
    start: string;
    end: string;
    weeksInYear: number;
    previous: LifeAdjacentWeek;
    next: LifeAdjacentWeek;
    finance: LifeFinanceSummary;
    health: LifeHealthSummary;
    organization: LifeOrganizationSummary;
    days: LifeDay[];
    moods: Moods;
};
