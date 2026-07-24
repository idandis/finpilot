<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import * as positionRoutes from '@/routes/investments/positions';
import type { InvestmentPositions } from '@/types';

const props = withDefaults(
    defineProps<{
        positions: InvestmentPositions;
        showClosed?: boolean;
        linkable?: boolean;
        /** 'cards' renders open positions as a stacked list of cards instead of a table - reads better on narrow/mobile screens. */
        layout?: 'table' | 'cards';
    }>(),
    { showClosed: true, linkable: true, layout: 'table' },
);

function goToPosition(isin: string) {
    if (props.linkable) {
        router.visit(positionRoutes.show(isin).url);
    }
}

function formatCurrency(value: number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

function formatQuantity(value: number) {
    return new Intl.NumberFormat('it-IT', {
        maximumFractionDigits: 8,
    }).format(value);
}

function formatDate(value: string) {
    return new Date(value).toLocaleDateString('it-IT');
}

function formatCurrencyOrDash(value: number | null) {
    return value === null ? '—' : formatCurrency(value);
}

function formatOriginalCurrency(value: number, currency: string) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency,
    }).format(value);
}

function formatPercent(value: number) {
    return `${value > 0 ? '+' : ''}${new Intl.NumberFormat('it-IT', {
        maximumFractionDigits: 2,
    }).format(value)}%`;
}

const totalOpenInvested = computed(() =>
    props.positions.open.reduce((sum, position) => sum + position.invested, 0),
);

const positionsWithMarketValue = computed(() =>
    props.positions.open.filter((position) => position.market_value !== null),
);

const totalMarketValue = computed(() =>
    positionsWithMarketValue.value.length === 0
        ? null
        : positionsWithMarketValue.value.reduce(
              (sum, position) => sum + (position.market_value ?? 0),
              0,
          ),
);

const totalUnrealizedGain = computed(() =>
    positionsWithMarketValue.value.length === 0
        ? null
        : positionsWithMarketValue.value.reduce(
              (sum, position) => sum + (position.unrealized_gain ?? 0),
              0,
          ),
);

const totalRealizedGain = computed(() =>
    props.positions.closed.reduce(
        (sum, position) => sum + position.realized_gain,
        0,
    ),
);

const totalOpenRealizedGain = computed(() =>
    props.positions.open.reduce(
        (sum, position) => sum + position.realized_gain,
        0,
    ),
);
</script>

