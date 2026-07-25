<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import MealController from '@/actions/App/Http/Controllers/MealController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as mealRoutes from '@/routes/meals';
import type { Meal, MealType } from '@/types';

const props = defineProps<{
    weekStart: string;
    today: string;
    meals: Meal[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pasti', href: mealRoutes.index() }],
    },
});

// Calendar-day arithmetic done at UTC midnight so it never drifts a day off
// because of the browser's local timezone/DST.
function shiftDate(date: string, days: number): string {
    const shifted = new Date(`${date}T00:00:00Z`);
    shifted.setUTCDate(shifted.getUTCDate() + days);

    return shifted.toISOString().slice(0, 10);
}

function mondayOf(date: string): string {
    const d = new Date(`${date}T00:00:00Z`);
    const isoWeekday = d.getUTCDay() === 0 ? 7 : d.getUTCDay();
    d.setUTCDate(d.getUTCDate() - (isoWeekday - 1));

    return d.toISOString().slice(0, 10);
}

const previousWeekStart = computed(() => shiftDate(props.weekStart, -7));
const nextWeekStart = computed(() => shiftDate(props.weekStart, 7));
const isCurrentWeek = computed(() => props.weekStart === mondayOf(props.today));

function goToWeek(date: string) {
    router.get(mealRoutes.index.url({ query: { date } }), {}, { preserveScroll: true });
}

const MEAL_TYPES: { type: MealType; label: string }[] = [
    { type: 'lunch', label: 'Pranzo' },
    { type: 'dinner', label: 'Cena' },
];

const DAY_LABEL_FORMATTER = new Intl.DateTimeFormat('it-IT', { weekday: 'long', day: 'numeric', month: 'short' });
const WEEK_RANGE_FORMATTER = new Intl.DateTimeFormat('it-IT', { day: 'numeric', month: 'long', year: 'numeric' });

const days = computed(() =>
    Array.from({ length: 7 }, (_, i) => {
        const date = shiftDate(props.weekStart, i);

        return {
            date,
            label: DAY_LABEL_FORMATTER.format(new Date(`${date}T00:00:00`)),
            isToday: date === props.today,
            isWeekend: i === 5 || i === 6,
        };
    }),
);

const formattedWeekRange = computed(() => {
    const start = WEEK_RANGE_FORMATTER.format(new Date(`${props.weekStart}T00:00:00`));
    const end = WEEK_RANGE_FORMATTER.format(new Date(`${shiftDate(props.weekStart, 6)}T00:00:00`));

    return `${start} - ${end}`;
});

// A local, mutable copy so drag-and-drop can move a meal between days/slots
// instantly (before the server confirms) - resynced whenever fresh props
// arrive (e.g. after the move request completes, or if it was rejected).
const localMeals = reactive<Meal[]>([...props.meals]);

watch(
    () => props.meals,
    (meals) => localMeals.splice(0, localMeals.length, ...meals),
);

function mealsFor(date: string, type: MealType) {
    return localMeals.filter((meal) => meal.meal_date === date && meal.meal_type === type).sort((a, b) => a.position - b.position);
}

function slotKey(date: string, type: MealType) {
    return `${date}::${type}`;
}

const draggingMealId = ref<number | null>(null);
const dragOverKey = ref<string | null>(null);

