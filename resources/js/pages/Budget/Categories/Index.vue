<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronRight, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import budgetCategories from '@/routes/budget-categories';
import budgetSubcategories from '@/routes/budget-subcategories';
import BudgetColorPicker from '@/components/budget/BudgetColorPicker.vue';
import Heading from '@/components/Heading.vue';
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

const positionLabel = (index: number) => String(index + 1).padStart(2, '0');

const expandedCategories = ref<Set<number>>(new Set());
const isCategorySheetOpen = ref(false);
const isSubcategorySheetOpen = ref(false);
const selectedCategory = ref<Category | null>(null);

// Gli stessi pannelli servono sia a creare sia a modificare: quando queste
// sono valorizzate si sta modificando quella riga.
const editingCategory = ref<Category | null>(null);
const editingSubcategory = ref<Subcategory | null>(null);

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

const toggleCategory = (category: Category) => {
    if (expandedCategories.value.has(category.id)) {
        expandedCategories.value.delete(category.id);
    } else {
        expandedCategories.value.add(category.id);
    }
};

const openAddCategory = (type: Direction) => {
    editingCategory.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryForm.type = type;
    isCategorySheetOpen.value = true;
};

const openEditCategory = (category: Category) => {
    editingCategory.value = category;
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryForm.name = category.name;
    categoryForm.color = category.color;
    categoryForm.type = category.type;
    isCategorySheetOpen.value = true;
};

const openAddSubcategory = (category: Category) => {
    selectedCategory.value = category;
    editingSubcategory.value = null;
    subcategoryForm.reset();
    subcategoryForm.clearErrors();
    isSubcategorySheetOpen.value = true;
};

const openEditSubcategory = (category: Category, subcategory: Subcategory) => {
    selectedCategory.value = category;
    editingSubcategory.value = subcategory;
    subcategoryForm.reset();
    subcategoryForm.clearErrors();
    subcategoryForm.name = subcategory.name;
    isSubcategorySheetOpen.value = true;
};

const closeCategorySheet = () => {
    isCategorySheetOpen.value = false;
    editingCategory.value = null;
    categoryForm.reset();
};

const closeSubcategorySheet = () => {
    isSubcategorySheetOpen.value = false;
    editingSubcategory.value = null;
    subcategoryForm.reset();
};

const submitCategory = () => {
    const options = { preserveScroll: true, onSuccess: closeCategorySheet };

    if (editingCategory.value) {
        categoryForm.put(budgetCategories.update.url(editingCategory.value.id), options);

        return;
    }

    categoryForm.post(budgetCategories.store.url(), options);
};

const submitSubcategory = () => {
    const options = { preserveScroll: true, onSuccess: closeSubcategorySheet };

    if (editingSubcategory.value) {
        subcategoryForm.patch(budgetSubcategories.update.url(editingSubcategory.value.id), options);

        return;
    }

    if (!selectedCategory.value) return;

    subcategoryForm.post(budgetCategories.subcategories.store.url(selectedCategory.value.id), options);
};

const deleteSubcategory = (subcategory: Subcategory) => {
    const confirmed = confirm(
        `Eliminare "${subcategory.name}" da tutti i mesi? `
        + 'Verranno persi anche gli importi attesi e i movimenti collegati.',
    );

    if (!confirmed) return;

    router.delete(budgetSubcategories.destroy.url(subcategory.id), { preserveScroll: true });
};

