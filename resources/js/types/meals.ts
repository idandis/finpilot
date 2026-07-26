export type MealType = 'lunch' | 'dinner';

export type Meal = {
    id: number;
    title: string;
    description: string | null;
    meal_date: string;
    meal_type: MealType;
    category: string | null;
    position: number;
};
