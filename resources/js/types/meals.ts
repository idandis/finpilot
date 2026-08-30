import type { SharedPerson } from './sharing';

export type MealType = 'lunch' | 'dinner';

export type Meal = {
    id: number;
    title: string;
    description: string | null;
    meal_date: string;
    meal_type: MealType;
    category: string | null;
    position: number;
    /** Who cooks it, if anyone - one of the plan's people. */
    assignee: { id: number; name: string } | null;
};

/**
 * A meal plan is simply a user's meals, so a plan is identified by its
 * owner: `id` is that user's id, and the user's own plan is the one whose
 * id matches theirs.
 */
export type MealPlan = {
    id: number;
    name: string;
    /** Owned by somebody else and shared with this user. */
    is_shared: boolean;
};

/** The plan currently shown, with everyone planning and cooking from it. */
export type MealPlanDetail = {
    id: number;
    name: string;
    is_owner: boolean;
    people: SharedPerson[];
};
