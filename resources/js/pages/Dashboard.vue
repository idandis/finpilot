<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import DashboardQuickActions from '@/components/dashboard/DashboardQuickActions.vue';
import TodayEventsCard from '@/components/dashboard/TodayEventsCard.vue';
import TodayMealsCard from '@/components/dashboard/TodayMealsCard.vue';
import TodayTasksCard from '@/components/dashboard/TodayTasksCard.vue';
import TodayWorkoutCard from '@/components/dashboard/TodayWorkoutCard.vue';
import InvestmentSummaryCards from '@/components/finance/InvestmentSummaryCards.vue';
import PortfolioHistoryChart from '@/components/finance/PortfolioHistoryChart.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import * as investmentRoutes from '@/routes/investments';
import type {
    DashboardEvent,
    DashboardWorkout,
    InvestmentPositions,
    Meal,
    PortfolioHistory,
    Task,
} from '@/types';

const props = defineProps<{
    positions: InvestmentPositions;
    portfolioHistory: PortfolioHistory;
    accountBalance: number | null;
    today: string;
    todayTasks: Pick<Task, 'id' | 'title' | 'status'>[];
    todayMeals: Pick<Meal, 'id' | 'title' | 'meal_type'>[];
    todayWorkout: DashboardWorkout | null;
    todayEvents: DashboardEvent[];
}>();

const user = computed(() => usePage().props.auth.user);

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Buongiorno';
    }

    if (hour < 18) {
        return 'Buon pomeriggio';
    }

    return 'Buonasera';
}

const formattedDate = computed(() =>
    new Intl.DateTimeFormat('it-IT', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(props.today)),
);

const hasInvestments = computed(
    () => props.positions.open.length > 0 || props.positions.closed.length > 0,
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-[72rem] flex-col space-y-8 p-4">
        <div>
            <h1 class="text-2xl font-semibold">
                {{ greeting() }}, {{ user.name }}
            </h1>
            <p class="text-muted-foreground capitalize">
                Oggi è: {{ formattedDate }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <TodayTasksCard :tasks="todayTasks" />
            <TodayMealsCard :meals="todayMeals" />
            <TodayWorkoutCard :workout="todayWorkout" />
            <TodayEventsCard :events="todayEvents" />
        </div>

        <div>
            <div class="flex items-start justify-between gap-4">
                <Heading
                    title="Andamento portafoglio"
                    description="Un riferimento veloce: per il dettaglio vai su Investimenti"
                />
                <Button as-child variant="outline" size="sm" class="shrink-0">
                    <Link :href="investmentRoutes.index()">
                        Vai a Investimenti
                    </Link>
                </Button>
            </div>

            <template v-if="hasInvestments">
                <InvestmentSummaryCards
                    class="mt-4"
                    :positions="positions"
                    :account-balance="accountBalance"
                />
                <PortfolioHistoryChart
                    class="mt-4"
                    :history="portfolioHistory"
                />
            </template>
            <div
                v-else
                class="mt-4 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                Nessun investimento registrato.
            </div>
        </div>

        <div>
            <Heading title="Scorciatoie" />
            <DashboardQuickActions class="mt-4" />
        </div>
    </div>
</template>