<template>
    <div class="space-y-8">
        <div>
            <h3 class="mb-4 text-sm font-medium">Posizioni aperte</h3>
            <div
                v-if="positions.open.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Nessuna posizione aperta.
            </div>
            <div v-else-if="layout === 'cards'" class="space-y-3">
                <div
                    v-for="position in positions.open"
                    :key="position.isin"
                    class="rounded-lg border p-3"
                    :class="{ 'cursor-pointer hover:bg-muted/50': linkable }"
                    @click="goToPosition(position.isin)"
                >
                    <!-- Narrow screens: stacked (name+value row, then a stats grid below). -->
                    <div class="lg:hidden">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="flex items-center gap-2 truncate font-medium">
                                    <span class="truncate">{{ position.name }}</span>
                                    <Badge v-if="position.is_crypto" variant="secondary" class="shrink-0">Crypto</Badge>
                                </p>
                                <p class="text-xs text-muted-foreground">{{ position.isin }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="font-semibold">
                                    {{ formatCurrencyOrDash(position.market_value) }}
                                </p>
                                <p
                                    class="text-xs font-medium"
                                    :class="
                                        position.unrealized_gain === null
                                            ? 'text-muted-foreground'
                                            : position.unrealized_gain < 0
                                              ? 'text-red-600'
                                              : 'text-green-600'
                                    "
                                >
                                    {{ formatCurrencyOrDash(position.unrealized_gain) }}
                                    <template v-if="position.unrealized_gain_percent !== null">
                                        ({{ formatPercent(position.unrealized_gain_percent) }})
                                    </template>
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
                            <div>
                                <p class="text-xs text-muted-foreground">Quantità</p>
                                <p class="tabular-nums">{{ formatQuantity(position.quantity) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Investito</p>
                                <p>{{ formatCurrency(position.invested) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Prezzo medio</p>
                                <p>{{ formatCurrency(position.average_price) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Prezzo attuale</p>
                                <p>{{ formatCurrencyOrDash(position.current_price) }}</p>
                                <p
                                    v-if="
                                        position.price_currency &&
                                        position.price_currency !== 'EUR' &&
                                        position.current_price_original !== null
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        formatOriginalCurrency(
                                            position.current_price_original,
                                            position.price_currency,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop: everything in a single compact row. -->
                    <div class="hidden lg:flex lg:items-center lg:gap-6">
                        <div class="w-52 shrink-0">
                            <p class="flex items-center gap-2 truncate font-medium">
                                <span class="truncate">{{ position.name }}</span>
                                <Badge v-if="position.is_crypto" variant="secondary" class="shrink-0">Crypto</Badge>
                            </p>
                            <p class="text-xs text-muted-foreground">{{ position.isin }}</p>
                        </div>

                        <div class="grid flex-1 grid-cols-4 gap-x-4 text-sm">
                            <div>
                                <p class="text-xs text-muted-foreground">Quantità</p>
                                <p class="tabular-nums">{{ formatQuantity(position.quantity) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Investito</p>
                                <p>{{ formatCurrency(position.invested) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Prezzo medio</p>
                                <p>{{ formatCurrency(position.average_price) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Prezzo attuale</p>
                                <p>{{ formatCurrencyOrDash(position.current_price) }}</p>
                                <p
                                    v-if="
                                        position.price_currency &&
                                        position.price_currency !== 'EUR' &&
                                        position.current_price_original !== null
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        formatOriginalCurrency(
                                            position.current_price_original,
                                            position.price_currency,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="w-40 shrink-0 text-right">
                            <p class="font-semibold">
                                {{ formatCurrencyOrDash(position.market_value) }}
                            </p>
                            <p
                                class="text-xs font-medium"
                                :class="
                                    position.unrealized_gain === null
                                        ? 'text-muted-foreground'
                                        : position.unrealized_gain < 0
                                          ? 'text-red-600'
                                          : 'text-green-600'
                                "
                            >
                                {{ formatCurrencyOrDash(position.unrealized_gain) }}
                                <template v-if="position.unrealized_gain_percent !== null">
                                    ({{ formatPercent(position.unrealized_gain_percent) }})
                                </template>
                            </p>
                        </div>
                    </div>

                    <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                        <span>Apertura {{ formatDate(position.opened_at) }}</span>
                        <span
                            v-if="position.realized_gain !== 0"
                            class="font-medium"
                            :class="position.realized_gain < 0 ? 'text-red-600' : 'text-green-600'"
                        >
                            Realizzato: {{ formatCurrency(position.realized_gain) }}
                        </span>
                    </div>
                </div>

                <div class="rounded-lg border bg-muted/30 p-4">
                    <div class="flex items-center justify-between text-sm font-medium">
                        <span>Totale investito</span>
                        <span>{{ formatCurrency(totalOpenInvested) }}</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-sm font-medium">
                        <span>Valore di mercato</span>
                        <span>{{ formatCurrencyOrDash(totalMarketValue) }}</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-sm font-medium">
                        <span>Plus/minusvalenza non realizzata</span>
                        <span
                            :class="
                                totalUnrealizedGain === null
                                    ? 'text-muted-foreground'
                                    : totalUnrealizedGain < 0
                                      ? 'text-red-600'
                                      : 'text-green-600'
                            "
                        >
                            {{ formatCurrencyOrDash(totalUnrealizedGain) }}
                        </span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-sm font-medium">
                        <span>Plus/minusvalenza realizzata</span>
                        <span
                            :class="
                                totalOpenRealizedGain === 0
                                    ? 'text-muted-foreground'
                                    : totalOpenRealizedGain < 0
                                      ? 'text-red-600'
                                      : 'text-green-600'
                            "
                        >
                            {{ formatCurrency(totalOpenRealizedGain) }}
                        </span>
                    </div>
                </div>
            </div>
            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-56">Strumento</TableHead>
                        <TableHead class="text-right">Non realizzato</TableHead>
                        <TableHead class="text-right">Realizzato</TableHead>
                        <TableHead class="text-right">Quantità</TableHead>
                        <TableHead class="text-right">Investito</TableHead>
                        <TableHead class="text-right">Prezzo medio</TableHead>
                        <TableHead class="text-right">Prezzo attuale</TableHead>
                        <TableHead class="text-right"
                            >Valore di mercato</TableHead
                        >
                        <TableHead>Apertura</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="position in positions.open"
                        :key="position.isin"
                        :class="{ 'cursor-pointer hover:bg-muted/50': linkable }"
                        @click="goToPosition(position.isin)"
                    >
                        <TableCell class="max-w-56">
                            <span class="flex items-center gap-2 truncate font-medium">
                                <span class="truncate">{{ position.name }}</span>
                                <Badge v-if="position.is_crypto" variant="secondary" class="shrink-0">Crypto</Badge>
                            </span>
                            <span class="text-xs text-muted-foreground">{{
                                position.isin
                            }}</span>
                        </TableCell>
                        <TableCell
                            class="text-right font-medium"
                            :class="
                                position.unrealized_gain === null
                                    ? 'text-muted-foreground'
                                    : position.unrealized_gain < 0
                                      ? 'text-red-600'
                                      : 'text-green-600'
                            "
                        >
                            <span>{{
                                formatCurrencyOrDash(position.unrealized_gain)
                            }}</span>
                            <span
                                v-if="position.unrealized_gain_percent !== null"
                                class="block text-xs"
                            >
                                {{
                                    formatPercent(
                                        position.unrealized_gain_percent,
                                    )
                                }}
                            </span>
                        </TableCell>
                        <TableCell
                            class="text-right font-medium"
                            :class="
                                position.realized_gain === 0
                                    ? 'text-muted-foreground'
                                    : position.realized_gain < 0
                                      ? 'text-red-600'
                                      : 'text-green-600'
                            "
                        >
                            {{ formatCurrency(position.realized_gain) }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ formatQuantity(position.quantity) }}
                        </TableCell>
                        <TableCell class="text-right">
                            {{ formatCurrency(position.invested) }}
                        </TableCell>
                        <TableCell class="text-right">
                            {{ formatCurrency(position.average_price) }}
                        </TableCell>
                        <TableCell class="text-right">
                            <span>{{
                                formatCurrencyOrDash(position.current_price)
                            }}</span>
                            <span
                                v-if="position.current_price !== null && position.price_date"
                                class="block text-xs text-muted-foreground"
                            >
                                al {{ formatDate(position.price_date) }}
                            </span>
                            <span
                                v-if="
                                    position.price_currency &&
                                    position.price_currency !== 'EUR' &&
                                    position.current_price_original !== null
                                "
                                class="block text-xs text-muted-foreground"
                            >
                                {{
                                    formatOriginalCurrency(
                                        position.current_price_original,
                                        position.price_currency,
                                    )
                                }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right">
                            <span>{{
                                formatCurrencyOrDash(position.market_value)
                            }}</span>
                            <span
                                v-if="
                                    position.price_currency &&
                                    position.price_currency !== 'EUR' &&
                                    position.market_value_original !== null
                                "
                                class="block text-xs text-muted-foreground"
                            >
                                {{
                                    formatOriginalCurrency(
                                        position.market_value_original,
                                        position.price_currency,
                                    )
                                }}
                            </span>
                        </TableCell>
                        <TableCell>{{
                            formatDate(position.opened_at)
                        }}</TableCell>
                    </TableRow>
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell class="font-medium"
                            >Totale investito</TableCell
                        >
                        <TableCell
                            class="text-right font-medium"
                            :class="
                                totalUnrealizedGain === null
                                    ? 'text-muted-foreground'
                                    : totalUnrealizedGain < 0
                                      ? 'text-red-600'
                                      : 'text-green-600'
                            "
                        >
                            {{ formatCurrencyOrDash(totalUnrealizedGain) }}
                        </TableCell>
                        <TableCell
                            class="text-right font-medium"
                            :class="
                                totalOpenRealizedGain === 0
                                    ? 'text-muted-foreground'
                                    : totalOpenRealizedGain < 0
                                      ? 'text-red-600'
                                      : 'text-green-600'
                            "
                        >
                            {{ formatCurrency(totalOpenRealizedGain) }}
                        </TableCell>
                        <TableCell />
                        <TableCell class="text-right font-medium">
                            {{ formatCurrency(totalOpenInvested) }}
                        </TableCell>
                        <TableCell />
                        <TableCell />
                        <TableCell class="text-right font-medium">
                            {{ formatCurrencyOrDash(totalMarketValue) }}
                        </TableCell>
                        <TableCell />
                    </TableRow>
                </TableFooter>
            </Table>
        </div>

        <div v-if="showClosed">
            <h3 class="mb-4 text-sm font-medium">Posizioni chiuse</h3>
            <div
                v-if="positions.closed.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Nessuna posizione chiusa.
            </div>
            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-56">Strumento</TableHead>
                        <TableHead class="text-right"
                            >Plus/minusvalenza</TableHead
                        >
                        <TableHead class="text-right">Investito</TableHead>
                        <TableHead class="text-right">Incassato</TableHead>
                        <TableHead>Apertura</TableHead>
                        <TableHead>Chiusura</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="position in positions.closed"
                        :key="position.isin"
                        :class="{ 'cursor-pointer hover:bg-muted/50': linkable }"
                        @click="goToPosition(position.isin)"
                    >
                        <TableCell class="max-w-56">
                            <span class="flex items-center gap-2 truncate font-medium">
                                <span class="truncate">{{ position.name }}</span>
                                <Badge v-if="position.is_crypto" variant="secondary" class="shrink-0">Crypto</Badge>
                            </span>
                            <span class="text-xs text-muted-foreground">{{
                                position.isin
                            }}</span>
                        </TableCell>
                        <TableCell
                            class="text-right font-medium"
                            :class="
                                position.realized_gain < 0
                                    ? 'text-red-600'
                                    : 'text-green-600'
                            "
                        >
                            {{ formatCurrency(position.realized_gain) }}
                        </TableCell>
                        <TableCell class="text-right">
                            {{ formatCurrency(position.invested) }}
                        </TableCell>
                        <TableCell class="text-right">
                            {{ formatCurrency(position.received) }}
                        </TableCell>
                        <TableCell>{{
                            formatDate(position.opened_at)
                        }}</TableCell>
                        <TableCell>{{
                            formatDate(position.closed_at)
                        }}</TableCell>
                    </TableRow>
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell class="font-medium"
                            >Totale realizzato</TableCell
                        >
                        <TableCell
                            class="text-right font-medium"
                            :class="
                                totalRealizedGain < 0
                                    ? 'text-red-600'
                                    : 'text-green-600'
                            "
                        >
                            {{ formatCurrency(totalRealizedGain) }}
                        </TableCell>
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                    </TableRow>
                </TableFooter>
            </Table>
        </div>
    </div>
</template>
