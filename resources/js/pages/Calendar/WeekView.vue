<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import {
    dateRange,
    formatTime,
    itemColor,
    minutesSinceMidnight,
    pad,
    toDateOnly,
} from '@/lib/calendar';
import type { CalendarItem } from '@/types';

const props = defineProps<{
    rangeStart: string;
    rangeEnd: string;
    today: string;
    items: CalendarItem[];
}>();

const emit = defineEmits<{
    'slot-click': [startAt: string];
    'item-click': [item: CalendarItem];
    reschedule: [item: CalendarItem, day: string, time: string];
    resize: [item: CalendarItem, endAt: string];
}>();

const HOURS = Array.from({ length: 24 }, (_, hour) => hour);
const HOUR_HEIGHT_PX = 48;
const MINUTES_PER_DAY = 1440;
const SNAP_MINUTES = 15;
// A plain click is a mousedown+mouseup with essentially no movement between
// them - below this many pixels it's treated as a click, not a drag, so
// opening the edit dialog and actually dragging never fight over the same
// gesture.
const DRAG_THRESHOLD_PX = 4;

const days = computed(() => dateRange(props.rangeStart, props.rangeEnd));
const gridColumns = computed(() => `3.5rem repeat(${days.value.length}, 1fr)`);

function isSameItem(a: CalendarItem, b: CalendarItem) {
    return a.source === b.source && a.id === b.id;
}

function timeString(minutes: number): string {
    return `${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`;
}

function snap(minutes: number, min = 0, max = MINUTES_PER_DAY): number {
    return Math.min(
        max,
        Math.max(min, Math.round(minutes / SNAP_MINUTES) * SNAP_MINUTES),
    );
}

function weekdayLabel(day: string) {
    return new Intl.DateTimeFormat('it-IT', {
        weekday: 'short',
        day: 'numeric',
    }).format(new Date(`${day}T00:00:00`));
}

// --- Custom pointer-based drag (move + resize) --------------------------
//
// Native HTML5 drag-and-drop was tried first but has two problems here:
// dropping only computes the final position once, with no live snapped
// preview while dragging (which is what made it feel imprecise), and a
// resize handle nested inside a draggable="true" block still lets the
// browser's native drag win the gesture even when the handle itself is
// draggable="false" (per spec, the nearest ANCESTOR with draggable=true is
// what gets dragged - the handle can't opt itself out). Tracking the
// gesture by hand with mousemove/mouseup sidesteps both.

type DragMode = 'move' | 'resize';

type ActiveDrag = {
    item: CalendarItem;
    mode: DragMode;
    startClientX: number;
    startClientY: number;
    grabOffsetPx: number; // move only: cursor's offset from the block's top when grabbed
    durationMinutes: number; // move only: preserved while dragging
    startEndMinutes: number; // resize only: the end this gesture started from
};

const activeDrag = ref<ActiveDrag | null>(null);
const dragMoved = ref(false);
const previewDay = ref<string | null>(null);
const previewStartMinutes = ref<number | null>(null);
const previewEndMinutes = ref<number | null>(null);
const columnRefs = ref<Record<string, HTMLElement | null>>({});

function setColumnRef(day: string, el: Element | null) {
    columnRefs.value[day] = el as HTMLElement | null;
}

// A task can't be dragged onto (or off of) a day already in the past - the
// same rule TaskController::schedule() enforces server-side; an evento is
// only draggable when it has its own time (not all_day); an allenamento has
// no such restriction, matching WorkoutController.
function isDraggable(item: CalendarItem): boolean {
    if (item.source === 'evento') {
        return !item.all_day;
    }

    if (item.source === 'task') {
        return toDateOnly(item.start_at) >= props.today;
    }

    return true;
}

// Only a real, timed "evento" has a duration worth resizing - a promemoria
// has no end_at by definition, and an all-day item has no hourly block to
// drag the edge of (matches EventController::resize()'s own guard).
function isResizable(item: CalendarItem): boolean {
    return item.source === 'evento' && item.type === 'evento' && !item.all_day;
}

function dayColumnAt(clientX: number, clientY: number): string | null {
    for (const day of days.value) {
        const el = columnRefs.value[day];

        if (!el) {
            continue;
        }

        const rect = el.getBoundingClientRect();

        if (
            clientX >= rect.left &&
            clientX <= rect.right &&
            clientY >= rect.top &&
            clientY <= rect.bottom
        ) {
            return day;
        }
    }

    return null;
}