const deleteCategory = (category: Category) => {
    const confirmed = confirm(
        `Eliminare "${category.name}" e le sue voci da tutti i mesi? `
        + 'Verranno persi anche gli importi attesi e i movimenti collegati.',
    );

    if (!confirmed) return;

    router.delete(budgetCategories.destroy.url(category.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Configurazione Budget" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4 pb-16">
        <Heading title="Configurazione Budget" />

        <section
            v-for="group in groups"
            :key="group.type"
            class="rounded-2xl bg-muted/60 dark:bg-muted/50"
        >
            <div class="flex items-start justify-between gap-4 px-5 pt-5">
                <h2 class="text-xl font-bold">{{ group.title }}</h2>
                <Button
                    variant="ghost"
                    size="sm"
                    class="-mr-2 text-muted-foreground"
                    :title="`Nuova ${group.addLabel.toLowerCase()}`"
                    @click="openAddCategory(group.type)"
                >
                    <Plus class="mr-1 size-4" />
                    Categoria
                </Button>
            </div>

            <p class="mt-3 border-t border-border/60 px-5 pt-3 font-semibold">
                Tutti i mesi
            </p>

            <div v-if="group.categories.length" class="pb-2">
                <div
                    v-for="(category, index) in group.categories"
                    :key="category.id"
                    class="group/row"
                    :style="{
                        backgroundColor: expandedCategories.has(category.id)
                            ? `${category.color}12`
                            : 'transparent',
                    }"
                >
                    <div class="flex items-center gap-1 pr-2">
                        <button
                            class="flex min-w-0 flex-1 items-center gap-3 py-3 pl-5 text-left"
                            @click="toggleCategory(category)"
                        >
                            <span class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
                                <span
                                    class="size-2.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: category.color }"
                                />
                                <span class="truncate font-medium">
                                    <span class="tabular-nums">{{ positionLabel(index) }}.</span>
                                    {{ category.name }}
                                </span>
                            </span>

                            <span class="shrink-0 text-sm text-muted-foreground tabular-nums">
                                {{ category.subcategories.length }} voci
                            </span>

                            <ChevronRight
                                class="size-4 shrink-0 text-muted-foreground transition-transform"
                                :class="{ 'rotate-90': expandedCategories.has(category.id) }"
                            />
                        </button>

                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="shrink-0 text-muted-foreground opacity-60 transition-opacity hover:text-foreground focus-visible:opacity-100 group-hover/row:opacity-100"
                            :title="`Modifica la categoria ${category.name}`"
                            @click="openEditCategory(category)"
                        >
                            <Pencil class="size-4" />
                        </Button>

                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="shrink-0 text-muted-foreground opacity-60 transition-opacity hover:bg-destructive/10 hover:text-destructive focus-visible:opacity-100 group-hover/row:opacity-100"
                            :title="`Elimina la categoria ${category.name}`"
                            @click="deleteCategory(category)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>

                    <div
                        v-if="expandedCategories.has(category.id)"
                        class="border-t border-border/60 pb-3"
                    >
                        <div
                            v-for="sub in category.subcategories"
                            :key="sub.id"
                            class="flex items-center gap-1.5 py-2 pl-4 pr-2 sm:gap-3 sm:px-5"
                        >
                            <span class="min-w-0 flex-1 truncate text-sm sm:pl-4">{{ sub.name }}</span>

                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="w-8 shrink-0 text-muted-foreground hover:text-foreground"
                                :title="`Modifica ${sub.name}`"
                                @click="openEditSubcategory(category, sub)"
                            >
                                <Pencil class="size-4" />
                            </Button>

                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="w-8 shrink-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                :title="`Elimina ${sub.name}`"
                                @click="deleteSubcategory(sub)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>

                        <p
                            v-if="category.subcategories.length === 0"
                            class="px-5 py-3 text-sm text-muted-foreground"
                        >
                            Nessuna voce.
                        </p>

                        <div class="px-2 sm:px-3">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-muted-foreground"
                                @click="openAddSubcategory(category)"
                            >
                                <Plus class="mr-1 size-4" />
                                Aggiungi alla lista
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <p v-else class="px-5 py-6 text-center text-sm text-muted-foreground">
                {{ group.emptyLabel }}
            </p>
        </section>
    </div>

    <!-- Sheet: Categoria (nuova o da modificare) -->
    <Sheet v-model:open="isCategorySheetOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>
                    <template v-if="editingCategory">Modifica “{{ editingCategory.name }}”</template>
                    <template v-else>
                        Nuova {{ categoryForm.type === 'income' ? 'entrata' : 'uscita' }} comune
                    </template>
                </SheetTitle>
            </SheetHeader>

            <div class="space-y-4 px-4 pb-6">
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
                    <BudgetColorPicker v-model="categoryForm.color" />
                </div>

                <p class="text-xs text-muted-foreground">Visibile in tutti i mesi.</p>

                <Button class="mt-6 w-full" :disabled="categoryForm.processing" @click="submitCategory">
                    {{ editingCategory ? 'Salva' : 'Crea Categoria' }}
                </Button>
            </div>
        </SheetContent>
    </Sheet>

    <!-- Sheet: Voce (nuova o da modificare) -->
    <Sheet v-model:open="isSubcategorySheetOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>
                    <template v-if="editingSubcategory">Modifica “{{ editingSubcategory.name }}”</template>
                    <template v-else>
                        Nuova {{ selectedCategory?.type === 'income' ? 'voce' : 'sottocategoria' }} comune
                    </template>
                </SheetTitle>
            </SheetHeader>

            <div class="space-y-4 px-4 pb-6">
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
                    {{ editingSubcategory ? 'Salva' : 'Crea' }}
                </Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
