<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import CardController from '@/actions/App/Http/Controllers/Finance/CardController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CARD_ICON_LABELS, CARD_ICON_NAMES } from '@/lib/card-icons';
import * as cards from '@/routes/cards';
import type { Card, CardType, FinancialAccount } from '@/types';

const props = defineProps<{
    card: Card;
    cardTypes: CardType[];
    accounts: Pick<FinancialAccount, 'id' | 'name' | 'bank_name'>[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Carte', href: cards.index() },
            { title: 'Modifica carta', href: cards.index() },
        ],
    },
});

const cardTypeLabels: Record<string, string> = {
    debit: 'Debito',
    credit: 'Credito',
    prepaid: 'Prepagata',
};

function destroyCard() {
    if (confirm(`Eliminare la carta "${props.card.name}"? L'operazione non può essere annullata.`)) {
        router.delete(cards.destroy(props.card.id).url);
    }
}
</script>

<template>
    <Head title="Modifica carta" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading title="Modifica carta" :description="card.name" />

        <Form
            v-bind="CardController.update.form(card.id)"
            class="max-w-lg space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    :default-value="card.name"
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
                        v-for="type in cardTypes"
                        :key="type"
                        :value="type"
                        :selected="type === card.type"
                    >
                        {{ cardTypeLabels[type] ?? type }}
                    </option>
                </select>
                <InputError :message="errors.type" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="color">Colore</Label>
                    <Input
                        id="color"
                        name="color"
                        type="color"
                        :default-value="card.color ?? '#1e293b'"
                        class="h-9 w-full p-1"
                    />
                    <InputError :message="errors.color" />
                </div>

                <div class="grid gap-2">
                    <Label for="icon">Icona</Label>
                    <select
                        id="icon"
                        name="icon"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="" :selected="!card.icon">Nessuna</option>
                        <option
                            v-for="iconName in CARD_ICON_NAMES"
                            :key="iconName"
                            :value="iconName"
                            :selected="iconName === card.icon"
                        >
                            {{ CARD_ICON_LABELS[iconName] }}
                        </option>
                    </select>
                    <InputError :message="errors.icon" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="circuit">Circuito</Label>
                    <Input
                        id="circuit"
                        name="circuit"
                        placeholder="Visa, Mastercard..."
                        :default-value="card.circuit ?? ''"
                    />
                    <InputError :message="errors.circuit" />
                </div>

                <div class="grid gap-2">
                    <Label for="last_four_digits">Ultime 4 cifre</Label>
                    <Input
                        id="last_four_digits"
                        name="last_four_digits"
                        maxlength="4"
                        placeholder="1234"
                        :default-value="card.last_four_digits ?? ''"
                    />
                    <InputError :message="errors.last_four_digits" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="owner_name">Nome intestatario</Label>
                <Input
                    id="owner_name"
                    name="owner_name"
                    placeholder="Es. Mario Rossi"
                    :default-value="card.owner_name ?? ''"
                />
                <InputError :message="errors.owner_name" />
            </div>

            <div class="grid gap-2">
                <Label for="iban">IBAN</Label>
                <Input
                    id="iban"
                    name="iban"
                    maxlength="34"
                    placeholder="IT00X0000000000000000000000"
                    class="uppercase"
                    :default-value="card.iban ?? ''"
                />
                <InputError :message="errors.iban" />
            </div>

            <div class="grid gap-2">
                <Label for="financial_account_id">Conto collegato (opzionale)</Label>
                <select
                    id="financial_account_id"
                    name="financial_account_id"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="" :selected="card.financial_account_id === null">
                        Nessun conto
                    </option>
                    <option
                        v-for="account in accounts"
                        :key="account.id"
                        :value="account.id"
                        :selected="account.id === card.financial_account_id"
                    >
                        {{ account.name }}{{ account.bank_name ? ` · ${account.bank_name}` : '' }}
                    </option>
                </select>
                <InputError :message="errors.financial_account_id" />
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Salva modifiche</Button>
                <Button type="button" variant="destructive" @click="destroyCard">
                    Elimina carta
                </Button>
            </div>
        </Form>
    </div>
</template>
