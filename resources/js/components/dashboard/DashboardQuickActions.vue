<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    CreditCard,
    Dumbbell,
    KanbanSquare,
    KeyRound,
    PiggyBank,
    ShoppingCart,
    Sprout,
    TrendingUp,
    UtensilsCrossed,
} from '@lucide/vue';
import * as budgetRoutes from '@/routes/budgets';
import * as calendarRoutes from '@/routes/calendar';
import * as cardRoutes from '@/routes/cards';
import * as investmentRoutes from '@/routes/investments';
import * as lifeRoutes from '@/routes/life';
import * as mealRoutes from '@/routes/meals';
import * as passwordRoutes from '@/routes/passwords';
import * as shoppingListRoutes from '@/routes/shopping-lists';
import * as taskRoutes from '@/routes/tasks';
import * as workoutRoutes from '@/routes/workouts';

const actions = [
    {
        title: 'Gestisci pasti',
        href: mealRoutes.index(),
        icon: UtensilsCrossed,
    },
    {
        title: 'Gestisci allenamenti',
        href: workoutRoutes.index(),
        icon: Dumbbell,
    },
    {
        title: 'Crea lista della spesa',
        href: shoppingListRoutes.index(),
        icon: ShoppingCart,
    },
    {
        title: 'Aggiungi ricordo per oggi',
        href: lifeRoutes.index(),
        icon: Sprout,
    },
    { title: 'Nuovo task', href: taskRoutes.index(), icon: KanbanSquare },
    {
        title: 'Apri calendario',
        href: calendarRoutes.index(),
        icon: CalendarDays,
    },
    {
        title: 'Vai a Investimenti',
        href: investmentRoutes.index(),
        icon: TrendingUp,
    },
    {
        title: 'Gestisci budget',
        href: budgetRoutes.index(),
        icon: PiggyBank,
    },
    {
        title: 'Carte di credito',
        href: cardRoutes.index(),
        icon: CreditCard,
    },
    {
        title: 'Nuova password',
        href: passwordRoutes.index(),
        icon: KeyRound,
    },
];
</script>

<template>
    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-7">
        <Link
            v-for="action in actions"
            :key="action.title"
            :href="action.href"
            class="flex flex-col items-center gap-2 rounded-xl bg-muted p-4 text-center transition hover:bg-muted/70"
        >
            <component :is="action.icon" class="size-6" />
            <span class="text-xs text-muted-foreground">{{
                action.title
            }}</span>
        </Link>
    </div>
</template>
