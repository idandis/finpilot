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

        <div v-else class="flex flex-wrap gap-4">
            <Link
                v-for="list in lists"
                :key="list.id"
                :href="shoppingListRoutes.show(list.id)"
                class="group relative w-48 shrink-0 rounded-sm bg-amber-100 p-4 shadow-md transition-transform hover:-translate-y-0.5 hover:shadow-lg dark:bg-amber-900/40"
            >
                <button
                    type="button"
                    class="absolute top-2 right-2 rounded-sm p-1 text-amber-900/50 opacity-0 group-hover:opacity-100 hover:bg-amber-900/10 hover:text-amber-900 dark:text-amber-100/50 dark:hover:bg-amber-100/10 dark:hover:text-amber-100"
                    title="Elimina lista"
                    @click.stop.prevent="destroyList(list)"
                >
                    <Trash2 class="size-4" />
                </button>
                <p class="pr-6 font-medium break-words text-amber-950 dark:text-amber-50">{{ list.name }}</p>
                <p class="mt-2 text-xs text-amber-900/70 dark:text-amber-100/70">
                    {{ list.items.length === 1 ? '1 prodotto' : `${list.items.length} prodotti` }}
                </p>
            </Link>
        </div>
    </div>
</template>
