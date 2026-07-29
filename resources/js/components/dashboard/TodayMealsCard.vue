<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { UtensilsCrossed } from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import * as mealRoutes from '@/routes/meals';
import type { Meal, MealType } from '@/types';

const props = defineProps<{
    meals: Pick<Meal, 'id' | 'title' | 'meal_type'>[];
}>();

const mealTypeLabel: Record<MealType, string> = {
    lunch: 'Pranzo',
    dinner: 'Cena',
};

function mealFor(type: MealType) {
    return props.meals.find((meal) => meal.meal_type === type);
}
</script>

<template>
    <Card class="border-none bg-muted/40 shadow-none">
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <UtensilsCrossed class="size-4 text-muted-foreground" />
                Pasti di oggi
            </CardTitle>
        </CardHeader>
        <CardContent>
            <p v-if="meals.length === 0" class="text-sm text-muted-foreground">
                Nessun pasto pianificato per oggi.
            </p>
            <ul v-else class="space-y-2">
                <li
                    v-for="type in ['lunch', 'dinner'] as MealType[]"
                    :key="type"
                    class="flex items-center justify-between gap-2 text-sm"
                >
                    <span class="text-muted-foreground">{{
                        mealTypeLabel[type]
                    }}</span>
                    <span class="truncate">
                        {{ mealFor(type)?.title ?? 'Non pianificato' }}
                    </span>
                </li>
            </ul>
            <Link
                :href="mealRoutes.index()"
                class="mt-3 inline-block text-sm text-primary underline underline-offset-4"
            >
                Gestisci pasti
            </Link>
        </CardContent>
    </Card>
</template>
