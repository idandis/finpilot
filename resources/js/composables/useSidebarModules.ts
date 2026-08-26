import { ref } from 'vue';

const STORAGE_KEY = 'sidebar-hidden-modules';

function readStoredHiddenModules(): string[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        return raw ? (JSON.parse(raw) as string[]) : [];
    } catch {
        return [];
    }
}

// Module-scoped (not per-component) so a toggle flipped on the settings
// page is reflected in the sidebar immediately, without a page reload -
// same pattern as accentColor/fontFamily in useAppearance.ts. Read
// synchronously at import time (not onMounted) so the sidebar's first
// render already reflects saved preferences instead of flashing hidden
// items before hiding them.
const hiddenModules = ref<string[]>(readStoredHiddenModules());

export function useSidebarModules() {
    function isModuleHidden(key?: string): boolean {
        return !!key && hiddenModules.value.includes(key);
    }

    function setModuleHidden(key: string, hidden: boolean) {
        const next = hidden
            ? Array.from(new Set([...hiddenModules.value, key]))
            : hiddenModules.value.filter((existing) => existing !== key);

        hiddenModules.value = next;
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    }

    return { hiddenModules, isModuleHidden, setModuleHidden };
}
