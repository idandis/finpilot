<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AccountController from '@/actions/App/Http/Controllers/Finance/AccountController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as accounts from '@/routes/accounts';
import type { AccountType } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Conti', href: accounts.index() },
            { title: 'Nuovo conto', href: accounts.create() },
        ],
    },
});

defineProps<{
    accountTypes: AccountType[];
}>();

const accountTypeLabels: Record<string, string> = {
    checking: 'Conto corrente',
    debit_card: 'Carta di debito',
    credit_card: 'Carta di credito',
    prepaid_card: 'Carta prepagata',
    cash: 'Contanti',
};
</script>

<template>
    <Head title="Nuovo conto" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            title="Nuovo conto"
            description="Registra un nuovo conto o carta"
        />

        <Form
            v-bind="AccountController.store.form()"
            class="max-w-lg space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    placeholder="Es. Conto corrente principale"
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
                    placeholder="Es. Intesa Sanpaolo"
                />
                <InputError :message="errors.bank_name" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="currency">Valuta</Label>
                    <Input
                        id="currency"
                        name="currency"
                        default-value="EUR"
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
                        default-value="0"
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
                        default-value="#2563eb"
                        class="h-9 w-full p-1"
                    />
                    <InputError :message="errors.color" />
                </div>

                <div class="grid gap-2">
                    <Label for="icon">Icona</Label>
                    <Input
                        id="icon"
                        name="icon"
                        placeholder="Nome icona (opzionale)"
                    />
                    <InputError :message="errors.icon" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Crea conto</Button>
            </div>
        </Form>
    </div>
</template>
