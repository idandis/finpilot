<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import budgetCategories from '@/routes/budget-categories';
import budgetSubcategories from '@/routes/budget-subcategories';
import Heading from '@/components/Heading.vue';
import { readableTextOn } from '@/lib/budget-colors';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

type Direction = 'income' | 'expense';

interface Subcategory {
    id: number;
    name: string;
    is_active: boolean;
}

interface Category {
    id: number;
    name: string;
    color: string;
    type: Direction;
    order: number;
    is_active: boolean;
    subcategories: Subcategory[];
}

const props = defineProps<{
    categories: Category[];
}>();

const groups = computed(() => [
    {
        type: 'income' as Direction,
        title: 'Entrate',
        addLabel: 'Entrata',
        emptyLabel: 'Nessuna categoria di entrata.',
        categories: props.categories.filter((category) => category.type === 'income'),
    },
    {
        type: 'expense' as Direction,
        title: 'Uscite',
        addLabel: 'Uscita',
        emptyLabel: 'Nessuna categoria di uscita.',
        categories: props.categories.filter((category) => category.type === 'expense'),
    },
]);

const expandedCategories = ref<Set<number>>(new Set());
const isAddCategoryOpen = ref(false);
const isAddSubcategoryOpen = ref(false);
const selectedCategory = ref<Category | null>(null);

const categoryForm = useForm({
    name: '',
    color: '#3b82f6',
    type: 'expense' as Direction,
    scope: 'global',
});

const subcategoryForm = useForm({
    name: '',
    scope: 'global',
});

const predefinedColors = [
    '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
    '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#06b6d4',
];

const toggleCategory = (category: Category) => {
    if (expandedCategories.value.has(category.id)) {
        expandedCategories.value.delete(category.id);
    } else {
        expandedCategories.value.add(category.id);
    }
};

const openAddCategory = (type: Direction) => {
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryForm.type = type;
    isAddCategoryOpen.value = true;
};

const openAddSubcategory = (category: Category) => {
    selectedCategory.value = category;
    subcategoryForm.reset();
    subcategoryForm.clearErrors();
    isAddSubcategoryOpen.value = true;
};

const submitCategory = () => {
    categoryForm.post(budgetCategories.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            isAddCategoryOpen.value = false;
            categoryForm.reset();
        },
    });
};

const submitSubcategory = () => {
    if (!selectedCategory.value) return;

    subcategoryForm.post(budgetCategories.subcategories.store.url(selectedCategory.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isAddSubcategoryOpen.value = false;
            subcategoryForm.reset();
        },
    });
};

const deleteSubcategory = (subcategory: Subcategory) => {
    const confirmed = confirm(
        `Eliminare "${subcategory.name}" da tutti i mesi? `
        + 'Verranno persi anche gli importi previsti e i movimenti collegati.',
    );

    if (!confirmed) return;

    router.delete(budgetSubcategories.destroy.url(subcategory.id), { preserveScroll: true });
};

