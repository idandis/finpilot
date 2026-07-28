<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, ChevronLeft, Dumbbell, Trash2 } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import * as workoutSetRoutes from '@/routes/workout-sets';
import * as workoutRoutes from '@/routes/workouts';
import * as workoutExerciseRoutes from '@/routes/workouts/exercises';
import type { Workout, WorkoutExercise, WorkoutSet } from '@/types';

const props = defineProps<{
    workout: Workout;
}>();

function mondayOf(date: string): string {
    const d = new Date(`${date}T00:00:00Z`);
    const isoWeekday = d.getUTCDay() === 0 ? 7 : d.getUTCDay();
    d.setUTCDate(d.getUTCDate() - (isoWeekday - 1));

    return d.toISOString().slice(0, 10);
}

const DATE_LABEL_FORMATTER = new Intl.DateTimeFormat('it-IT', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

const dateLabel = computed(() =>
    DATE_LABEL_FORMATTER.format(new Date(`${props.workout.workout_date}T00:00:00`)),
);

const boardHref = computed(() =>
    workoutRoutes.index.url({ query: { date: mondayOf(props.workout.workout_date) } }),
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Allenamenti', href: workoutRoutes.index() },
            { title: 'Dettaglio allenamento', href: workoutRoutes.index() },
        ],
    },
});

// A local, mutable copy so toggling a set feels instant - resynced whenever
// fresh props arrive (e.g. after the toggle request completes).
const localWorkout = reactive<Workout>({ ...props.workout });

watch(
    () => props.workout,
    (workout) => Object.assign(localWorkout, workout),
);

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

function toggleSet(set: WorkoutSet) {
    set.completed = !set.completed;

    router.patch(
        workoutSetRoutes.toggle(set.id).url,
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function removeExercise(workoutExercise: WorkoutExercise) {
    if (confirm(`Rimuovere "${workoutExercise.exercise_name}" dall'allenamento?`)) {
        router.delete(
            workoutExerciseRoutes.destroy([
                localWorkout.id,
                workoutExercise.id,
            ]).url,
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head title="Allenamento" />

    <div class="flex flex-col space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <Button variant="ghost" size="sm" as-child class="-ml-2">
                    <Link :href="boardHref">
                        <ChevronLeft />
                        Torna alla settimana
                    </Link>
                </Button>
                <h2 class="px-2 text-xl font-semibold capitalize">
                    {{ localWorkout.title ?? 'Allenamento' }} · {{ dateLabel }}
                </h2>
            </div>
            <Badge
                v-if="isWorkoutComplete(localWorkout)"
                variant="secondary"
                class="gap-1.5"
            >
                <CheckCircle2 class="size-3.5" />
                Allenamento completato
            </Badge>
        </div>

        <div
            v-if="localWorkout.exercises.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Nessun esercizio in questo allenamento.
        </div>

        <div v-else class="flex flex-col gap-3">
            <div
                v-for="exercise in localWorkout.exercises"
                :key="exercise.id"
                class="group flex flex-col gap-3 rounded-xl bg-muted/40 p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="space-y-0.5">
                        <p class="flex items-center gap-1.5 font-medium">
                            <Dumbbell class="size-3.5 shrink-0 text-primary/70" />
                            {{ exercise.exercise_name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ exercise.sets_count }} serie ·
                            {{ exercise.reps_count }} ripetizioni
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="size-6 shrink-0 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive"
                        title="Rimuovi esercizio"
                        @click="removeExercise(exercise)"
                    >
                        <Trash2 class="size-3.5" />
                    </Button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="set in exercise.sets"
                        :key="set.id"
                        type="button"
                        :title="`Serie ${set.set_number}`"
                        class="flex size-9 items-center justify-center rounded-full border text-xs font-medium transition-colors"
                        :class="
                            set.completed
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input bg-background text-muted-foreground hover:bg-muted'
                        "
                        @click="toggleSet(set)"
                    >
                        {{ set.set_number }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
