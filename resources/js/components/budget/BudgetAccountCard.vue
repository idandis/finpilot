<script setup lang="ts">
import { Wifi } from '@lucide/vue';
import { computed } from 'vue';
import { formatCurrency } from '@/lib/balance-sheet-format';
import { accountIcon, accountTypeLabels } from '@/lib/budget-accounts';
import { cardGradient } from '@/lib/card-gradient';

/**
 * Il conto disegnato come la carta che hai in tasca.
 *
 * Vive solo dentro al pannello di modifica, dove ce n'è una sola e grande:
 * lì la plastica aiuta a riconoscere il conto mentre ne scegli colore e
 * icona. Nell'elenco, dove i conti sono tanti, si usa la riga sobria.
 */
const props = defineProps<{
    account: {
        name: string;
        type: string;
        bank_name: string | null;
        holder_name: string | null;
        iban: string | null;
        color: string | null;
        icon: string | null;
        balance: number;
    };
}>();

const gradient = computed(() => cardGradient(props.account.color || '#1e293b'));

/**
 * Le ultime quattro cifre dell'IBAN al posto del numero della carta: sono
 * quelle che si leggono sull'estratto conto per riconoscere il conto.
 */
const lastFour = computed(() => {
    const iban = props.account.iban?.replace(/\s/g, '') ?? '';

    return iban.length >= 4 ? iban.slice(-4) : '••••';
});

const typeLabel = computed(
    () => accountTypeLabels[props.account.type] ?? props.account.type,
);
</script>

<template>
    <div
        class="relative flex aspect-[1.586/1] w-full flex-col justify-between overflow-hidden rounded-2xl p-5 text-white shadow-lg"
        :style="{ backgroundImage: gradient }"
    >
        <!-- Il riflesso della luce sulla plastica -->
        <span
            class="pointer-events-none absolute -top-16 -right-12 size-48 rounded-full bg-white/15 blur-2xl"
        />

        <div class="relative flex items-start justify-between">
            <div
                class="h-7 w-9 rounded-md bg-gradient-to-br from-yellow-200 via-yellow-300 to-yellow-500/80"
            />
            <Wifi class="size-5 rotate-90 opacity-90" />
        </div>

        <div class="relative">
            <p class="text-[10px] tracking-widest text-white/60 uppercase">
                Saldo
            </p>
            <p class="text-2xl font-semibold tabular-nums">
                {{ formatCurrency(account.balance) }}
            </p>
            <p class="mt-1 font-mono text-sm tracking-widest text-white/70">
                •••• {{ lastFour }}
            </p>
        </div>

        <div class="relative flex items-end justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium">{{ account.name }}</p>
                <p
                    class="truncate text-[11px] tracking-wide text-white/70 uppercase"
                >
                    {{ account.holder_name ?? typeLabel }}
                </p>
            </div>

            <div
                class="flex shrink-0 items-center gap-1.5 text-sm font-bold tracking-wide uppercase italic"
            >
                <span v-if="account.bank_name" class="truncate">
                    {{ account.bank_name }}
                </span>
                <component
                    :is="accountIcon(account.icon)"
                    v-else
                    class="size-5"
                />
            </div>
        </div>
    </div>
</template>
