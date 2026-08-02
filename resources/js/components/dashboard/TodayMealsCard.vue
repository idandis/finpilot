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

function mealsFor(type: MealType) {
    return props.meals.filter((meal) => meal.meal_type === type);
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
            <ul v-else class="space-y-3">
                <li
                    v-for="type in ['lunch', 'dinner'] as MealType[]"
                    :key="type"
                    class="space-y-1"
                >
                    <span class="text-xs font-medium text-muted-foreground">
                        {{ mealTypeLabel[type] }}
                    </span>
                    <ul v-if="mealsFor(type).length > 0" class="space-y-1">
                        <li
                            v-for="meal in mealsFor(type)"
                            :key="meal.id"
                            class="flex min-w-0 text-sm"
                        >
                            <span class="min-w-0 truncate">{{ meal.title }}</span>
                        </li>
                    </ul>
                    <span v-else class="block text-sm text-muted-foreground">
                        Non pianificato
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
