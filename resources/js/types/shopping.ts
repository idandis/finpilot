export type ShoppingListItem = {
    id: number;
    shopping_list_id: number;
    name: string;
    category: string;
    purchased: boolean;
    position: number;
};

export type ShoppingList = {
    id: number;
    user_id: number;
    name: string;
    items: ShoppingListItem[];
};

/** Category key -> Italian label, in canonical display order. */
export type GroceryCategories = Record<string, string>;
