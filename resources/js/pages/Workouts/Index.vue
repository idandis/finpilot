<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Dumbbell,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ExerciseController from '@/actions/App/Http/Controllers/ExerciseController';
import WorkoutController from '@/actions/App/Http/Controllers/WorkoutController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as exerciseRoutes from '@/routes/exercises';
import * as workoutRoutes from '@/routes/workouts';
import type { Exercise, ExerciseCategories, Workout } from '@/types';

const props = defineProps<{
    weekStart: string;
    today: string;
    workouts: Workout[];
    exercises: Exercise[];
    exerciseCategories: ExerciseCategories;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Allenamenti', href: workoutRoutes.index() }],
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
    router.get(
        workoutRoutes.index.url({ query: { date } }),
        {},
        { preserveScroll: true },
    );
}

const DAY_LABEL_FORMATTER = new Intl.DateTimeFormat('it-IT', {
    weekday: 'long',
    day: 'numeric',
    month: 'short',
});
const WEEK_RANGE_FORMATTER = new Intl.DateTimeFormat('it-IT', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

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
    const start = WEEK_RANGE_FORMATTER.format(
        new Date(`${props.weekStart}T00:00:00`),
    );
    const end = WEEK_RANGE_FORMATTER.format(
        new Date(`${shiftDate(props.weekStart, 6)}T00:00:00`),
    );

    return `${start} - ${end}`;
});

function workoutFor(date: string): Workout | undefined {
    return props.workouts.find((workout) => workout.workout_date === date);
}

function isWorkoutComplete(workout: Workout): boolean {
    return (
        workout.exercises.length > 0 &&
        workout.exercises.every(
            (exercise) =>
                exercise.sets.length > 0 &&
                exercise.sets.every((set) => set.completed),
        )
    );
}

const completedThisWeek = computed(
    () => props.workouts.filter(isWorkoutComplete).length,
);

function destroyWorkout(workout: Workout) {
    if (confirm("Eliminare l'allenamento di questo giorno?")) {
        router.delete(workoutRoutes.destroy(workout.id).url, {
            preserveScroll: true,
        });
    }
}

const groupedExercises = computed(() =>
    Object.entries(props.exerciseCategories)
        .map(([key, label]) => ({
            key,
            label,
            exercises: props.exercises.filter(
                (exercise) => exercise.category === key,
            ),
        }))
        .filter((group) => group.exercises.length > 0),
);

function destroyExercise(exercise: Exercise) {
    if (confirm(`Eliminare l'esercizio "${exercise.name}"?`)) {
        router.delete(exerciseRoutes.destroy(exercise.id).url, {
            preserveScroll: true,
        });
    }
}

// null = closed, 'new' = create dialog, an Exercise = edit dialog for it.
const exerciseDialogTarget = ref<Exercise | 'new' | null>(null);
const isExerciseDialogOpen = computed(
    () => exerciseDialogTarget.value !== null,
);
const editingExercise = computed(() =>
    exerciseDialogTarget.value !== null && exerciseDialogTarget.value !== 'new'
        ? exerciseDialogTarget.value
        : null,
);

function openCreateExerciseDialog() {
    exerciseDialogTarget.value = 'new';
}

function openEditExerciseDialog(exercise: Exercise) {
    exerciseDialogTarget.value = exercise;
}

function closeExerciseDialog() {
    exerciseDialogTarget.value = null;
}

// Uncontrolled rows (no v-model): each renders a real named select/input
// read directly from the DOM by the Form component's FormData collection -
// this array only drives how many rows exist and their positional index.
let nextExerciseRowKey = 0;
const exerciseRows = ref<{ key: number }[]>([]);

function addExerciseRow() {
    exerciseRows.value.push({ key: nextExerciseRowKey++ });
}

function removeExerciseRow(key: number) {
    exerciseRows.value = exerciseRows.value.filter((row) => row.key !== key);
}

const isAddWorkoutOpen = ref(false);
const addWorkoutTarget = ref<{ date: string; label: string } | null>(null);

function openAddWorkoutDialog(date: string, day: { label: string }) {
    addWorkoutTarget.value = { date, label: day.label };
    exerciseRows.value = [];
    addExerciseRow();
    isAddWorkoutOpen.value = true;
}

function closeAddWorkoutDialog() {
    isAddWorkoutOpen.value = false;
    addWorkoutTarget.value = null;
    exerciseRows.value = [];
}
</script>

