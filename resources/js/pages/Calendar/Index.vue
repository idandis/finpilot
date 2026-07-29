<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import EventController from '@/actions/App/Http/Controllers/EventController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    addDaysUtc,
    addMonthsUtc,
    toDateOnly,
    toDatetimeLocal,
} from '@/lib/calendar';
import * as calendarRoutes from '@/routes/calendar';
import * as eventRoutes from '@/routes/events';
import * as taskRoutes from '@/routes/tasks';
import * as workoutRoutes from '@/routes/workouts';
import type { CalendarItem, CalendarView } from '@/types';
import CalendarMonthView from './MonthView.vue';
import CalendarWeekView from './WeekView.vue';

const props = defineProps<{
    view: CalendarView;
    date: string;
    today: string;
    rangeStart: string;
    rangeEnd: string;
    items: CalendarItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Calendario', href: calendarRoutes.index() }],
    },
});

const VIEW_OPTIONS: { value: CalendarView; label: string }[] = [
    { value: 'mese', label: 'Mese' },
    { value: 'settimana', label: 'Settimana' },
    { value: 'giorno', label: 'Giorno' },
];

function navigate(view: CalendarView, date: string) {
    router.get(
        calendarRoutes.index.url({ query: { vista: view, data: date } }),
        {},
        { preserveScroll: true },
    );
}

const previousDate = computed(() => {
    if (props.view === 'mese') {
        return addMonthsUtc(props.date, -1);
    }

    if (props.view === 'settimana') {
        return addDaysUtc(props.date, -7);
    }

    return addDaysUtc(props.date, -1);
});

const nextDate = computed(() => {
    if (props.view === 'mese') {
        return addMonthsUtc(props.date, 1);
    }

    if (props.view === 'settimana') {
        return addDaysUtc(props.date, 7);
    }

    return addDaysUtc(props.date, 1);
});