function onDragStart(meal: Meal, event: DragEvent) {
    draggingMealId.value = meal.id;
    event.dataTransfer?.setData('text/plain', String(meal.id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragEnd() {
    draggingMealId.value = null;
    dragOverKey.value = null;
}

function onDragOver(date: string, type: MealType, event: DragEvent) {
    event.preventDefault();
    dragOverKey.value = slotKey(date, type);
}

function onDrop(date: string, type: MealType, event: DragEvent) {
    event.preventDefault();
    const mealId = draggingMealId.value;
    dragOverKey.value = null;
    draggingMealId.value = null;

    if (mealId === null) {
        return;
    }

    const meal = localMeals.find((candidate) => candidate.id === mealId);

    if (!meal || (meal.meal_date === date && meal.meal_type === type)) {
        return;
    }

    meal.meal_date = date;
    meal.meal_type = type;

    router.patch(mealRoutes.move(mealId).url, { meal_date: date, meal_type: type }, { preserveScroll: true, preserveState: true });
}

function destroyMeal(meal: Meal) {
    if (confirm(`Eliminare "${meal.title}"?`)) {
        router.delete(mealRoutes.destroy(meal.id).url, { preserveScroll: true });
    }
}

const isAddMealOpen = ref(false);
const addMealTarget = ref<{ date: string; label: string; type: MealType; typeLabel: string } | null>(null);

function openAddDialog(date: string, day: { label: string }, type: MealType, typeLabel: string) {
    addMealTarget.value = { date, label: day.label, type, typeLabel };
    isAddMealOpen.value = true;
}
</script>

<template>
    <Head title="Pasti" />

    <div class="flex flex-col space-y-8 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <h2 class="text-xl font-semibold tracking-tight">Pasti</h2>
                <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <Button variant="outline" size="icon-sm" title="Settimana precedente" @click="goToWeek(previousWeekStart)">
                        <ChevronLeft />
                    </Button>
                    <span class="capitalize">{{ formattedWeekRange }}</span>
                    <Button variant="outline" size="icon-sm" title="Settimana successiva" @click="goToWeek(nextWeekStart)">
                        <ChevronRight />
                    </Button>
                    <Button v-if="!isCurrentWeek" variant="ghost" size="sm" @click="goToWeek(today)">Torna a questa settimana</Button>
                </div>
            </div>
        </div>

        <div class="-mx-4 snap-x snap-mandatory overflow-x-auto pb-2 sm:mx-0 sm:snap-none">
            <div class="flex sm:grid sm:grid-flow-col sm:auto-cols-[17rem] sm:gap-4">
                <div
                    v-for="day in days"
                    :key="day.date"
                    class="w-screen shrink-0 snap-center px-4 sm:w-auto sm:shrink sm:snap-align-none sm:px-0"
                >
                    <div
                        class="flex h-full flex-col gap-4 rounded-xl p-4 sm:p-5"
                        :class="day.isToday || day.isWeekend ? 'bg-primary/10' : 'bg-muted/40'"
                    >
                        <h3 class="px-1 text-base font-medium capitalize sm:text-sm">{{ day.label }}</h3>

                        <template v-for="(slot, index) in MEAL_TYPES" :key="slot.type">
                            <div v-if="index > 0" class="border-t border-border/60" />

                            <div
                                class="flex min-h-[7rem] flex-col gap-2 rounded-lg p-2 transition-colors sm:p-3"
                                :class="dragOverKey === slotKey(day.date, slot.type) ? 'bg-muted/70 ring-2 ring-primary/40' : ''"
                                @dragover="onDragOver(day.date, slot.type, $event)"
                                @dragleave="dragOverKey = dragOverKey === slotKey(day.date, slot.type) ? null : dragOverKey"
                                @drop="onDrop(day.date, slot.type, $event)"
                            >
                                <div class="flex items-center justify-between px-1">
                                    <h4 class="text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ slot.label }}</h4>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="size-6"
                                        title="Aggiungi pasto"
                                        @click="openAddDialog(day.date, day, slot.type, slot.label)"
                                    >
                                        <Plus class="size-3.5" />
                                    </Button>
                                </div>

                                <div
                                    v-for="meal in mealsFor(day.date, slot.type)"
                                    :key="meal.id"
                                    draggable="true"
                                    class="group flex items-start gap-2 rounded-lg px-2 py-2 text-sm select-none hover:bg-background/60"
                                    :class="draggingMealId === meal.id ? 'opacity-40' : ''"
                                    @dragstart="onDragStart(meal, $event)"
                                    @dragend="onDragEnd"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium">{{ meal.title }}</p>
                                        <p v-if="meal.description" class="mt-0.5 line-clamp-2 text-xs whitespace-pre-line text-muted-foreground">
                                            {{ meal.description }}
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="size-6 shrink-0 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive"
                                        title="Elimina pasto"
                                        @click="destroyMeal(meal)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>

                                <p v-if="mealsFor(day.date, slot.type).length === 0" class="px-1 text-xs text-muted-foreground">Nessun pasto</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:open="isAddMealOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle v-if="addMealTarget" class="capitalize">{{ addMealTarget.label }} · {{ addMealTarget.typeLabel }}</DialogTitle>
                </DialogHeader>
                <Form
                    v-if="addMealTarget"
                    v-bind="MealController.store.form()"
                    reset-on-success
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="isAddMealOpen = false"
                >
                    <input type="hidden" name="meal_date" :value="addMealTarget.date" />
                    <input type="hidden" name="meal_type" :value="addMealTarget.type" />
                    <div class="grid gap-2">
                        <Label for="title">Titolo</Label>
                        <Input id="title" name="title" placeholder="Es. Pasta al pomodoro" required autofocus />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Descrizione (opzionale)</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            placeholder="Dettagli aggiuntivi..."
                            class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive w-full min-w-0 resize-y rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm"
                        ></textarea>
                        <InputError :message="errors.description" />
                    </div>
                    <Button type="submit" :disabled="processing">Aggiungi pasto</Button>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
