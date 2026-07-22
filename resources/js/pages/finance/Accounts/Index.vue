<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import * as accountRoutes from '@/routes/accounts';
import type { FinancialAccount } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Conti',
                href: accountRoutes.index(),
            },
        ],
    },
});

defineProps<{
    accounts: FinancialAccount[];
}>();

const accountTypeLabels: Record<string, string> = {
    checking: 'Conto corrente',
    debit_card: 'Carta di debito',
    credit_card: 'Carta di credito',
    prepaid_card: 'Carta prepagata',
    cash: 'Contanti',
};

function destroy(account: FinancialAccount) {
    if (
        confirm(
            `Eliminare il conto "${account.name}"? L'operazione non può essere annullata.`,
        )
    ) {
        router.delete(accountRoutes.destroy(account.id).url);
    }
}
</script>

<template>
    <Head title="Conti" />

    <div class="flex flex-col space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Conti e carte"
                description="Gestisci i tuoi conti finanziari e le carte associate"
            />
            <Button as-child>
                <Link :href="accountRoutes.create()">
                    <Plus />
                    Nuovo conto
                </Link>
            </Button>
        </div>

        <div
            v-if="accounts.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Non hai ancora nessun conto. Creane uno per iniziare a importare i
            tuoi estratti conto.
        </div>

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Nome</TableHead>
                    <TableHead>Tipo</TableHead>
                    <TableHead>Banca</TableHead>
                    <TableHead>Valuta</TableHead>
                    <TableHead>Saldo iniziale</TableHead>
                    <TableHead>Carte</TableHead>
                    <TableHead class="text-right">Azioni</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="account in accounts" :key="account.id">
                    <TableCell class="font-medium">
                        <span
                            v-if="account.color"
                            class="mr-2 inline-block size-2.5 rounded-full align-middle"
                            :style="{ backgroundColor: account.color }"
                        />
                        {{ account.name }}
                    </TableCell>
                    <TableCell>{{
                        accountTypeLabels[account.type] ?? account.type
                    }}</TableCell>
                    <TableCell>{{ account.bank_name ?? '—' }}</TableCell>
                    <TableCell>{{ account.currency }}</TableCell>
                    <TableCell>{{ account.initial_balance }}</TableCell>
                    <TableCell>
                        <Badge v-if="account.cards?.length" variant="secondary">
                            {{ account.cards.length }}
                        </Badge>
                        <span v-else class="text-muted-foreground">—</span>
                    </TableCell>
                    <TableCell class="text-right">
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="accountRoutes.edit(account.id)"
                                    >Modifica</Link
                                >
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                @click="destroy(account)"
                            >
                                Elimina
                            </Button>
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
