<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import * as categoryRuleRoutes from '@/routes/category-rules';
import type { CategoryRule, TransactionCategory } from '@/types';

defineProps<{
    rules: CategoryRule[];
    categories: TransactionCategory[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Regole categorie', href: categoryRuleRoutes.index() },
        ],
    },
});

const editingId = ref<number | null>(null);
const editingPattern = ref('');

function startEditingPattern(rule: CategoryRule) {
    editingId.value = rule.id;
    editingPattern.value = rule.pattern;
}

function cancelEditingPattern() {
    editingId.value = null;
}

function savePattern(rule: CategoryRule) {
    const value = editingPattern.value.trim();
    editingId.value = null;

    if (value === '' || value === rule.pattern) {
        return;
    }

    router.patch(
        categoryRuleRoutes.update(rule.id).url,
        { pattern: value },
        { preserveScroll: true, preserveState: true },
    );
}

function updateCategory(rule: CategoryRule, value: string) {
    router.patch(
        categoryRuleRoutes.update(rule.id).url,
        { transaction_category_id: Number(value) },
        { preserveScroll: true, preserveState: true },
    );
}

function toggleActive(rule: CategoryRule) {
    router.patch(
        categoryRuleRoutes.update(rule.id).url,
        { is_active: !rule.is_active },
        { preserveScroll: true, preserveState: true },
    );
}

function destroyRule(rule: CategoryRule) {
    if (confirm(`Eliminare la regola "${rule.pattern}"?`)) {
        router.delete(categoryRuleRoutes.destroy(rule.id).url, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Regole categorie" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            title="Regole categorie"
            description="Ogni volta che categorizzi manualmente una transazione, il sistema crea o rinforza una regola per riconoscere automaticamente lo stesso merchant la volta successiva"
        />

        <div
            v-if="rules.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Nessuna regola ancora. Assegna una categoria a una transazione non
            categorizzata per crearne una automaticamente.
        </div>

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Parola chiave</TableHead>
                    <TableHead>Categoria</TableHead>
                    <TableHead>Volte applicata</TableHead>
                    <TableHead>Attiva</TableHead>
                    <TableHead class="text-right">Azioni</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="rule in rules" :key="rule.id">
                    <TableCell>
                        <input
                            v-if="editingId === rule.id"
                            v-model="editingPattern"
                            type="text"
                            autofocus
                            class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            @keyup.enter="savePattern(rule)"
                            @keyup.esc="cancelEditingPattern"
                            @blur="savePattern(rule)"
                        />
                        <button
                            v-else
                            type="button"
                            class="hover:underline"
                            title="Modifica parola chiave"
                            @click="startEditingPattern(rule)"
                        >
                            {{ rule.pattern }}
                        </button>
                    </TableCell>
                    <TableCell>
                        <select
                            :value="rule.transaction_category_id"
                            class="h-8 rounded-md border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            @change="
                                updateCategory(
                                    rule,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </TableCell>
                    <TableCell>
                        <Badge variant="secondary">{{
                            rule.times_applied
                        }}</Badge>
                    </TableCell>
                    <TableCell>
                        <Checkbox
                            :model-value="rule.is_active"
                            @update:model-value="toggleActive(rule)"
                        />
                    </TableCell>
                    <TableCell class="text-right">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            title="Elimina regola"
                            @click="destroyRule(rule)"
                        >
                            <Trash2 />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
