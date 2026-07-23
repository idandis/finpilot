<script setup lang="ts">
import { computed } from 'vue';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { InvestmentPositions } from '@/types';

const props = defineProps<{
    positions: InvestmentPositions;
}>();

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

const totalOpenInvested = computed(() =>
    props.positions.open.reduce((sum, position) => sum + position.invested, 0),
);

const totalRealizedGain = computed(() =>
    props.positions.closed.reduce(
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
            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>Strumento</TableHead>
                        <TableHead class="text-right">Quantità</TableHead>
                        <TableHead class="text-right">Investito</TableHead>
                        <TableHead class="text-right">Prezzo medio</TableHead>
                        <TableHead>Apertura</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="position in positions.open"
                        :key="position.isin"
                    >
                        <TableCell>
                            <span class="block truncate font-medium">{{
                                position.name
                            }}</span>
                            <span class="text-xs text-muted-foreground">{{
                                position.isin
                            }}</span>
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
                        <TableCell />
                        <TableCell class="text-right font-medium">
                            {{ formatCurrency(totalOpenInvested) }}
                        </TableCell>
                        <TableCell />
                        <TableCell />
                    </TableRow>
                </TableFooter>
            </Table>
        </div>

        <div>
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
                        <TableHead>Strumento</TableHead>
                        <TableHead class="text-right">Investito</TableHead>
                        <TableHead class="text-right">Incassato</TableHead>
                        <TableHead class="text-right"
                            >Plus/minusvalenza</TableHead
                        >
                        <TableHead>Apertura</TableHead>
                        <TableHead>Chiusura</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="position in positions.closed"
                        :key="position.isin"
                    >
                        <TableCell>
                            <span class="block truncate font-medium">{{
                                position.name
                            }}</span>
                            <span class="text-xs text-muted-foreground">{{
                                position.isin
                            }}</span>
                        </TableCell>
                        <TableCell class="text-right">
                            {{ formatCurrency(position.invested) }}
                        </TableCell>
                        <TableCell class="text-right">
                            {{ formatCurrency(position.received) }}
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
                        <TableCell />
                        <TableCell />
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
                    </TableRow>
                </TableFooter>
            </Table>
        </div>
    </div>
</template>
