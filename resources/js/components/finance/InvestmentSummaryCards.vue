<script setup lang="ts">
import { computed } from 'vue';
import type { InvestmentPositions } from '@/types';

const props = defineProps<{
    positions: InvestmentPositions;
    accountBalance: number | null;
}>();

function formatCurrency(value: number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

function formatCurrencyOrDash(value: number | null) {
    return value === null ? '—' : formatCurrency(value);
}

function formatPercent(value: number) {
    return `${value > 0 ? '+' : ''}${new Intl.NumberFormat('it-IT', {
        maximumFractionDigits: 2,
    }).format(value)}%`;
}

// Current worth of every open position: the cached market value when a
// price is available, falling back to the cost basis so an unpriced
// instrument still contributes something rather than being silently
// dropped from the total.
const investedValue = computed(() =>
    props.positions.open.reduce(
        (sum, position) => sum + (position.market_value ?? position.invested),
        0,
    ),
);

const totalWealth = computed(
    () => investedValue.value + (props.accountBalance ?? 0),
);

const pricedPositions = computed(() =>
    props.positions.open.filter((position) => position.market_value !== null),
);

const unrealizedGain = computed(() =>
    pricedPositions.value.length === 0
        ? null
        : pricedPositions.value.reduce(
              (sum, position) => sum + (position.unrealized_gain ?? 0),
              0,
          ),
);

const unrealizedGainPercent = computed(() => {
    if (unrealizedGain.value === null) {
        return null;
    }

    const pricedInvested = pricedPositions.value.reduce(
        (sum, position) => sum + position.invested,
        0,
    );

    return pricedInvested > 0
        ? (unrealizedGain.value / pricedInvested) * 100
        : null;
});

// Realized since inception: partial sells on still-open positions plus
// fully closed positions - the same `realized_gain` field already shown per
// row in the positions tables, just totaled across both groups.
const realizedGain = computed(
    () =>
        props.positions.open.reduce(
            (sum, position) => sum + position.realized_gain,
            0,
        ) +
        props.positions.closed.reduce(
            (sum, position) => sum + position.realized_gain,
            0,
        ),
);
</script>

<template>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-lg border p-4">
            <p class="text-xs text-muted-foreground">Investito</p>
            <p class="text-lg font-semibold">
                {{ formatCurrency(investedValue) }}
            </p>
        </div>
        <div class="rounded-lg border p-4">
            <p class="text-xs text-muted-foreground">Saldo conto</p>
            <p class="text-lg font-semibold">
                {{ formatCurrencyOrDash(accountBalance) }}
            </p>
        </div>
        <div class="rounded-lg border p-4">
            <p class="text-xs text-muted-foreground">Patrimonio totale</p>
            <p class="text-lg font-semibold">
                {{ formatCurrency(totalWealth) }}
            </p>
        </div>
        <div class="rounded-lg border p-4">
            <p class="text-xs text-muted-foreground">Andamento</p>
            <p
                class="text-lg font-semibold"
                :class="
                    unrealizedGain === null
                        ? 'text-muted-foreground'
                        : unrealizedGain < 0
                          ? 'text-red-600'
                          : 'text-green-600'
                "
            >
                {{ formatCurrencyOrDash(unrealizedGain) }}
            </p>
            <p v-if="unrealizedGainPercent !== null" class="text-xs">
                {{ formatPercent(unrealizedGainPercent) }}
            </p>
        </div>
        <div class="rounded-lg border p-4">
            <p class="text-xs text-muted-foreground">
                Realizzato dall'inizio
            </p>
            <p
                class="text-lg font-semibold"
                :class="
                    realizedGain === 0
                        ? 'text-muted-foreground'
                        : realizedGain < 0
                          ? 'text-red-600'
                          : 'text-green-600'
                "
            >
                {{ formatCurrency(realizedGain) }}
            </p>
        </div>
    </div>
</template>
