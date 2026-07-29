<script setup lang="ts">
import { computed } from 'vue';
import { dateRange, itemColor, toDateOnly } from '@/lib/calendar';
import type { CalendarItem } from '@/types';

const props = defineProps<{
    date: string;
    rangeStart: string;
    rangeEnd: string;
    today: string;
    items: CalendarItem[];
}>();

const emit = defineEmits<{
    'day-click': [startAt: string];
    'item-click': [item: CalendarItem];
}>();

const WEEKDAY_LABELS = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
const MAX_VISIBLE_PER_DAY = 3;

const currentMonth = computed(() => Number(props.date.slice(5, 7)));

const days = computed(() => {
    const itemsByDay = new Map<string, CalendarItem[]>();

    for (const item of props.items) {
        const startDay = toDateOnly(item.start_at);
        const endDay = item.end_at ? toDateOnly(item.end_at) : startDay;

        for (const day of dateRange(startDay, endDay)) {
            if (day < props.rangeStart || day > props.rangeEnd) {
                continue;
            }

            if (!itemsByDay.has(day)) {
                itemsByDay.set(day, []);
            }

            itemsByDay.get(day)!.push(item);
        }
    }

    return dateRange(props.rangeStart, props.rangeEnd).map((day) => ({
        date: day,
        inCurrentMonth: Number(day.slice(5, 7)) === currentMonth.value,
        isToday: day === props.today,
        items: (itemsByDay.get(day) ?? []).sort((a, b) =>
            a.start_at.localeCompare(b.start_at),
        ),
    }));
});
</script>

<template>
    <div class="overflow-hidden rounded-lg border">
        <div
            class="grid grid-cols-7 border-b bg-muted/30 text-xs font-medium text-muted-foreground"
        >
            <div
                v-for="label in WEEKDAY_LABELS"
                :key="label"
                class="px-2 py-2 text-center"
            >
                {{ label }}
            </div>
        </div>
        <div class="grid grid-cols-7">
            <div
                v-for="day in days"
                :key="day.date"
                class="min-h-[6rem] cursor-pointer border-r border-b p-1.5 last:border-r-0 hover:bg-muted/30"
                :class="
                    !day.inCurrentMonth
                        ? 'bg-muted/10 text-muted-foreground'
                        : ''
                "
                @click="emit('day-click', `${day.date}T09:00`)"
            >
                <span
                    class="inline-flex size-6 items-center justify-center rounded-full text-xs"
                    :class="
                        day.isToday
                            ? 'bg-primary font-semibold text-primary-foreground'
                            : ''
                    "
                >
                    {{ Number(day.date.slice(8, 10)) }}
                </span>
                <div class="mt-1 space-y-1">
                    <button
                        v-for="item in day.items.slice(0, MAX_VISIBLE_PER_DAY)"
                        :key="`${item.source}-${item.id}`"
                        type="button"
                        class="block w-full truncate rounded px-1.5 py-0.5 text-left text-xs text-white"
                        :style="{ backgroundColor: itemColor(item) }"
                        @click.stop="emit('item-click', item)"
                    >
                        {{ item.title }}
                    </button>
                    <p
                        v-if="day.items.length > MAX_VISIBLE_PER_DAY"
                        class="px-1.5 text-[11px] text-muted-foreground"
                    >
                        +{{ day.items.length - MAX_VISIBLE_PER_DAY }} altri
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
