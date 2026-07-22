<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Finance/CategoryController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import * as categoryRoutes from '@/routes/categories';
import type { TransactionCategory } from '@/types';

defineProps<{
    categories: TransactionCategory[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categorie', href: categoryRoutes.index() }],
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

function isOwn(category: TransactionCategory) {
    return category.user_id === currentUserId.value;
}

const editingId = ref<number | null>(null);
const editingName = ref('');

function startEditingName(category: TransactionCategory) {
    editingId.value = category.id;
    editingName.value = category.name;
}

function cancelEditingName() {
    editingId.value = null;
}

function saveName(category: TransactionCategory) {
    const value = editingName.value.trim();
    editingId.value = null;

    if (value === '' || value === category.name) {
        return;
    }

    router.patch(
        categoryRoutes.update(category.id).url,
        { name: value },
        { preserveScroll: true, preserveState: true },
    );
}

function updateColor(category: TransactionCategory, value: string) {
    router.patch(
        categoryRoutes.update(category.id).url,
        { color: value },
        { preserveScroll: true, preserveState: true },
    );
}

function destroyCategory(category: TransactionCategory) {
    if (
        confirm(
            `Eliminare la categoria "${category.name}"? Le transazioni già assegnate diventeranno non categorizzate.`,
        )
    ) {
        router.delete(categoryRoutes.destroy(category.id).url, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Categorie" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            title="Categorie"
            description="Le categorie di sistema sono condivise e non modificabili; quelle che crei tu sono completamente tue"
        />

        <Form
            v-bind="CategoryController.store.form()"
            reset-on-success
            class="flex flex-wrap items-end gap-4 rounded-lg border p-4"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Nuova categoria</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    placeholder="Es. Animali domestici"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="color">Colore</Label>
                <Input
                    id="color"
                    name="color"
                    type="color"
                    default-value="#64748b"
                    class="h-9 w-16 p-1"
                />
            </div>

            <Button type="submit" :disabled="processing">Aggiungi</Button>
        </Form>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Nome</TableHead>
                    <TableHead>Colore</TableHead>
                    <TableHead>Tipo</TableHead>
                    <TableHead class="text-right">Azioni</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="category in categories" :key="category.id">
                    <TableCell>
                        <input
                            v-if="editingId === category.id"
                            v-model="editingName"
                            type="text"
                            autofocus
                            class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            @keyup.enter="saveName(category)"
                            @keyup.esc="cancelEditingName"
                            @blur="saveName(category)"
                        />
                        <button
                            v-else-if="isOwn(category)"
                            type="button"
                            class="hover:underline"
                            title="Modifica nome"
                            @click="startEditingName(category)"
                        >
                            {{ category.name }}
                        </button>
                        <span v-else>{{ category.name }}</span>
                    </TableCell>
                    <TableCell>
                        <input
                            type="color"
                            :value="category.color ?? '#94a3b8'"
                            :disabled="!isOwn(category)"
                            class="h-8 w-8 rounded border p-0 disabled:opacity-50"
                            @change="
                                updateColor(
                                    category,
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                    </TableCell>
                    <TableCell>
                        <Badge v-if="category.is_system" variant="secondary">
                            Sistema
                        </Badge>
                        <Badge v-else variant="outline">Personale</Badge>
                    </TableCell>
                    <TableCell class="text-right">
                        <Button
                            v-if="isOwn(category)"
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            title="Elimina categoria"
                            @click="destroyCategory(category)"
                        >
                            <Trash2 />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
