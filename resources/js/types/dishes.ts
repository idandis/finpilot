export type DishIngredient = {
    id: number;
    name: string;
    category: string;
};

export type Dish = {
    id: number;
    name: string;
    description: string | null;
    category: string;
    ingredients: DishIngredient[];
};

/** Category key -> Italian label, in canonical display order. */
export type DishCategories = Record<string, string>;
