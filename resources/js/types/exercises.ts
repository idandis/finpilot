export type Exercise = {
    id: number;
    name: string;
    category: string;
    requires_equipment: boolean;
};

/** Category key -> Italian label, in canonical display order. */
export type ExerciseCategories = Record<string, string>;