<template>
    <Head title="Allenamenti" />

    <div class="flex flex-col space-y-8 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div
                    class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <Button
                        variant="outline"
                        size="icon-sm"
                        title="Settimana precedente"
                        @click="goToWeek(previousWeekStart)"
                    >
                        <ChevronLeft />
                    </Button>
                    <span class="capitalize">{{ formattedWeekRange }}</span>
                    <Button
                        variant="outline"
                        size="icon-sm"
                        title="Settimana successiva"
                        @click="goToWeek(nextWeekStart)"
                    >
                        <ChevronRight />
                    </Button>
                    <Button
                        v-if="!isCurrentWeek"
                        variant="ghost"
                        size="sm"
                        @click="goToWeek(today)"
                        >Torna a questa settimana</Button
                    >
                </div>
            </div>
        </div>

        <div v-if="workouts.length > 0" class="flex flex-wrap items-center gap-2">
            <Badge variant="secondary" class="gap-1.5">
                Allenamenti completati
                <span class="font-semibold"
                    >{{ completedThisWeek }}/{{ workouts.length }}</span
                >
            </Badge>
        </div>

        <div
            class="-mx-4 snap-x snap-mandatory overflow-x-auto pb-2 sm:mx-0 sm:snap-none"
        >
            <div
                class="flex sm:grid sm:auto-cols-[17rem] sm:grid-flow-col sm:gap-4"
            >
                <div
                    v-for="day in days"
                    :key="day.date"
                    class="w-screen shrink-0 snap-center px-4 sm:w-auto sm:shrink sm:snap-align-none sm:px-0"
                >
                    <div
                        class="flex h-full flex-col gap-3 rounded-xl p-4 sm:p-5"
                        :class="
                            day.isToday || day.isWeekend
                                ? 'bg-primary/10'
                                : 'bg-muted dark:bg-muted/40'
                        "
                    >
                        <div class="flex items-center justify-between px-1">
                            <h3
                                class="text-base font-medium capitalize sm:text-sm"
                            >
                                {{ day.label }}
                            </h3>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="size-6"
                                title="Aggiungi esercizi"
                                @click="openAddWorkoutDialog(day.date, day)"
                            >
                                <Plus class="size-3.5" />
                            </Button>
                        </div>

                        <template v-if="workoutFor(day.date)">
                            <div
                                class="group flex min-h-[7rem] flex-col gap-2 rounded-lg p-2 sm:p-3"
                            >
                                <Link
                                    :href="
                                        workoutRoutes.show(
                                            workoutFor(day.date)!.id,
                                        )
                                    "
                                    class="flex-1 space-y-2"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <Dumbbell
                                            class="size-3.5 shrink-0 text-primary/70"
                                        />
                                        <span class="truncate text-sm font-medium">{{
                                            workoutFor(day.date)!.title ??
                                            'Allenamento'
                                        }}</span>
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            workoutFor(day.date)!.exercises
                                                .length
                                        }}
                                        {{
                                            workoutFor(day.date)!.exercises
                                                .length === 1
                                                ? 'esercizio'
                                                : 'esercizi'
                                        }}
                                    </p>
                                    <Badge
                                        v-if="
                                            isWorkoutComplete(
                                                workoutFor(day.date)!,
                                            )
                                        "
                                        variant="secondary"
                                        class="gap-1"
                                    >
                                        <CheckCircle2 class="size-3" />
                                        Completato
                                    </Badge>
                                </Link>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="size-6 self-end text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive"
                                    title="Elimina allenamento"
                                    @click="destroyWorkout(workoutFor(day.date)!)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </template>
                        <p
                            v-else
                            class="px-1 text-xs text-muted-foreground"
                        >
                            Nessun allenamento
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-0.5">
                    <h3 class="text-lg font-semibold tracking-tight">
                        Esercizi
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        La tua libreria di esercizi, da usare per comporre gli
                        allenamenti della settimana.
                    </p>
                </div>
                <Button
                    size="sm"
                    class="shrink-0"
                    @click="openCreateExerciseDialog"
                >
                    <Plus />
                    Nuovo esercizio
                </Button>
            </div>

            <div
                v-if="groupedExercises.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Nessun esercizio ancora. Aggiungine uno per iniziare a comporre
                gli allenamenti.
            </div>

            <div v-else class="-mx-4 overflow-x-auto pb-2 sm:mx-0">
                <div
                    class="flex gap-4 px-4 sm:grid sm:auto-cols-[17rem] sm:grid-flow-col sm:px-0"
                >
                    <div
                        v-for="group in groupedExercises"
                        :key="group.key"
                        class="w-[17rem] shrink-0 rounded-xl bg-muted dark:bg-muted/40 p-4 sm:w-auto"
                    >
                        <h4
                            class="mb-2 px-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ group.label }}
                        </h4>
                        <div class="space-y-0.5">
                            <div
                                v-for="exercise in group.exercises"
                                :key="exercise.id"
                                class="group flex items-start gap-2 rounded-lg px-2 py-2 text-sm select-none hover:bg-background/60"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="flex items-center gap-1.5 truncate font-medium"
                                    >
                                        <span
                                            class="size-2 shrink-0 rounded-full bg-primary/70"
                                        />
                                        <span class="truncate">{{
                                            exercise.name
                                        }}</span>
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        {{
                                            exercise.requires_equipment
                                                ? 'Con attrezzi'
                                                : 'Corpo libero'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="flex shrink-0 gap-0.5 opacity-0 group-hover:opacity-100"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="size-6 text-muted-foreground hover:bg-muted"
                                        title="Modifica esercizio"
                                        @click="
                                            openEditExerciseDialog(exercise)
                                        "
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="size-6 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        title="Elimina esercizio"
                                        @click="destroyExercise(exercise)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog
            :open="isExerciseDialogOpen"
            @update:open="
                (open) => {
                    if (!open) closeExerciseDialog();
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        editingExercise ? 'Modifica esercizio' : 'Nuovo esercizio'
                    }}</DialogTitle>
                </DialogHeader>
                <Form
                    :key="
                        editingExercise
                            ? `exercise-${editingExercise.id}`
                            : 'exercise-new'
                    "
                    v-bind="
                        editingExercise
                            ? ExerciseController.update.form(
                                  editingExercise.id,
                              )
                            : ExerciseController.store.form()
                    "
                    :reset-on-success="!editingExercise"
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="closeExerciseDialog"
                >
                    <div class="grid gap-2">
                        <Label for="exercise-name">Nome</Label>
                        <Input
                            id="exercise-name"
                            name="name"
                            placeholder="Es. Squat"
                            required
                            autofocus
                            :default-value="editingExercise?.name"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="exercise-category">Categoria</Label>
                        <select
                            id="exercise-category"
                            name="category"
                            required
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option
                                v-for="(label, key) in exerciseCategories"
                                :key="key"
                                :value="key"
                                :selected="
                                    editingExercise
                                        ? editingExercise.category === key
                                        : undefined
                                "
                            >
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="errors.category" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            id="exercise-equipment"
                            type="checkbox"
                            name="requires_equipment"
                            value="1"
                            class="size-4 rounded border-input"
                            :checked="editingExercise?.requires_equipment"
                        />
                        <Label for="exercise-equipment" class="font-normal"
                            >Richiede attrezzi (altrimenti a corpo
                            libero)</Label
                        >
                        <InputError :message="errors.requires_equipment" />
                    </div>
                    <Button type="submit" :disabled="processing">{{
                        editingExercise ? 'Salva modifiche' : 'Aggiungi esercizio'
                    }}</Button>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="isAddWorkoutOpen"
            @update:open="
                (open) => {
                    if (!open) closeAddWorkoutDialog();
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle v-if="addWorkoutTarget" class="capitalize"
                        >{{ addWorkoutTarget.label }}</DialogTitle
                    >
                </DialogHeader>
                <div
                    v-if="exercises.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Crea prima almeno un esercizio nella libreria qui sotto.
                </div>
                <Form
                    v-else-if="addWorkoutTarget"
                    v-bind="WorkoutController.store.form()"
                    reset-on-success
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="closeAddWorkoutDialog"
                >
                    <input
                        type="hidden"
                        name="workout_date"
                        :value="addWorkoutTarget.date"
                    />
                    <InputError :message="errors.exercises" />
                    <div
                        v-for="(row, index) in exerciseRows"
                        :key="row.key"
                        class="flex items-start gap-2"
                    >
                        <select
                            :name="`exercises[${index}][exercise_id]`"
                            class="h-9 min-w-0 flex-1 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Seleziona esercizio</option>
                            <option
                                v-for="exercise in exercises"
                                :key="exercise.id"
                                :value="exercise.id"
                            >
                                {{ exercise.name }}
                            </option>
                        </select>
                        <Input
                            :name="`exercises[${index}][sets_count]`"
                            type="number"
                            min="1"
                            max="20"
                            placeholder="Serie"
                            default-value="3"
                            class="w-20 shrink-0"
                        />
                        <Input
                            :name="`exercises[${index}][reps_count]`"
                            type="number"
                            min="1"
                            max="100"
                            placeholder="Rip."
                            default-value="10"
                            class="w-20 shrink-0"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="shrink-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            title="Rimuovi esercizio"
                            @click="removeExerciseRow(row.key)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="justify-self-start"
                        @click="addExerciseRow()"
                    >
                        <Plus class="size-3.5" />
                        Aggiungi esercizio
                    </Button>
                    <Button type="submit" :disabled="processing"
                        >Salva allenamento</Button
                    >
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
