<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
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
import * as overviewRoutes from '@/routes/overview';
import type { MonthlyOverview, YearlyOverview } from '@/types';

defineProps<{
    overview: YearlyOverview[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panoramica', href: overviewRoutes.index() }],
    },
});

const currency = 'EUR';

function formatCurrency(value: number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency,
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
    <Head title="Panoramica" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            title="Panoramica"
            description="Entrate e uscite mese per mese, su tutti i tuoi conti"
        />

        <div
            v-if="overview.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Nessuna transazione ancora. Importa un estratto conto da una carta
            per iniziare.
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
                                    net(row) < 0
                                        ? 'text-red-600'
                                        : 'text-green-600'
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
                            <TableCell
                                class="text-right font-medium text-red-600"
                            >
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
                                        year.totals.income -
                                            year.totals.expense,
                                    )
                                }}
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>
            </TabsContent>
        </Tabs>
    </div>
</template>