const periodLabel = computed(() => {
    const anchor = new Date(`${props.date}T00:00:00`);

    if (props.view === 'mese') {
        return new Intl.DateTimeFormat('it-IT', {
            month: 'long',
            year: 'numeric',
        }).format(anchor);
    }

    if (props.view === 'giorno') {
        return new Intl.DateTimeFormat('it-IT', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(anchor);
    }

    const fmt = new Intl.DateTimeFormat('it-IT', {
        day: 'numeric',
        month: 'short',
    });
    const start = new Date(`${props.rangeStart}T00:00:00`);
    const end = new Date(`${props.rangeEnd}T00:00:00`);

    return `${fmt.format(start)} - ${fmt.format(end)} ${end.getFullYear()}`;
});

// --- Reposition a task/allenamento/evento dragged onto an hour slot ----

function onReschedule(item: CalendarItem, day: string, time: string) {
    const options = { preserveScroll: true, preserveState: true };

    if (item.source === 'evento') {
        router.patch(
            eventRoutes.reschedule(item.id).url,
            { start_at: `${day} ${time}:00` },
            options,
        );
    } else if (item.source === 'task') {
        router.patch(
            taskRoutes.schedule(item.id).url,
            { task_date: day, scheduled_time: time },
            options,
        );
    } else {
        router.patch(
            workoutRoutes.schedule(item.id).url,
            { workout_date: day, scheduled_time: time },
            options,
        );
    }
}

function onResize(item: CalendarItem, endAt: string) {
    router.patch(
        eventRoutes.resize(item.id).url,
        { end_at: endAt },
        { preserveScroll: true, preserveState: true },
    );
}

// --- Create/edit event dialog -------------------------------------------
// Only ever opened for an "evento" - clicking a task/allenamento chip
// navigates to its own page instead (see openItem()), since those are
// edited from Tasks/Allenamenti, not from the calendar.

const editingEvent = ref<CalendarItem | null>(null);
const createDefaultStart = ref<string | null>(null);
const isDialogOpen = ref(false);

const selectedType = ref<'evento' | 'promemoria'>('evento');
const isAllDay = ref(false);

function openCreateDialog(startAt: string) {
    editingEvent.value = null;
    createDefaultStart.value = startAt;
    isDialogOpen.value = true;
}

function openItem(item: CalendarItem) {
    if (item.source !== 'evento') {
        router.visit(item.href!);

        return;
    }

    editingEvent.value = item;
    createDefaultStart.value = null;
    isDialogOpen.value = true;
}

function closeDialog() {
    isDialogOpen.value = false;
}

watch(isDialogOpen, (open) => {
    if (open) {
        selectedType.value =
            (editingEvent.value?.type as 'evento' | 'promemoria' | null) ??
            'evento';
        isAllDay.value = editingEvent.value?.all_day ?? false;
    } else {
        editingEvent.value = null;
        createDefaultStart.value = null;
    }
});

const startDefaultValue = computed(() => {
    const iso =
        editingEvent.value?.start_at ??
        (createDefaultStart.value ? `${createDefaultStart.value}:00` : null);

    if (!iso) {
        return undefined;
    }

    return isAllDay.value ? toDateOnly(iso) : toDatetimeLocal(iso);
});

const endDefaultValue = computed(() => {
    if (!editingEvent.value?.end_at) {
        return undefined;
    }

    return isAllDay.value
        ? toDateOnly(editingEvent.value.end_at)
        : toDatetimeLocal(editingEvent.value.end_at);
});

function destroyEvent(item: CalendarItem) {
    if (confirm(`Eliminare "${item.title}"?`)) {
        closeDialog();
        router.delete(eventRoutes.destroy(item.id).url, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Calendario" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <Button
                    variant="outline"
                    size="icon-sm"
                    title="Periodo precedente"
                    @click="navigate(view, previousDate)"
                >
                    <ChevronLeft />
                </Button>
                <span class="min-w-[9rem] font-medium capitalize">{{
                    periodLabel
                }}</span>
                <Button
                    variant="outline"
                    size="icon-sm"
                    title="Periodo successivo"
                    @click="navigate(view, nextDate)"
                >
                    <ChevronRight />
                </Button>
                <Button
                    v-if="date !== today"
                    variant="ghost"
                    size="sm"
                    @click="navigate(view, today)"
                    >Oggi</Button
                >
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="flex rounded-md border p-0.5">
                    <Button
                        v-for="option in VIEW_OPTIONS"
                        :key="option.value"
                        type="button"
                        :variant="view === option.value ? 'default' : 'ghost'"
                        size="sm"
                        @click="navigate(option.value, date)"
                    >
                        {{ option.label }}
                    </Button>
                </div>
                <Button @click="openCreateDialog(`${date}T09:00`)">
                    <Plus />
                    Nuovo evento
                </Button>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            Task e allenamenti compaiono qui in sola lettura per posizionarli su
            un orario - per modificarne contenuto o dettagli, apri la rispettiva
            pagina.
        </p>

        <CalendarMonthView
            v-if="view === 'mese'"
            :range-start="rangeStart"
            :range-end="rangeEnd"
            :date="date"
            :today="today"
            :items="items"
            @day-click="openCreateDialog"
            @item-click="openItem"
        />
        <CalendarWeekView
            v-else
            :range-start="rangeStart"
            :range-end="rangeEnd"
            :today="today"
            :items="items"
            @slot-click="openCreateDialog"
            @item-click="openItem"
            @reschedule="onReschedule"
            @resize="onResize"
        />

        <Dialog
            :open="isDialogOpen"
            @update:open="
                (open) => {
                    if (!open) closeDialog();
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        editingEvent ? 'Modifica evento' : 'Nuovo evento'
                    }}</DialogTitle>
                </DialogHeader>
                <Form
                    :key="
                        editingEvent
                            ? `event-${editingEvent.id}`
                            : `new-event-${isAllDay}`
                    "
                    v-bind="
                        editingEvent
                            ? EventController.update.form(editingEvent.id)
                            : EventController.store.form()
                    "
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="closeDialog"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="type">Tipo</Label>
                            <select
                                id="type"
                                v-model="selectedType"
                                name="type"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="evento">Evento</option>
                                <option value="promemoria">Promemoria</option>
                            </select>
                            <InputError :message="errors.type" />
                        </div>
                        <div
                            v-if="selectedType === 'evento'"
                            class="flex items-end pb-2"
                        >
                            <Label
                                class="flex items-center gap-2 text-sm font-normal"
                            >
                                <input type="hidden" name="all_day" value="0" />
                                <Checkbox
                                    v-model="isAllDay"
                                    name="all_day"
                                    value="1"
                                />
                                Tutto il giorno
                            </Label>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="title">Titolo</Label>
                        <Input
                            id="title"
                            name="title"
                            required
                            autofocus
                            :default-value="editingEvent?.title"
                        />
                        <InputError :message="errors.title" />
                    </div>

                    <div
                        class="grid gap-4"
                        :class="
                            selectedType === 'evento'
                                ? 'grid-cols-2'
                                : 'grid-cols-1'
                        "
                    >
                        <div class="grid gap-2">
                            <Label for="start_at">{{
                                selectedType === 'promemoria'
                                    ? 'Quando'
                                    : 'Inizio'
                            }}</Label>
                            <Input
                                id="start_at"
                                name="start_at"
                                :type="isAllDay ? 'date' : 'datetime-local'"
                                required
                                :default-value="startDefaultValue"
                            />
                            <InputError :message="errors.start_at" />
                        </div>
                        <div
                            v-if="selectedType === 'evento'"
                            class="grid gap-2"
                        >
                            <Label for="end_at">Fine</Label>
                            <Input
                                id="end_at"
                                name="end_at"
                                :type="isAllDay ? 'date' : 'datetime-local'"
                                :default-value="endDefaultValue"
                            />
                            <InputError :message="errors.end_at" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Descrizione (opzionale)</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            placeholder="Dettagli aggiuntivi..."
                            class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                            :value="editingEvent?.description ?? ''"
                        ></textarea>
                        <InputError :message="errors.description" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="location">Luogo (opzionale)</Label>
                            <Input
                                id="location"
                                name="location"
                                :default-value="editingEvent?.location ?? ''"
                            />
                            <InputError :message="errors.location" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="color">Colore (opzionale)</Label>
                            <Input
                                id="color"
                                name="color"
                                type="color"
                                :default-value="
                                    editingEvent?.color ?? '#3987e5'
                                "
                                class="h-9 w-full p-1"
                            />
                            <InputError :message="errors.color" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <Button
                            v-if="editingEvent"
                            type="button"
                            variant="ghost"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            @click="destroyEvent(editingEvent)"
                        >
                            Elimina
                        </Button>
                        <span v-else />
                        <Button type="submit" :disabled="processing">{{
                            editingEvent ? 'Salva modifiche' : 'Crea'
                        }}</Button>
                    </div>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
