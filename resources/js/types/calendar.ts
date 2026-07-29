export type CalendarView = 'mese' | 'settimana' | 'giorno';

export type EventType = 'evento' | 'promemoria';

export type CalendarItemSource = 'evento' | 'task' | 'allenamento';

/**
 * One entry in the calendar's merged item list - an Event row as-is, or a
 * read-and-reposition view onto a Task/Workout (see CalendarController).
 * `type` is only meaningful for source === 'evento'; `href` is only set for
 * 'task'/'allenamento' (where clicking navigates away instead of opening
 * the event dialog).
 */
export type CalendarItem = {
    id: number;
    source: CalendarItemSource;
    title: string;
    description: string | null;
    location: string | null;
    type: EventType | null;
    start_at: string;
    end_at: string | null;
    all_day: boolean;
    scheduled: boolean;
    color: string | null;
    href: string | null;
};
