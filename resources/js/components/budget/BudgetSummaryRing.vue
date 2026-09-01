<script setup lang="ts">
import { computed } from 'vue';
import { formatAmount } from '@/lib/balance-sheet-format';

const props = withDefaults(defineProps<{
    label: string;
    amount: number;
    expected: number;
    expectedLabel: string;
    size?: 'md' | 'lg';
    overIsBad?: boolean;
    accent?: boolean;
}>(), { size: 'md', overIsBad: false, accent: false });

const RADIUS = 46;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

// L'anello mostra quanto l'effettivo pesa sull'atteso. Senza un atteso non
// c'è proporzione da disegnare: l'anello è pieno se qualcosa è successo.
const ratio = computed(() => {
    if (props.expected === 0) return props.amount === 0 ? 0 : 1;

    return Math.abs(props.amount) / Math.abs(props.expected);
});

// Stessi colori delle barre della pagina. Sforare è un male solo sulle uscite:
// incassare o avanzare più dell'atteso resta verde.
const isBad = computed(() => (props.overIsBad && ratio.value > 1) || props.amount < 0);

// L'anello in evidenza porta il colore dell'app, gli altri il verde/rosso.
const tone = computed(() => {
    if (isBad.value) return { stroke: 'stroke-red-500', text: 'text-red-500' };

    if (props.accent) return { stroke: 'stroke-primary', text: 'text-primary' };

    return { stroke: 'stroke-emerald-500', text: 'text-emerald-500' };
});

const dash = computed(() => Math.min(ratio.value, 1) * CIRCUMFERENCE);

const percentage = computed(() => Math.round(ratio.value * 100));

// Gli anelli vivono solo su mobile: sono dimensionati per riempire la
// larghezza dello schermo, non per scalare col viewport.
const sizeClass = computed(() => props.size === 'lg' ? 'w-40' : 'w-32');

const valueClass = computed(() => props.size === 'lg' ? 'text-2xl' : 'text-xl');
</script>

<template>
    <div
        class="relative aspect-square"
        :class="sizeClass"
        :title="`${label}: ${formatAmount(amount)} · ${percentage}% di ${expectedLabel} ${formatAmount(expected)}`"
    >
        <svg viewBox="0 0 100 100" class="size-full -rotate-90">
            <!-- Il grigio delle card è traslucido: lo stendo sopra un disco pieno
                 del colore della pagina, così il risultato è identico ma opaco e
                 continua a coprire l'anello di quello dietro. -->
            <circle cx="50" cy="50" :r="RADIUS" class="fill-background" />
            <circle cx="50" cy="50" :r="RADIUS" class="fill-muted/60 dark:fill-muted/50" />
            <circle
                cx="50"
                cy="50"
                :r="RADIUS"
                fill="none"
                stroke-width="3"
                class="stroke-foreground/10"
            />
            <circle
                v-if="dash > 0"
                cx="50"
                cy="50"
                :r="RADIUS"
                fill="none"
                stroke-width="3"
                stroke-linecap="round"
                :stroke-dasharray="`${dash} ${CIRCUMFERENCE - dash}`"
                class="transition-all"
                :class="tone.stroke"
            />
        </svg>

        <div class="absolute inset-0 flex flex-col items-center justify-center px-3 text-center">
            <span class="w-full truncate font-semibold tabular-nums" :class="valueClass">
                {{ formatAmount(amount) }}
            </span>
            <span class="w-full truncate text-xs leading-tight text-muted-foreground">
                {{ label }}
            </span>
            <span class="w-full truncate text-[10px] leading-tight text-muted-foreground/70 tabular-nums">
                {{ expectedLabel }} {{ formatAmount(expected) }}
            </span>
        </div>
    </div>
</template>