function onMoveStart(item: CalendarItem, event: MouseEvent) {
    if (!isDraggable(item)) {
        return;
    }

    event.preventDefault();

    const startMinutes = minutesSinceMidnight(item.start_at);
    const endMinutes = item.end_at
        ? Math.max(
              minutesSinceMidnight(item.end_at),
              startMinutes + SNAP_MINUTES,
          )
        : startMinutes + 30;
    const target = event.currentTarget as HTMLElement | null;

    activeDrag.value = {
        item,
        mode: 'move',
        startClientX: event.clientX,
        startClientY: event.clientY,
        grabOffsetPx: target
            ? event.clientY - target.getBoundingClientRect().top
            : 0,
        durationMinutes: endMinutes - startMinutes,
        startEndMinutes: 0,
    };
    dragMoved.value = false;
    previewDay.value = toDateOnly(item.start_at);
    previewStartMinutes.value = startMinutes;

    window.addEventListener('mousemove', onPointerMove);
    window.addEventListener('mouseup', onPointerUp);
}

function onResizeStart(item: CalendarItem, event: MouseEvent) {
    if (!isResizable(item)) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const startMinutes = minutesSinceMidnight(item.start_at);
    const startEndMinutes = item.end_at
        ? minutesSinceMidnight(item.end_at)
        : startMinutes + 30;

    activeDrag.value = {
        item,
        mode: 'resize',
        startClientX: event.clientX,
        startClientY: event.clientY,
        grabOffsetPx: 0,
        durationMinutes: 0,
        startEndMinutes,
    };
    dragMoved.value = false;
    previewEndMinutes.value = startEndMinutes;

    window.addEventListener('mousemove', onPointerMove);
    window.addEventListener('mouseup', onPointerUp);
}

function onPointerMove(event: MouseEvent) {
    const drag = activeDrag.value;

    if (!drag) {
        return;
    }

    if (!dragMoved.value) {
        const dx = event.clientX - drag.startClientX;
        const dy = event.clientY - drag.startClientY;

        if (Math.hypot(dx, dy) < DRAG_THRESHOLD_PX) {
            return;
        }

        dragMoved.value = true;
    }

    if (drag.mode === 'move') {
        const day =
            dayColumnAt(event.clientX, event.clientY) ?? previewDay.value;
        const column = day ? columnRefs.value[day] : null;

        if (!day || !column) {
            return;
        }

        const rect = column.getBoundingClientRect();
        const topPx = event.clientY - rect.top - drag.grabOffsetPx;
        const rawMinutes = Math.round((topPx / rect.height) * MINUTES_PER_DAY);

        previewDay.value = day;
        previewStartMinutes.value = snap(
            rawMinutes,
            0,
            MINUTES_PER_DAY - SNAP_MINUTES,
        );
    } else {
        const deltaPx = event.clientY - drag.startClientY;
        const deltaMinutes = Math.round((deltaPx / HOUR_HEIGHT_PX) * 60);
        const startMinutes = minutesSinceMidnight(drag.item.start_at);

        previewEndMinutes.value = snap(
            drag.startEndMinutes + deltaMinutes,
            startMinutes + SNAP_MINUTES,
            MINUTES_PER_DAY,
        );
    }
}

function onPointerUp() {
    const drag = activeDrag.value;

    window.removeEventListener('mousemove', onPointerMove);
    window.removeEventListener('mouseup', onPointerUp);

    if (!drag) {
        return;
    }

    if (!dragMoved.value) {
        // No real drag happened - a plain click, only meaningful for move
        // (the resize handle has nothing to click through to).
        if (drag.mode === 'move') {
            emit('item-click', drag.item);
        }
    } else if (
        drag.mode === 'move' &&
        previewDay.value !== null &&
        previewStartMinutes.value !== null
    ) {
        emit(
            'reschedule',
            drag.item,
            previewDay.value,
            timeString(previewStartMinutes.value),
        );
    } else if (drag.mode === 'resize' && previewEndMinutes.value !== null) {
        const day = toDateOnly(drag.item.start_at);
        emit(
            'resize',
            drag.item,
            `${day} ${timeString(previewEndMinutes.value)}:00`,
        );
    }

    activeDrag.value = null;
    dragMoved.value = false;
    previewDay.value = null;
    previewStartMinutes.value = null;
    previewEndMinutes.value = null;
}

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', onPointerMove);
    window.removeEventListener('mouseup', onPointerUp);
});

// --- Rendering: item lists per day + position, drag-preview aware -------

function isBeingMoved(item: CalendarItem): boolean {
    return (
        activeDrag.value !== null &&
        activeDrag.value.mode === 'move' &&
        dragMoved.value &&
        isSameItem(activeDrag.value.item, item)
    );
}

function allDayOrUnscheduled(day: string) {
    return props.items.filter(
        (item) =>
            item.all_day &&
            toDateOnly(item.start_at) === day &&
            !isBeingMoved(item),
    );
}

function timedItems(day: string) {
    return props.items.filter((item) => {
        if (isBeingMoved(item)) {
            return previewDay.value === day;
        }

        if (item.all_day) {
            return false;
        }

        return toDateOnly(item.start_at) === day;
    });
}

