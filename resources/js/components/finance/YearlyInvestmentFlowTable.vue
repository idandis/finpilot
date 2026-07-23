<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { MonthlyInvestmentFlow, YearlyInvestmentFlow } from '@/types';

defineProps<{
    overview: YearlyInvestmentFlow[];
}>();

function formatCurrency(value: number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

function monthLabel(month: number) {
    const label = new Date(2000, month - 1, 1).toLocaleDateString('it-IT', {
        month: 'long',
    });

    return label.charAt(0).toUpperCase() + label.slice(1);
}

function net(row: MonthlyInvestmentFlow) {
    return row.rientrato - row.versato;
}

function hasActivity(row: MonthlyInvestmentFlow) {
    return row.versato !== 0 || row.rientrato !== 0;
}
</script>

<template>
    <div
        v-if="overview.length === 0"
        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
    >
        Nessuna transazione ancora.
    </div>

    <Tabs v-else :default-value="String(overview[0].year)">
        <TabsList>
            <TabsTrigger
                v-for="year in overview"
                :key="year.year"
                :value="String(year.year)"
            >
                {{ year.year }}
            </TabsTrigger>
        </TabsList>

        <TabsContent
            v-for="year in overview"
            :key="year.year"
            :value="String(year.year)"
            class="pt-4"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Mese</TableHead>
                        <TableHead class="text-right">Versato</TableHead>
                        <TableHead class="text-right">Rientrato</TableHead>
                        <TableHead class="text-right">Netto</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="row in year.months"
                        :key="row.month"
                        :class="{
                            'text-muted-foreground': !hasActivity(row),
                        }"
                    >
                        <TableCell>{{ monthLabel(row.month) }}</TableCell>
                        <TableCell class="text-right text-red-600">
                            {{ formatCurrency(row.versato) }}
                        </TableCell>
                        <TableCell class="text-right text-green-600">
                            {{ formatCurrency(row.rientrato) }}
                        </TableCell>
                        <TableCell
                            class="text-right font-medium"
                            :class="
                                net(row) < 0 ? 'text-red-600' : 'text-green-600'
                            "
                        >
                            {{ formatCurrency(net(row)) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell class="font-medium"
                            >Totale {{ year.year }}</TableCell
                        >
                        <TableCell class="text-right font-medium text-red-600">
                            {{ formatCurrency(year.totals.versato) }}
                        </TableCell>
                        <TableCell
                            class="text-right font-medium text-green-600"
                        >
                            {{ formatCurrency(year.totals.rientrato) }}
                        </TableCell>
                        <TableCell
                            class="text-right font-medium"
                            :class="
                                year.totals.rientrato - year.totals.versato < 0
                                    ? 'text-red-600'
                                    : 'text-green-600'
                            "
                        >
                            {{
                                formatCurrency(
                                    year.totals.rientrato - year.totals.versato,
                                )
                            }}
                        </TableCell>
                    </TableRow>
                </TableFooter>
            </Table>
        </TabsContent>
    </Tabs>
</template>
