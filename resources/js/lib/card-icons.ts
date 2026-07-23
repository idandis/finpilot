import { Banknote, Coins, CreditCard, Landmark, PiggyBank, Wallet } from '@lucide/vue';

export const CARD_ICONS = {
    'credit-card': CreditCard,
    wallet: Wallet,
    landmark: Landmark,
    'piggy-bank': PiggyBank,
    banknote: Banknote,
    coins: Coins,
} as const;

export type CardIconName = keyof typeof CARD_ICONS;

export const CARD_ICON_NAMES = Object.keys(CARD_ICONS) as CardIconName[];

export const CARD_ICON_LABELS: Record<CardIconName, string> = {
    'credit-card': 'Carta di credito',
    wallet: 'Portafoglio',
    landmark: 'Banca',
    'piggy-bank': 'Salvadanaio',
    banknote: 'Banconota',
    coins: 'Monete',
};
