import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { AccentColor, Appearance, FontChoice, ResolvedAppearance } from '@/types';

export type { AccentColor, Appearance, FontChoice, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
    accentColor: Ref<AccentColor>;
    updateAccentColor: (value: AccentColor) => void;
    fontFamily: Ref<FontChoice>;
    updateFontFamily: (value: FontChoice) => void;
};

// Kept deliberately dark enough that white text (the foreground used for all
// of them) stays readable in both light and dark mode, so no separate
// per-theme variant is needed - this is a brand accent, not a full palette.
export const ACCENT_COLORS: Record<Exclude<AccentColor, 'default'>, { primary: string; primaryForeground: string; swatch: string }> = {
    blue: { primary: 'hsl(221 83% 53%)', primaryForeground: 'hsl(0 0% 100%)', swatch: 'hsl(221 83% 53%)' },
    green: { primary: 'hsl(142 71% 35%)', primaryForeground: 'hsl(0 0% 100%)', swatch: 'hsl(142 71% 35%)' },
    purple: { primary: 'hsl(262 83% 58%)', primaryForeground: 'hsl(0 0% 100%)', swatch: 'hsl(262 83% 58%)' },
    rose: { primary: 'hsl(346 77% 50%)', primaryForeground: 'hsl(0 0% 100%)', swatch: 'hsl(346 77% 50%)' },
    orange: { primary: 'hsl(24 95% 44%)', primaryForeground: 'hsl(0 0% 100%)', swatch: 'hsl(24 95% 44%)' },
    teal: { primary: 'hsl(173 80% 32%)', primaryForeground: 'hsl(0 0% 100%)', swatch: 'hsl(173 80% 32%)' },
};

export const FONT_STACKS: Record<FontChoice, string> = {
    sans: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'",
    serif: "ui-serif, Georgia, Cambria, 'Times New Roman', Times, serif",
    mono: "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace",
    rounded: "'Segoe UI Rounded', 'SF Pro Rounded', ui-rounded, 'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
};

export function updateAccentColorDom(value: AccentColor): void {
    if (typeof document === 'undefined') {
        return;
    }

    if (value === 'default') {
        document.documentElement.style.removeProperty('--primary');
        document.documentElement.style.removeProperty('--primary-foreground');

        return;
    }

    const { primary, primaryForeground } = ACCENT_COLORS[value];

    document.documentElement.style.setProperty('--primary', primary);
    document.documentElement.style.setProperty('--primary-foreground', primaryForeground);
}

export function updateFontDom(value: FontChoice): void {
    if (typeof document === 'undefined') {
        return;
    }

    // Set on both html and body: app.css redeclares --font-sans directly on
    // body (not just inherited from html), so an override needs to reach
    // that element too, not just its ancestor.
    document.documentElement.style.setProperty('--font-sans', FONT_STACKS[value]);
    document.body?.style.setProperty('--font-sans', FONT_STACKS[value]);
}

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function initializeAccentColor(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const saved = localStorage.getItem('accent-color') as AccentColor | null;
    updateAccentColorDom(saved || 'default');
}

export function initializeFont(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const saved = localStorage.getItem('font-family') as FontChoice | null;
    updateFontDom(saved || 'sans');
}

const appearance = ref<Appearance>('system');
const accentColor = ref<AccentColor>('default');
const fontFamily = ref<FontChoice>('sans');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = localStorage.getItem(
            'appearance',
        ) as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }

        const savedAccentColor = localStorage.getItem('accent-color') as AccentColor | null;

        if (savedAccentColor) {
            accentColor.value = savedAccentColor;
        }

        const savedFontFamily = localStorage.getItem('font-family') as FontChoice | null;

        if (savedFontFamily) {
            fontFamily.value = savedFontFamily;
        }
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    function updateAccentColor(value: AccentColor) {
        accentColor.value = value;
        localStorage.setItem('accent-color', value);
        updateAccentColorDom(value);
    }

    function updateFontFamily(value: FontChoice) {
        fontFamily.value = value;
        localStorage.setItem('font-family', value);
        updateFontDom(value);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
        accentColor,
        updateAccentColor,
        fontFamily,
        updateFontFamily,
    };
}
