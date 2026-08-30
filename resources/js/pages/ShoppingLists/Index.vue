<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, ShoppingBasket, Trash2, Users } from '@lucide/vue';
import { ref } from 'vue';
import ShoppingListController from '@/actions/App/Http/Controllers/ShoppingListController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SharedWith from '@/components/SharedWith.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as shoppingListRoutes from '@/routes/shopping-lists';
import type { SharedPerson, ShoppingListDetail } from '@/types';

defineProps<{
    lists: ShoppingListDetail[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Lista della spesa', href: shoppingListRoutes.index() }],
    },
});

const page = usePage();

// Creating and renaming both happen in a modal, so the grid stays the only
// thing on the page.
const isAddOpen = ref(false);
const listBeingRenamed = ref<ShoppingListDetail | null>(null);

function destroyList(list: ShoppingListDetail) {
    if (confirm(`Eliminare la lista "${list.name}"? Verranno eliminati anche tutti i prodotti al suo interno.`)) {
        router.delete(ShoppingListController.destroy(list.id).url, {
            preserveScroll: true,
        });
    }
}

// How much of the shopping is already done, for the card's progress bar.
function purchasedCount(list: ShoppingListDetail): number {
    return list.items.filter((item) => item.purchased).length;
}

function progress(list: ShoppingListDetail): number {
    return list.items.length === 0 ? 0 : Math.round((purchasedCount(list) / list.items.length) * 100);
}

// A taste of what is still to buy, so a card says something without opening it.
function stillToBuy(list: ShoppingListDetail): string {
    const missing = list.items.filter((item) => !item.purchased);

    if (missing.length === 0) {
        return list.items.length === 0 ? 'Lista vuota' : 'Tutto preso';
    }

    return (
        missing
            .slice(0, 3)
            .map((item) => item.name)
            .join(' · ') + (missing.length > 3 ? ` +${missing.length - 3}` : '')
    );
}

// Each card carries its own sharing panel, so a list can be shared (and its
// people seen) without opening it first.
function removeMember(list: ShoppingListDetail, person: SharedPerson) {
    if (confirm(`Rimuovere ${person.name} dalla lista "${list.name}"?`)) {
        router.delete(ShoppingListController.destroyMember([list.id, person.id]).url, { preserveScroll: true });
    }
}

function leaveList(list: ShoppingListDetail, person: SharedPerson) {
    if (confirm(`Uscire dalla lista "${list.name}"? Non la vedrai più finché non ti reinvitano.`)) {
        router.delete(ShoppingListController.destroyMember([list.id, person.id]).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Lista della spesa" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Lista della spesa" description="Crea più liste e tieni i prodotti sempre organizzati per categoria." />
            <Button class="shrink-0" @click="isAddOpen = true">
                <Plus />
                Nuova lista
            </Button>
        </div>

        <div v-if="lists.length === 0" class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
            <span class="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <ShoppingBasket class="size-6" />
            </span>
            <p class="text-sm text-muted-foreground">Nessuna lista ancora: creane una per iniziare a fare la spesa.</p>
            <Button variant="outline" @click="isAddOpen = true">
                <Plus />
                Nuova lista
            </Button>
        </div>

        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="list in lists"
                :key="list.id"
                :href="shoppingListRoutes.show(list.id)"
                class="group relative flex flex-col gap-3 rounded-xl border border-transparent bg-muted dark:bg-muted/60 p-4 transition hover:border-border hover:bg-muted"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 space-y-1">
                        <p class="font-medium break-words">{{ list.name }}</p>
                        <Badge v-if="list.is_shared" variant="outline" class="border-primary/40 bg-primary/15 text-primary">
                            <Users />
                            Condivisa con te
                        </Badge>
                    </div>
                    <div
                        v-if="list.is_owner"
                        class="flex shrink-0 items-center gap-0.5 opacity-100 transition sm:opacity-0 sm:group-focus-within:opacity-100 sm:group-hover:opacity-100"
                    >
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground"
                            title="Rinomina lista"
                            @click.stop.prevent="listBeingRenamed = list"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            title="Elimina lista"
                            @click.stop.prevent="destroyList(list)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>

                <div class="space-y-1">
                    <p class="text-xs text-muted-foreground">
                        {{ list.items.length === 0 ? '0 prodotti' : `${purchasedCount(list)} di ${list.items.length} presi` }}
                    </p>
                    <p class="truncate text-xs text-muted-foreground/80">
                        {{ stillToBuy(list) }}
                    </p>
                </div>

                <SharedWith
                    :title="`Condividi &quot;${list.name}&quot;`"
                    :people="list.people"
                    :is-owner="list.is_owner"
                    :current-user-id="page.props.auth.user.id"
                    :invite-form="ShoppingListController.storeMember.form(list.id)"
                    permission-hint="Deve essere già registrata sulla piattaforma. Chi entra può aggiungere, spuntare ed eliminare i prodotti della lista come te."
                    leave-label="Esci dalla lista"
                    @remove="removeMember(list, $event)"
                    @leave="leaveList(list, $event)"
                />

                <!-- Closes the card: how much of the shopping is already done. -->
                <div class="mt-auto h-1.5 overflow-hidden rounded-full bg-background">
                    <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${progress(list)}%` }" />
                </div>
            </Link>
        </div>

        <Dialog v-model:open="isAddOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nuova lista</DialogTitle>
                </DialogHeader>
                <Form
                    v-bind="ShoppingListController.store.form()"
                    reset-on-success
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="isAddOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="list-name">Nome</Label>
                        <Input id="list-name" name="name" placeholder="Es. Spesa settimanale" required autofocus />
                        <InputError :message="errors.name" />
                        <p class="text-xs text-muted-foreground">I prodotti che aggiungi vengono raggruppati per categoria dentro la lista.</p>
                    </div>
                    <Button type="submit" :disabled="processing">Crea lista</Button>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog :open="listBeingRenamed !== null" @update:open="(open: boolean) => (listBeingRenamed = open ? listBeingRenamed : null)">
            <DialogContent v-if="listBeingRenamed">
                <DialogHeader>
                    <DialogTitle>Rinomina lista</DialogTitle>
                </DialogHeader>
                <Form
                    :key="`list-${listBeingRenamed.id}`"
                    v-bind="ShoppingListController.update.form(listBeingRenamed.id)"
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="listBeingRenamed = null"
                >
                    <div class="grid gap-2">
                        <Label for="rename-list-name">Nome</Label>
                        <Input id="rename-list-name" name="name" required autofocus :default-value="listBeingRenamed.name" />
                        <InputError :message="errors.name" />
                    </div>
                    <Button type="submit" :disabled="processing">Salva</Button>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
