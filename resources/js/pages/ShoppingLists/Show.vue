<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ShoppingListItemController from '@/actions/App/Http/Controllers/ShoppingListItemController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as shoppingListRoutes from '@/routes/shopping-lists';
import type { GroceryCategories, ShoppingList, ShoppingListItem } from '@/types';

const props = defineProps<{
    list: ShoppingList;
    categories: GroceryCategories;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lista della spesa', href: shoppingListRoutes.index() },
            { title: 'Dettaglio lista', href: shoppingListRoutes.index() },
        ],
    },
});

// A local, mutable copy so drag-and-drop (and the purchased toggle) can
// update a card instantly, before the server confirms - resynced whenever
// fresh props arrive.
const localItems = reactive<ShoppingListItem[]>([...props.list.items]);

watch(
    () => props.list.items,
    (items) => localItems.splice(0, localItems.length, ...items),
);

const groupedItems = computed(() =>
    Object.entries(props.categories)
        .map(([key, label]) => ({
            key,
            label,
            items: localItems.filter((item) => item.category === key).sort((a, b) => a.position - b.position),
        }))
        .filter((group) => group.items.length > 0),
);

const draggingItemId = ref<number | null>(null);
const dragOverCategory = ref<string | null>(null);

function onDragStart(item: ShoppingListItem, event: DragEvent) {
    draggingItemId.value = item.id;
    event.dataTransfer?.setData('text/plain', String(item.id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragEnd() {
    draggingItemId.value = null;
    dragOverCategory.value = null;
}

function onDragOver(category: string, event: DragEvent) {
    event.preventDefault();
    dragOverCategory.value = category;
}

function onDrop(category: string, event: DragEvent) {
    event.preventDefault();
    const itemId = draggingItemId.value;
    dragOverCategory.value = null;
    draggingItemId.value = null;

    if (itemId === null) {
        return;
    }

    const item = localItems.find((candidate) => candidate.id === itemId);

    if (!item || item.category === category) {
        return;
    }

    item.category = category;

    router.patch(ShoppingListItemController.move(itemId).url, { category }, { preserveScroll: true, preserveState: true });
}

function toggleItem(item: ShoppingListItem) {
    item.purchased = !item.purchased;

    router.patch(ShoppingListItemController.toggle(item.id).url, {}, { preserveScroll: true, preserveState: true });
}

function destroyItem(item: ShoppingListItem) {
    router.delete(ShoppingListItemController.destroy(item.id).url, { preserveScroll: true });
}

// Controlled (not left to the native form reset) so it keeps the last
// category picked across additions - handy when adding several products
// of the same kind in a row.
const selectedCategory = ref(Object.keys(props.categories)[0]);
</script>

<template>
    <Head :title="list.name" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <Heading :title="list.name" description="Clicca su un prodotto per segnarlo come comprato. Trascinalo per cambiarne la categoria." />

        <Form
            v-bind="ShoppingListItemController.store.form(list.id)"
            reset-on-success
            class="grid grid-cols-1 gap-4 rounded-xl bg-muted/40 p-4 sm:grid-cols-[1fr_1fr_auto]"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Prodotto</Label>
                <Input id="name" name="name" placeholder="Es. Mele" required />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="category">Categoria</Label>
                <select
                    id="category"
                    v-model="selectedCategory"
                    name="category"
                    required
                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                </select>
                <InputError :message="errors.category" />
            </div>
            <Button type="submit" class="self-end" :disabled="processing">Aggiungi prodotto</Button>
        </Form>

        <div v-if="groupedItems.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
            Nessun prodotto in questa lista ancora.
        </div>

        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="group in groupedItems"
                :key="group.key"
                class="rounded-xl bg-muted/40 p-4 transition-colors"
                :class="dragOverCategory === group.key ? 'bg-muted/70 ring-2 ring-primary/40' : ''"
                @dragover="onDragOver(group.key, $event)"
                @dragleave="dragOverCategory = dragOverCategory === group.key ? null : dragOverCategory"
                @drop="onDrop(group.key, $event)"
            >
                <h4 class="mb-2 px-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ group.label }}</h4>
                <div class="space-y-0.5">
                    <div
                        v-for="item in group.items"
                        :key="item.id"
                        draggable="true"
                        class="group flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2 py-2 text-sm select-none hover:bg-background/60"
                        :class="draggingItemId === item.id ? 'opacity-40' : ''"
                        @click="toggleItem(item)"
                        @dragstart="onDragStart(item, $event)"
                        @dragend="onDragEnd"
                    >
                        <span
                            class="min-w-0 truncate"
                            :class="item.purchased ? 'text-muted-foreground line-through' : ''"
                        >
                            {{ item.name }}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="shrink-0 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive"
                            title="Elimina prodotto"
                            @click.stop="destroyItem(item)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
