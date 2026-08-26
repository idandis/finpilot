import type { BalanceSheetEntry } from '@/types';

export function groupBalanceSheetEntriesByCategory(
    entries: BalanceSheetEntry[],
) {
    const groups = new Map<string, BalanceSheetEntry[]>();

    for (const entry of entries) {
        const key = entry.category ?? 'Senza categoria';
        groups.set(key, [...(groups.get(key) ?? []), entry]);
    }

    return [...groups.entries()].map(([category, items]) => ({
        category,
        items,
    }));
}