const deleteCategory = (category: Category) => {
    const confirmed = confirm(
        `Eliminare "${category.name}" e le sue voci da tutti i mesi? `
        + 'Verranno persi anche gli importi previsti e i movimenti collegati.',
    );

    if (!confirmed) return;

    router.delete(budgetCategories.destroy.url(category.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Configurazione Budget" />

    <div class="mx-auto max-w-2xl p-4">
        <Heading
            title="Configurazione Budget"
            description="La struttura comune a tutti i mesi. Le voci valide per un mese solo si creano dal budget di quel mese."
        />

        <div class="mt-6 space-y-3">
            <template v-for="group in groups" :key="group.type">
                <div class="flex items-center justify-between gap-4 pt-2">
                    <h2 class="font-semibold">{{ group.title }}</h2>
                    <Button size="sm" variant="outline" @click="openAddCategory(group.type)">
                        <Plus class="mr-2 size-4" />
                        {{ group.addLabel }}
                    </Button>
                </div>

                <p
                    v-if="group.categories.length === 0"
                    class="rounded-lg border border-dashed bg-muted dark:bg-muted/30 p-4 text-center text-sm text-muted-foreground"
                >
                    {{ group.emptyLabel }}
                </p>

                <div
                    v-for="category in group.categories"
                    :key="category.id"
                    class="rounded-lg border bg-card"
                >
                    <div class="flex items-center gap-2 pr-2">
                        <button
                            class="flex flex-1 items-center gap-3 px-4 py-3 hover:bg-muted/50"
                            @click="toggleCategory(category)"
                        >
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg text-sm font-semibold tabular-nums"
                                :style="{
                                    backgroundColor: category.color,
                                    color: readableTextOn(category.color),
                                }"
                                :title="`${category.subcategories.length} voci`"
                            >
                                {{ category.subcategories.length }}
                            </div>
                            <span class="flex-1 text-left font-medium">{{ category.name }}</span>
                            <ChevronDown
                                class="size-4 shrink-0 transition-transform"
                                :class="{ 'rotate-180': expandedCategories.has(category.id) }"
                            />
                        </button>

                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="shrink-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            :title="`Elimina la categoria ${category.name}`"
                            @click="deleteCategory(category)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>

                    <div
                        v-if="expandedCategories.has(category.id)"
                        class="border-t bg-muted/30 px-4 py-3"
                    >
                        <div class="mb-4 space-y-2">
                            <div
                                v-for="sub in category.subcategories"
                                :key="sub.id"
                                class="flex items-center justify-between rounded px-2 py-2 hover:bg-muted"
                            >
                                <span class="text-sm">{{ sub.name }}</span>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    @click="deleteSubcategory(sub)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>

                            <p
                                v-if="category.subcategories.length === 0"
                                class="px-2 py-2 text-sm text-muted-foreground"
                            >
                                Nessuna voce.
                            </p>
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full"
                            @click="openAddSubcategory(category)"
                        >
                            <Plus class="mr-2 size-3" />
                            {{ category.type === 'income' ? 'Voce' : 'Sottocategoria' }}
                        </Button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Sheet: Aggiungi Categoria -->
    <Sheet v-model:open="isAddCategoryOpen">
        <SheetContent>
            <SheetHeader>
                <SheetTitle>
                    Nuova {{ categoryForm.type === 'income' ? 'entrata' : 'uscita' }} comune
                </SheetTitle>
            </SheetHeader>

            <div class="mt-6 space-y-4">
                <div class="grid gap-2">
                    <Label for="category-name">Nome</Label>
                    <Input
                        id="category-name"
                        v-model="categoryForm.name"
                        :placeholder="categoryForm.type === 'income' ? 'Es. Stipendio' : 'Es. Bollette'"
                        autofocus
                    />
                    <p v-if="categoryForm.errors.name" class="text-sm text-destructive">
                        {{ categoryForm.errors.name }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label>Colore</Label>
                    <div class="grid grid-cols-5 gap-2">
                        <button
                            v-for="color in predefinedColors"
                            :key="color"
                            class="rounded border-2 transition-transform"
                            :class="{
                                'border-foreground scale-110': categoryForm.color === color,
                                'border-transparent': categoryForm.color !== color,
                            }"
                            :style="{ backgroundColor: color }"
                            style="height: 32px"
                            @click="categoryForm.color = color"
                        />
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">Visibile in tutti i mesi.</p>

                <Button class="mt-6 w-full" :disabled="categoryForm.processing" @click="submitCategory">
                    Crea Categoria
                </Button>
            </div>
        </SheetContent>
    </Sheet>

    <!-- Sheet: Aggiungi Sottocategoria -->
    <Sheet v-model:open="isAddSubcategoryOpen">
        <SheetContent>
            <SheetHeader>
                <SheetTitle>
                    Nuova {{ selectedCategory?.type === 'income' ? 'voce' : 'sottocategoria' }} comune
                </SheetTitle>
            </SheetHeader>

            <div class="mt-6 space-y-4">
                <div class="grid gap-2">
                    <Label for="subcategory-name">Nome</Label>
                    <Input
                        id="subcategory-name"
                        v-model="subcategoryForm.name"
                        :placeholder="selectedCategory?.type === 'income' ? 'Es. tredicesima' : 'Es. enel energia'"
                        autofocus
                    />
                    <p v-if="subcategoryForm.errors.name" class="text-sm text-destructive">
                        {{ subcategoryForm.errors.name }}
                    </p>
                </div>

                <p v-if="selectedCategory" class="text-xs text-muted-foreground">
                    In “{{ selectedCategory.name }}”. Visibile in tutti i mesi.
                </p>

                <Button
                    class="mt-6 w-full"
                    :disabled="subcategoryForm.processing"
                    @click="submitSubcategory"
                >
                    Crea
                </Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
