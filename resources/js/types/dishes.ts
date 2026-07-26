export type Dish = {
    id: number;
    name: string;
    description: string | null;
    category: string;
};

/** Category key -> Italian label, in canonical display order. */
export type DishCategories = Record<string, string>;
