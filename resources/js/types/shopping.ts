import type { SharedPerson } from './sharing';

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
    /** Owned by somebody else and shared with this user. */
    is_shared: boolean;
    items: ShoppingListItem[];
};

/** The list currently open, with everyone shopping from it. */
export type ShoppingListDetail = ShoppingList & {
    is_owner: boolean;
    people: SharedPerson[];
};

/** Category key -> Italian label, in canonical display order. */
export type GroceryCategories = Record<string, string>;
