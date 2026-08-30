import {
    Apple,
    Beef,
    Candy,
    Carrot,
    Croissant,
    CupSoda,
    Fish,
    Ham,
    Milk,
    Package,
    ShoppingBasket,
    ShowerHead,
    Snowflake,
    Soup,
    SprayCan,
    Wheat,
} from '@lucide/vue';
import type { Component } from 'vue';

/**
 * One icon per grocery aisle (the keys come from
 * App\Services\Shopping\GroceryCategories). Anything unmapped falls back to
 * the basket, so adding a category server-side never breaks the page.
 */
export const GROCERY_ICONS: Record<string, Component> = {
    frutta: Apple,
    verdura: Carrot,
    carne: Beef,
    pesce: Fish,
    latticini: Milk,
    salumi: Ham,
    panetteria: Croissant,
    pasta_riso_cereali: Wheat,
    conserve: Package,
    surgelati: Snowflake,
    bevande: CupSoda,
    snack_dolci: Candy,
    condimenti: Soup,
    casa_pulizia: SprayCan,
    igiene_persona: ShowerHead,
    altro: ShoppingBasket,
};

export function groceryIcon(category: string): Component {
    return GROCERY_ICONS[category] ?? ShoppingBasket;
}
