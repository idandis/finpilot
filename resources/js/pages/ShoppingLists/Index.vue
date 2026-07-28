<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import ShoppingListController from '@/actions/App/Http/Controllers/ShoppingListController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import * as shoppingListRoutes from '@/routes/shopping-lists';
import type { ShoppingList } from '@/types';

defineProps<{
    lists: ShoppingList[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Lista della spesa', href: shoppingListRoutes.index() }],
    },
});

function destroyList(list: ShoppingList) {
    if (confirm(`Eliminare la lista "${list.name}"? Verranno eliminati anche tutti i prodotti al suo interno.`)) {
        router.delete(ShoppingListController.destroy(list.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Lista della spesa" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <Heading title="Lista della spesa" description="Crea più liste e tieni i prodotti sempre organizzati per categoria." />

        <Form v-bind="ShoppingListController.store.form()" reset-on-success class="flex items-start gap-2" v-slot="{ errors, processing }">
            <div class="flex-1">
                <Input name="name" placeholder="Nome nuova lista" required />
                <InputError :message="errors.name" />
            </div>
            <Button type="submit" :disabled="processing" class="shrink-0">Crea lista</Button>
        </Form>

        <div v-if="lists.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
            Nessuna lista ancora: creane una qui sopra per iniziare.
        </div>

        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <Link
                v-for="list in lists"
                :key="list.id"
                :href="shoppingListRoutes.show(list.id)"
                class="group relative rounded-xl bg-muted p-3 transition hover:bg-muted/70"
            >
                <button
                    type="button"
                    class="absolute top-2 right-2 rounded-sm p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive"
                    title="Elimina lista"
                    @click.stop.prevent="destroyList(list)"
                >
                    <Trash2 class="size-4" />
                </button>
                <p class="pr-6 font-medium break-words">{{ list.name }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ list.items.length === 1 ? '1 prodotto' : `${list.items.length} prodotti` }}
                </p>
            </Link>
        </div>
    </div>
</template>
