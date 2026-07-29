import type { EventType } from './calendar';

export type DashboardWorkoutExercise = {
    id: number;
    exercise_name: string;
    sets_count: number;
    reps_count: number;
};

export type DashboardWorkout = {
    id: number;
    title: string | null;
    exercises: DashboardWorkoutExercise[];
};

export type DashboardEvent = {
    id: number;
    title: string;
    start_at: string;
    end_at: string | null;
    type: EventType | null;
    all_day: boolean;
};
