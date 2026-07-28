export type WorkoutSet = {
    id: number;
    set_number: number;
    completed: boolean;
};

export type WorkoutExercise = {
    id: number;
    exercise_id: number;
    exercise_name: string;
    category: string;
    sets_count: number;
    reps_count: number;
    position: number;
    sets: WorkoutSet[];
};

export type Workout = {
    id: number;
    workout_date: string;
    title: string | null;
    exercises: WorkoutExercise[];
};
