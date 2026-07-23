<script setup lang="ts">
import CategorySpendingChart from '@/components/finance/CategorySpendingChart.vue';
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
import type { MonthlyOverview, YearlyOverview } from '@/types';

defineProps<{
    overview: YearlyOverview[];
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

function net(row: MonthlyOverview) {
    return row.income - row.expense;
}

function hasActivity(row: MonthlyOverview) {
    return row.income !== 0 || row.expense !== 0;
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
                        <TableHead class="text-right">Entrate</TableHead>
                        <TableHead class="text-right">Uscite</TableHead>
                        <TableHead class="text-right">Saldo</TableHead>
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
                        <TableCell class="text-right text-green-600">
                            {{ formatCurrency(row.income) }}
                        </TableCell>
                        <TableCell class="text-right text-red-600">
                            {{ formatCurrency(row.expense) }}
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
                        <TableCell
                            class="text-right font-medium text-green-600"
                        >
                            {{ formatCurrency(year.totals.income) }}
                        </TableCell>
                        <TableCell class="text-right font-medium text-red-600">
                            {{ formatCurrency(year.totals.expense) }}
                        </TableCell>
                        <TableCell
                            class="text-right font-medium"
                            :class="
                                year.totals.income - year.totals.expense < 0
                                    ? 'text-red-600'
                                    : 'text-green-600'
                            "
                        >
                            {{
                                formatCurrency(
                                    year.totals.income - year.totals.expense,
                                )
                            }}
                        </TableCell>
                    </TableRow>
                </TableFooter>
            </Table>

            <div class="mt-6 rounded-lg border p-4">
                <h3 class="mb-4 text-sm font-medium">
                    Spese per categoria - {{ year.year }}
                </h3>
                <CategorySpendingChart
                    :breakdown="year.categoryBreakdown"
                    currency="EUR"
                    :empty-message="`Nessuna spesa da mostrare per il ${year.year}.`"
                />
            </div>
        </TabsContent>
    </Tabs>
</template>
