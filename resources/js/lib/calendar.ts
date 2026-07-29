import type { CalendarItem } from '@/types';

export function pad(n: number): string {
    return String(n).padStart(2, '0');
}

const SOURCE_COLORS = {
    evento: '#3987e5',
    promemoria: '#c026d3',
    task: '#c98500',
    allenamento: '#199e70',
} as const;

/** An item's own color, or a fixed default keyed by source (promemoria gets its own shade of evento). */
export function itemColor(
    item: Pick<CalendarItem, 'source' | 'type' | 'color'>,
): string {
    if (item.color) {
        return item.color;
    }

    if (item.source === 'evento' && item.type === 'promemoria') {
        return SOURCE_COLORS.promemoria;
    }

    return SOURCE_COLORS[item.source];
}

/** Local (not UTC) YYYY-MM-DD for an ISO datetime string. */
export function toDateOnly(iso: string): string {
    const d = new Date(iso);

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Local YYYY-MM-DDTHH:mm, the format <input type="datetime-local"> expects/produces. */
export function toDatetimeLocal(iso: string): string {
    const d = new Date(iso);

    return `${toDateOnly(iso)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function formatTime(iso: string): string {
    const d = new Date(iso);

    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function minutesSinceMidnight(iso: string): number {
    const d = new Date(iso);

    return d.getHours() * 60 + d.getMinutes();
}

// Arithmetic done at UTC midnight on a date-only string so it never drifts a
// day off because of the browser's local timezone/DST - same technique
// Tasks/Index.vue's shiftDate() uses.
export function addDaysUtc(date: string, amount: number): string {
    const shifted = new Date(`${date}T00:00:00Z`);
    shifted.setUTCDate(shifted.getUTCDate() + amount);

    return shifted.toISOString().slice(0, 10);
}

export function addMonthsUtc(date: string, amount: number): string {
    const shifted = new Date(`${date}T00:00:00Z`);
    shifted.setUTCMonth(shifted.getUTCMonth() + amount);

    return shifted.toISOString().slice(0, 10);
}

/** Every date-only string from `start` to `end`, inclusive. */
export function dateRange(start: string, end: string): string[] {
    const days: string[] = [];
    let cursor = start;

    while (cursor <= end) {
        days.push(cursor);
        cursor = addDaysUtc(cursor, 1);
    }

    return days;
}