function itemStyle(item: CalendarItem) {
    let startMinutes = minutesSinceMidnight(item.start_at);
    let endMinutes = item.end_at
        ? Math.max(
              minutesSinceMidnight(item.end_at),
              startMinutes + SNAP_MINUTES,
          )
        : startMinutes + 30;

    if (isBeingMoved(item) && previewStartMinutes.value !== null) {
        const duration = endMinutes - startMinutes;
        startMinutes = previewStartMinutes.value;
        endMinutes = startMinutes + duration;
    } else if (
        activeDrag.value?.mode === 'resize' &&
        dragMoved.value &&
        isSameItem(activeDrag.value.item, item) &&
        previewEndMinutes.value !== null
    ) {
        endMinutes = previewEndMinutes.value;
    }

    return {
        top: `${(startMinutes / MINUTES_PER_DAY) * 100}%`,
        height: `${((endMinutes - startMinutes) / MINUTES_PER_DAY) * 100}%`,
    };
}
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border"
        :class="activeDrag && dragMoved ? 'select-none' : ''"
    >
        <div class="grid" :style="{ gridTemplateColumns: gridColumns }">
            <div class="border-r border-b" />
            <div
                v-for="day in days"
                :key="day"
                class="border-r border-b p-2 text-center text-xs font-medium capitalize last:border-r-0"
                :class="day === today ? 'bg-primary/10 text-primary' : ''"
            >
                {{ weekdayLabel(day) }}
            </div>
        </div>

        <div
            v-if="days.some((day) => allDayOrUnscheduled(day).length > 0)"
            class="grid border-b"
            :style="{ gridTemplateColumns: gridColumns }"
        >
            <div class="border-r p-1 text-[11px] text-muted-foreground">
                Giorno
            </div>
            <div
                v-for="day in days"
                :key="`allday-${day}`"
                class="space-y-1 border-r p-1 last:border-r-0"
            >
                <button
                    v-for="item in allDayOrUnscheduled(day)"
                    :key="`${item.source}-${item.id}`"
                    type="button"
                    class="block w-full truncate rounded px-1.5 py-0.5 text-left text-xs text-white"
                    :class="
                        isDraggable(item)
                            ? 'cursor-grab active:cursor-grabbing'
                            : ''
                    "
                    :style="{ backgroundColor: itemColor(item) }"
                    :title="
                        item.source !== 'evento'
                            ? 'Trascina su un orario per pianificarlo'
                            : undefined
                    "
                    @mousedown="onMoveStart(item, $event)"
                    @click.stop
                >
                    {{ item.title }}
                </button>
            </div>
        </div>

        <div
            class="grid max-h-[42rem] overflow-y-auto"
            :style="{ gridTemplateColumns: gridColumns }"
        >
            <div>
                <div
                    v-for="hour in HOURS"
                    :key="hour"
                    class="border-r border-b pr-1 text-right text-[11px] text-muted-foreground"
                    :style="{ height: `${HOUR_HEIGHT_PX}px` }"
                >
                    {{ String(hour).padStart(2, '0') }}:00
                </div>
            </div>
            <div
                v-for="day in days"
                :key="day"
                :ref="(el) => setColumnRef(day, el as Element | null)"
                class="relative cursor-pointer border-r last:border-r-0"
                :class="
                    activeDrag && dragMoved && previewDay === day
                        ? 'bg-primary/5'
                        : ''
                "
                @click="emit('slot-click', `${day}T09:00`)"
            >
                <div
                    v-for="hour in HOURS"
                    :key="hour"
                    class="border-b"
                    :style="{ height: `${HOUR_HEIGHT_PX}px` }"
                ></div>
                <button
                    v-for="item in timedItems(day)"
                    :key="`${item.source}-${item.id}`"
                    type="button"
                    class="absolute inset-x-0.5 overflow-hidden rounded px-1.5 py-0.5 text-left text-[11px] text-white shadow-sm"
                    :class="
                        isDraggable(item)
                            ? 'cursor-grab active:cursor-grabbing'
                            : ''
                    "
                    :style="{
                        ...itemStyle(item),
                        backgroundColor: itemColor(item),
                    }"
                    @mousedown="onMoveStart(item, $event)"
                    @click.stop
                >
                    <span class="block truncate font-medium">{{
                        item.title
                    }}</span>
                    <span class="block truncate opacity-90">{{
                        formatTime(item.start_at)
                    }}</span>
                    <div
                        v-if="isResizable(item)"
                        class="absolute inset-x-0 bottom-0 h-2 cursor-ns-resize"
                        title="Trascina per cambiare la durata"
                        @mousedown="onResizeStart(item, $event)"
                        @click.stop
                    />
                </button>
            </div>
        </div>
    </div>
</template>
