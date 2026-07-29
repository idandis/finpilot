import {
    Briefcase,
    KeyRound,
    Landmark,
    LayoutGrid,
    Mail,
    ShoppingCart,
    StickyNote,
    Users,
    Wifi,
} from '@lucide/vue';

export const PASSWORD_GROUP_ICONS = {
    apps: LayoutGrid,
    banking: Landmark,
    email: Mail,
    notes: StickyNote,
    social: Users,
    shopping: ShoppingCart,
    work: Briefcase,
    wifi: Wifi,
    other: KeyRound,
} as const;

export type PasswordGroupIconName = keyof typeof PASSWORD_GROUP_ICONS;

export const PASSWORD_GROUP_ICON_NAMES = Object.keys(
    PASSWORD_GROUP_ICONS,
) as PasswordGroupIconName[];

export const PASSWORD_GROUP_ICON_LABELS: Record<PasswordGroupIconName, string> =
    {
        apps: 'App',
        banking: 'Banca',
        email: 'Email',
        notes: 'Note',
        social: 'Social',
        shopping: 'Shopping',
        work: 'Lavoro',
        wifi: 'Wi-Fi',
        other: 'Altro',
    };
