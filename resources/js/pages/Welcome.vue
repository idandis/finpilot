<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { login } from '@/routes';
import { FONT_STACKS } from '@/composables/useAppearance';
import AppMascotIcon from '@/components/AppMascotIcon.vue';
import {
    KanbanSquare,
    KeyRound,
    PiggyBank,
    ShoppingCart,
    TrendingUp,
    UtensilsCrossed,
} from '@lucide/vue';

const features = [
    { icon: PiggyBank, label: 'Budget', description: 'Tieni sotto controllo entrate e uscite senza pensieri.' },
    { icon: TrendingUp, label: 'Investimenti', description: 'Segui il tuo portafoglio e il mercato in un colpo d’occhio.' },
    { icon: KanbanSquare, label: 'Task', description: 'Organizza le cose da fare, da solo o in famiglia.' },
    { icon: UtensilsCrossed, label: 'Pasti', description: 'Pianifica la settimana a tavola senza stress.' },
    { icon: ShoppingCart, label: 'Lista della spesa', description: 'Genera la spesa direttamente dai pasti pianificati.' },
    { icon: KeyRound, label: 'Password', description: 'Custodisci le tue credenziali in un posto sicuro.' },
];
</script>

<template>
    <Head title="ManageMe" />
    <div class="min-h-screen bg-neutral-950 text-white" :style="{ fontFamily: FONT_STACKS.rounded }">
        <div class="mx-auto flex min-h-screen max-w-3xl flex-col px-6 pt-8 pb-28 lg:pb-16">
            <header class="flex items-center gap-2">
                <AppMascotIcon class="size-9" />
                <span class="text-lg font-semibold tracking-tight">ManageMe</span>
            </header>

            <div class="relative mx-auto mt-16 h-24 w-24 animate-float lg:mt-20 lg:h-28 lg:w-28">
                <div
                    class="absolute inset-0 flex -translate-x-3 -translate-y-2 -rotate-12 items-center justify-center rounded-3xl bg-sky-500 shadow-lg shadow-black/30"
                >
                    <ShoppingCart class="h-9 w-9 text-white lg:h-10 lg:w-10" />
                </div>
                <div
                    class="absolute inset-0 flex translate-x-2 -translate-y-1 rotate-6 items-center justify-center rounded-3xl bg-amber-500 shadow-lg shadow-black/30"
                >
                    <KanbanSquare class="h-9 w-9 text-white lg:h-10 lg:w-10" />
                </div>
                <div class="absolute inset-0 flex -rotate-3 items-center justify-center rounded-3xl bg-violet-500 shadow-lg shadow-black/30">
                    <TrendingUp class="h-9 w-9 text-white lg:h-10 lg:w-10" />
                </div>
                <AppMascotIcon class="absolute inset-0 h-full w-full drop-shadow-xl" />
            </div>

            <h1 class="mx-auto mt-10 max-w-2xl text-center text-4xl leading-tight font-extrabold tracking-tight lg:text-6xl">
                <span class="block text-white">Smetti di dire &laquo;mannaggia a me&raquo;.</span>
                <span class="block text-violet-400">Inizia a dire ManageMe.</span>
            </h1>

            <p class="mx-auto mt-6 max-w-lg text-center text-neutral-400 lg:text-lg">
                Dove sono finiti i soldi? Qual era la password? Cosa mangio stasera? Come vanno gli investimenti?
            </p>

            <div class="mt-16 grid grid-cols-2 gap-4 lg:mt-20 lg:grid-cols-3 lg:gap-5">
                <div
                    v-for="feature in features"
                    :key="feature.label"
                    class="rounded-2xl border border-white/10 bg-white/5 p-4 lg:p-5"
                >
                    <component :is="feature.icon" class="h-6 w-6 text-violet-400" />
                    <p class="mt-3 font-semibold">{{ feature.label }}</p>
                    <p class="mt-1 text-sm text-neutral-400">{{ feature.description }}</p>
                </div>
            </div>

            <div class="grow" />

            <div class="fixed inset-x-0 bottom-0 bg-gradient-to-t from-neutral-950 via-neutral-950/95 to-transparent pt-10 pb-6 lg:static lg:mt-16 lg:bg-none lg:pt-0 lg:pb-0">
                <div class="mx-auto max-w-sm px-6 lg:max-w-none lg:px-0">
                    <Link
                        :href="login()"
                        class="flex w-full items-center justify-center rounded-full bg-violet-500 px-6 py-4 text-base font-semibold text-white transition hover:bg-violet-400 lg:mx-auto lg:max-w-xs"
                    >
                        Accedi all’app
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-float {
    animation: float 5.5s ease-in-out infinite;
}

@keyframes float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .animate-float {
        animation: none;
    }
}
</style>
