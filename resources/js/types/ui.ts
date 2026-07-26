export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AccentColor = 'default' | 'blue' | 'green' | 'purple' | 'rose' | 'orange' | 'teal';
export type FontChoice = 'sans' | 'serif' | 'mono' | 'rounded';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};
