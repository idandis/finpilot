<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import AccountController from '@/actions/App/Http/Controllers/Finance/AccountController';
import CardController from '@/actions/App/Http/Controllers/Finance/CardController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as accounts from '@/routes/accounts';
import * as cards from '@/routes/cards';
import type { AccountType, CardType, FinancialAccount } from '@/types';

defineProps<{
    account: FinancialAccount;
    accountTypes: AccountType[];
    cardTypes: CardType[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Conti', href: accounts.index() },
            { title: 'Modifica conto', href: accounts.index() },
        ],
    },
});

const accountTypeLabels: Record<string, string> = {
    checking: 'Conto corrente',
    debit_card: 'Carta di debito',
    credit_card: 'Carta di credito',
    prepaid_card: 'Carta prepagata',
    cash: 'Contanti',
};

const cardTypeLabels: Record<string, string> = {
    debit: 'Debito',
    credit: 'Credito',
    prepaid: 'Prepagata',
};

function destroyCard(card: { id: number; name: string }) {
    if (confirm(`Eliminare la carta "${card.name}"?`)) {
        router.delete(cards.destroy(card.id).url);
    }
}
</script>

<template>
    <Head :title="`Modifica ${account.name}`" />

    <div class="flex flex-col space-y-10 p-4">
        <div class="max-w-lg space-y-6">
            <Heading title="Modifica conto" :description="account.name" />

            <Form
                v-bind="AccountController.update.form(account.id)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Nome</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        :default-value="account.name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="type">Tipo</Label>
                    <select
                        id="type"
                        name="type"
                        required
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option
                            v-for="type in accountTypes"
                            :key="type"
                            :value="type"
                            :selected="type === account.type"
                        >
                            {{ accountTypeLabels[type] ?? type }}
                        </option>
                    </select>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="bank_name">Banca</Label>
                    <Input
                        id="bank_name"
                        name="bank_name"
                        :default-value="account.bank_name ?? ''"
                    />
                    <InputError :message="errors.bank_name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="currency">Valuta</Label>
                        <Input
                            id="currency"
                            name="currency"
                            :default-value="account.currency"
                            maxlength="3"
                        />
                        <InputError :message="errors.currency" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="initial_balance">Saldo iniziale</Label>
                        <Input
                            id="initial_balance"
                            name="initial_balance"
                            type="number"
                            step="0.01"
                            :default-value="account.initial_balance"
                        />
                        <InputError :message="errors.initial_balance" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="color">Colore</Label>
                        <Input
                            id="color"
                            name="color"
                            type="color"
                            :default-value="account.color ?? '#2563eb'"
                            class="h-9 w-full p-1"
                        />
                        <InputError :message="errors.color" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="icon">Icona</Label>
                        <Input
                            id="icon"
                            name="icon"
                            :default-value="account.icon ?? ''"
                        />
                        <InputError :message="errors.icon" />
                    </div>
                </div>

                <Label for="is_active" class="flex items-center space-x-3">
                    <input type="hidden" name="is_active" value="0" />
                    <Checkbox
                        id="is_active"
                        name="is_active"
                        value="1"
                        :default-value="account.is_active"
                    />
                    <span>Conto attivo</span>
                </Label>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="processing"
                        >Salva modifiche</Button
                    >
                </div>
            </Form>
        </div>

        <div class="max-w-lg space-y-4">
            <Heading
                variant="small"
                title="Carte"
                description="Carte associate a questo conto"
            />

            <ul v-if="account.cards?.length" class="space-y-2">
                <li
                    v-for="card in account.cards"
                    :key="card.id"
                    class="flex items-center justify-between rounded-lg border p-3"
                >
                    <div class="space-y-0.5">
                        <p class="text-sm font-medium">{{ card.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ cardTypeLabels[card.type] ?? card.type }}
                            <span v-if="card.last_four_digits">
                                · •••• {{ card.last_four_digits }}</span
                            >
                            <span v-if="card.circuit">
                                · {{ card.circuit }}</span
                            >
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge v-if="!card.is_active" variant="outline"
                            >Disattiva</Badge
                        >
                        <Button
                            variant="destructive"
                            size="sm"
                            @click="destroyCard(card)"
                            >Elimina</Button
                        >
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                Nessuna carta associata a questo conto.
            </p>

            <Form
                v-bind="CardController.store.form(account.id)"
                reset-on-success
                class="space-y-4 rounded-lg border p-4"
                v-slot="{ errors, processing }"
            >
                <p class="text-sm font-medium">Aggiungi una carta</p>

                <div class="grid gap-2">
                    <Label for="card_name">Nome</Label>
                    <Input
                        id="card_name"
                        name="name"
                        required
                        placeholder="Es. Carta Visa"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="card_type">Tipo</Label>
                    <select
                        id="card_type"
                        name="type"
                        required
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option
                            v-for="type in cardTypes"
                            :key="type"
                            :value="type"
                        >
                            {{ cardTypeLabels[type] ?? type }}
                        </option>
                    </select>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="last_four_digits">Ultime 4 cifre</Label>
                        <Input
                            id="last_four_digits"
                            name="last_four_digits"
                            maxlength="4"
                            placeholder="1234"
                        />
                        <InputError :message="errors.last_four_digits" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="circuit">Circuito</Label>
                        <Input
                            id="circuit"
                            name="circuit"
                            placeholder="Visa, Mastercard..."
                        />
                        <InputError :message="errors.circuit" />
                    </div>
                </div>

                <Button type="submit" variant="secondary" :disabled="processing"
                    >Aggiungi carta</Button
                >
            </Form>
        </div>
    </div>
</template>
