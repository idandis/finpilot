<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Dumbbell } from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import * as workoutRoutes from '@/routes/workouts';
import type { DashboardWorkout } from '@/types';

defineProps<{
    workout: DashboardWorkout | null;
}>();
</script>

<template>
    <Card class="border-none bg-muted dark:bg-muted/40 shadow-none">
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <Dumbbell class="size-4 text-muted-foreground" />
                Allenamento di oggi
            </CardTitle>
        </CardHeader>
        <CardContent>
            <p v-if="!workout" class="text-sm text-muted-foreground">
                Giorno di riposo.
            </p>
            <ul v-else class="space-y-2">
                <li
                    v-for="exercise in workout.exercises"
                    :key="exercise.id"
                    class="flex items-center justify-between gap-2 text-sm"
                >
                    <span class="truncate">{{ exercise.exercise_name }}</span>
                    <span class="text-muted-foreground">
                        {{ exercise.sets_count }}x{{ exercise.reps_count }}
                    </span>
                </li>
            </ul>
            <Link
                :href="
                    workout
                        ? workoutRoutes.show(workout.id)
                        : workoutRoutes.index()
                "
                class="mt-3 inline-block text-sm text-primary underline underline-offset-4"
            >
                Gestisci allenamenti
            </Link>
        </CardContent>
    </Card>
</template>
