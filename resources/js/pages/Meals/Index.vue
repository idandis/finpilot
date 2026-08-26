<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    FileDown,
    Pencil,
    Plus,
    ShoppingCart,
    Trash2,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import DishController from '@/actions/App/Http/Controllers/DishController';
import MealController from '@/actions/App/Http/Controllers/MealController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import * as dishRoutes from '@/routes/dishes';
import * as mealRoutes from '@/routes/meals';
import type {
    Dish,
    DishCategories,
    GroceryCategories,
    Meal,
    MealType,
} from '@/types';

const props = defineProps<{
    weekStart: string;
    today: string;
    meals: Meal[];
    dishes: Dish[];
    dishCategories: DishCategories;
    groceryCategories: GroceryCategories;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pasti', href: mealRoutes.index() }],
    },
});

// Calendar-day arithmetic done at UTC midnight so it never drifts a day off
// because of the browser's local timezone/DST.
function shiftDate(date: string, days: number): string {
    const shifted = new Date(`${date}T00:00:00Z`);
    shifted.setUTCDate(shifted.getUTCDate() + days);

    return shifted.toISOString().slice(0, 10);
}

function mondayOf(date: string): string {
    const d = new Date(`${date}T00:00:00Z`);
    const isoWeekday = d.getUTCDay() === 0 ? 7 : d.getUTCDay();
    d.setUTCDate(d.getUTCDate() - (isoWeekday - 1));

    return d.toISOString().slice(0, 10);
}

const previousWeekStart = computed(() => shiftDate(props.weekStart, -7));
const nextWeekStart = computed(() => shiftDate(props.weekStart, 7));
const isCurrentWeek = computed(() => props.weekStart === mondayOf(props.today));

function goToWeek(date: string) {
    router.get(
        mealRoutes.index.url({ query: { date } }),
        {},
        { preserveScroll: true },
    );
}

const MEAL_TYPES: { type: MealType; label: string }[] = [
    { type: 'lunch', label: 'Pranzo' },
    { type: 'dinner', label: 'Cena' },
];

const DAY_LABEL_FORMATTER = new Intl.DateTimeFormat('it-IT', {
    weekday: 'long',
    day: 'numeric',
    month: 'short',
});
const WEEK_RANGE_FORMATTER = new Intl.DateTimeFormat('it-IT', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const days = computed(() =>
    Array.from({ length: 7 }, (_, i) => {
        const date = shiftDate(props.weekStart, i);

        return {
            date,
            label: DAY_LABEL_FORMATTER.format(new Date(`${date}T00:00:00`)),
            isToday: date === props.today,
            isWeekend: i === 5 || i === 6,
        };
    }),
);

const formattedWeekRange = computed(() => {
    const start = WEEK_RANGE_FORMATTER.format(
        new Date(`${props.weekStart}T00:00:00`),
    );
    const end = WEEK_RANGE_FORMATTER.format(
        new Date(`${shiftDate(props.weekStart, 6)}T00:00:00`),
    );

    return `${start} - ${end}`;
});

// A local, mutable copy so drag-and-drop can move a meal between days/slots
// instantly (before the server confirms) - resynced whenever fresh props
// arrive (e.g. after the move request completes, or if it was rejected).
const localMeals = reactive<Meal[]>([...props.meals]);

watch(
    () => props.meals,
    (meals) => localMeals.splice(0, localMeals.length, ...meals),
);

function mealsFor(date: string, type: MealType) {
    return localMeals
        .filter((meal) => meal.meal_date === date && meal.meal_type === type)
        .sort((a, b) => a.position - b.position);
}

function slotKey(date: string, type: MealType) {
    return `${date}::${type}`;
}

const draggingMealId = ref<number | null>(null);
const draggingDishId = ref<number | null>(null);
const dragOverKey = ref<string | null>(null);

function onDragStart(meal: Meal, event: DragEvent) {
    draggingMealId.value = meal.id;
    event.dataTransfer?.setData('text/plain', String(meal.id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragEnd() {
    draggingMealId.value = null;
    dragOverKey.value = null;
}

function onDishDragStart(dish: Dish, event: DragEvent) {
    draggingDishId.value = dish.id;
    event.dataTransfer?.setData('text/plain', `dish:${dish.id}`);

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
    }
}

function onDishDragEnd() {
    draggingDishId.value = null;
    dragOverKey.value = null;
}

function onDragOver(date: string, type: MealType, event: DragEvent) {
    event.preventDefault();
    dragOverKey.value = slotKey(date, type);
}

function onDrop(date: string, type: MealType, event: DragEvent) {
    event.preventDefault();
    dragOverKey.value = null;

    // Dropping a preconfigured dish creates a new meal from it - the dish
    // itself stays in the library below so it can be reused on other days.
    if (draggingDishId.value !== null) {
        const dish = props.dishes.find(
            (candidate) => candidate.id === draggingDishId.value,
        );
        draggingDishId.value = null;

        if (!dish) {
            return;
        }

        router.post(
            mealRoutes.store.url(),
            {
                title: dish.name,
                description: dish.description ?? '',
                meal_date: date,
                meal_type: type,
                category: dish.category,
                dish_id: dish.id,
            },
            { preserveScroll: true, preserveState: true },
        );

        return;
    }

    const mealId = draggingMealId.value;
    draggingMealId.value = null;

    if (mealId === null) {
        return;
    }

    const meal = localMeals.find((candidate) => candidate.id === mealId);

    if (!meal || (meal.meal_date === date && meal.meal_type === type)) {
        return;
    }

    meal.meal_date = date;
    meal.meal_type = type;

    router.patch(
        mealRoutes.move(mealId).url,
        { meal_date: date, meal_type: type },
        { preserveScroll: true, preserveState: true },
    );
}

function destroyMeal(meal: Meal) {
    if (confirm(`Eliminare "${meal.title}"?`)) {
        router.delete(mealRoutes.destroy(meal.id).url, {
            preserveScroll: true,
        });
    }
}

const groupedDishes = computed(() =>
    Object.entries(props.dishCategories)
        .map(([key, label]) => ({
            key,
            label,
            dishes: props.dishes.filter((dish) => dish.category === key),
        }))
        .filter((group) => group.dishes.length > 0),
);

// How many times each category appears among this week's meals - a quick
// "quanta carne/pesce ecc. mangio questa settimana" glance above the board.
const weeklyCategoryCounts = computed(() => {
    const counts = new Map<string, number>();

    for (const meal of localMeals) {
        if (meal.category) {
            counts.set(meal.category, (counts.get(meal.category) ?? 0) + 1);
        }
    }

    return Object.entries(props.dishCategories)
        .map(([key, label]) => ({ key, label, count: counts.get(key) ?? 0 }))
        .filter((entry) => entry.count > 0);
});

function destroyDish(dish: Dish) {
    if (confirm(`Eliminare il piatto "${dish.name}" dall'elenco?`)) {
        router.delete(dishRoutes.destroy(dish.id).url, {
            preserveScroll: true,
        });
    }
}

function categoryLabel(category: string | null) {
    return category ? (props.dishCategories[category] ?? category) : null;
}

// null = closed, 'new' = create dialog, a Dish = edit dialog for that dish.
const dishDialogTarget = ref<Dish | 'new' | null>(null);
const isDishDialogOpen = computed(() => dishDialogTarget.value !== null);
const editingDish = computed(() =>
    dishDialogTarget.value !== null && dishDialogTarget.value !== 'new'
        ? dishDialogTarget.value
        : null,
);

// Uncontrolled rows (no v-model): each renders a real named input/select
// read directly from the DOM by the Form component's FormData collection -
// this array only drives how many rows exist, their positional index, and
// (when editing) their initial value.
let nextIngredientKey = 0;
const ingredientRows = ref<{ key: number; name: string; category: string }[]>(
    [],
);

function addIngredientRow(name = '', category = '') {
    ingredientRows.value.push({ key: nextIngredientKey++, name, category });
}

function removeIngredientRow(key: number) {
    ingredientRows.value = ingredientRows.value.filter(
        (row) => row.key !== key,
    );
}

const dishDescriptionForForm = ref('');

function openCreateDishDialog() {
    dishDialogTarget.value = 'new';
    ingredientRows.value = [];
    dishDescriptionForForm.value = '';
}

function openEditDishDialog(dish: Dish) {
    dishDialogTarget.value = dish;
    ingredientRows.value = dish.ingredients.map((ingredient) => ({
        key: nextIngredientKey++,
        name: ingredient.name,
        category: ingredient.category,
    }));
    dishDescriptionForForm.value = dish.description ?? '';
}

function closeDishDialog() {
    dishDialogTarget.value = null;
    ingredientRows.value = [];
}

function generateShoppingList() {
    router.post(
        mealRoutes.generateShoppingList.url({
            query: { date: props.weekStart },
        }),
        {},
        { preserveScroll: true },
    );
}

const isAddMealOpen = ref(false);
const addMealTarget = ref<{
    date: string;
    label: string;
    type: MealType;
    typeLabel: string;
} | null>(null);

function openAddDialog(
    date: string,
    day: { label: string },
    type: MealType,
    typeLabel: string,
) {
    addMealTarget.value = { date, label: day.label, type, typeLabel };
    isAddMealOpen.value = true;
}
</script>

<template>
    <Head title="Pasti" />

    <div class="flex flex-col space-y-8 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div
                    class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <Button
                        variant="outline"
                        size="icon-sm"
                        title="Settimana precedente"
                        @click="goToWeek(previousWeekStart)"
                    >
                        <ChevronLeft />
                    </Button>
                    <span class="capitalize">{{ formattedWeekRange }}</span>
                    <Button
                        variant="outline"
                        size="icon-sm"
                        title="Settimana successiva"
                        @click="goToWeek(nextWeekStart)"
                    >
                        <ChevronRight />
                    </Button>
                    <Button
                        v-if="!isCurrentWeek"
                        variant="ghost"
                        size="sm"
                        @click="goToWeek(today)"
                        >Torna a questa settimana</Button
                    >
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Button
                    variant="outline"
                    title="Genera una lista della spesa dai pasti di questa settimana"
                    @click="generateShoppingList"
                >
                    <ShoppingCart />
                    Genera lista della spesa
                </Button>
                <Button variant="outline" as-child>
                    <a
                        :href="
                            mealRoutes.pdf.url({ query: { date: weekStart } })
                        "
                    >
                        <FileDown />
                        Crea PDF
                    </a>
                </Button>
            </div>
        </div>

        <div
            v-if="weeklyCategoryCounts.length > 0"
            class="flex flex-wrap items-center gap-2"
        >
            <Badge
                v-for="entry in weeklyCategoryCounts"
                :key="entry.key"
                variant="secondary"
                class="gap-1.5"
            >
                {{ entry.label }}
                <span class="font-semibold">{{ entry.count }}</span>
            </Badge>
        </div>

        <div
            class="-mx-4 snap-x snap-mandatory overflow-x-auto pb-2 sm:mx-0 sm:snap-none"
        >
            <div
                class="flex sm:grid sm:auto-cols-[17rem] sm:grid-flow-col sm:gap-4"
            >
                <div
                    v-for="day in days"
                    :key="day.date"
                    class="w-screen shrink-0 snap-center px-4 sm:w-auto sm:shrink sm:snap-align-none sm:px-0"
                >
                    <div
                        class="flex h-full flex-col gap-4 rounded-xl p-4 sm:p-5"
                        :class="
                            day.isToday || day.isWeekend
                                ? 'bg-primary/10'
                                : 'bg-muted/40'
                        "
                    >
                        <h3
                            class="px-1 text-base font-medium capitalize sm:text-sm"
                        >
                            {{ day.label }}
                        </h3>

                        <template
                            v-for="(slot, index) in MEAL_TYPES"
                            :key="slot.type"
                        >
                            <div
                                v-if="index > 0"
                                class="border-t border-border/60"
                            />

                            <div
                                class="flex min-h-[7rem] flex-col gap-2 rounded-lg p-2 transition-colors sm:p-3"
                                :class="
                                    dragOverKey === slotKey(day.date, slot.type)
                                        ? 'bg-muted/70 ring-2 ring-primary/40'
                                        : ''
                                "
                                @dragover="
                                    onDragOver(day.date, slot.type, $event)
                                "
                                @dragleave="
                                    dragOverKey =
                                        dragOverKey ===
                                        slotKey(day.date, slot.type)
                                            ? null
                                            : dragOverKey
                                "
                                @drop="onDrop(day.date, slot.type, $event)"
                            >
                                <div
                                    class="flex items-center justify-between px-1"
                                >
                                    <h4
                                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        {{ slot.label }}
                                    </h4>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="size-6"
                                        title="Aggiungi pasto"
                                        @click="
                                            openAddDialog(
                                                day.date,
                                                day,
                                                slot.type,
                                                slot.label,
                                            )
                                        "
                                    >
                                        <Plus class="size-3.5" />
                                    </Button>
                                </div>

                                <div
                                    v-for="meal in mealsFor(
                                        day.date,
                                        slot.type,
                                    )"
                                    :key="meal.id"
                                    draggable="true"
                                    class="group flex items-start gap-2 rounded-lg px-2 py-2 text-sm select-none hover:bg-background/60"
                                    :class="
                                        draggingMealId === meal.id
                                            ? 'opacity-40'
                                            : ''
                                    "
                                    @dragstart="onDragStart(meal, $event)"
                                    @dragend="onDragEnd"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="flex items-center gap-1.5 truncate font-medium"
                                        >
                                            <span
                                                class="size-2 shrink-0 rounded-full bg-primary/70"
                                            />
                                            <span class="truncate">{{
                                                meal.title
                                            }}</span>
                                        </p>
                                        <p
                                            v-if="meal.category"
                                            class="mt-0.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            {{ categoryLabel(meal.category) }}
                                        </p>
                                        <p
                                            v-if="meal.description"
                                            class="mt-0.5 line-clamp-2 text-xs whitespace-pre-line text-muted-foreground"
                                        >
                                            {{ meal.description }}
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="size-6 shrink-0 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive"
                                        title="Elimina pasto"
                                        @click="destroyMeal(meal)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>

                                <p
                                    v-if="
                                        mealsFor(day.date, slot.type).length ===
                                        0
                                    "
                                    class="px-1 text-xs text-muted-foreground"
                                >
                                    Nessun pasto
                                </p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-0.5">
                    <h3 class="text-lg font-semibold tracking-tight">
                        Piatti preconfigurati
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        Trascina un piatto su un giorno per aggiungerlo: resta
                        qui per essere riutilizzato.
                    </p>
                </div>
                <Button
                    size="sm"
                    class="shrink-0"
                    @click="openCreateDishDialog"
                >
                    <Plus />
                    Nuovo piatto
                </Button>
            </div>

            <div
                v-if="groupedDishes.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Nessun piatto ancora. Aggiungine uno per iniziare a comporre la
                settimana.
            </div>

            <div v-else class="-mx-4 overflow-x-auto pb-2 sm:mx-0">
                <div
                    class="flex gap-4 px-4 sm:grid sm:auto-cols-[17rem] sm:grid-flow-col sm:px-0"
                >
                    <div
                        v-for="group in groupedDishes"
                        :key="group.key"
                        class="w-[17rem] shrink-0 rounded-xl bg-muted/40 p-4 sm:w-auto"
                    >
                        <h4
                            class="mb-2 px-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ group.label }}
                        </h4>
                        <div class="space-y-0.5">
                            <div
                                v-for="dish in group.dishes"
                                :key="dish.id"
                                draggable="true"
                                class="group flex cursor-grab items-start gap-2 rounded-lg px-2 py-2 text-sm select-none hover:bg-background/60"
                                :class="
                                    draggingDishId === dish.id
                                        ? 'opacity-40'
                                        : ''
                                "
                                @dragstart="onDishDragStart(dish, $event)"
                                @dragend="onDishDragEnd"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="flex items-center gap-1.5 truncate font-medium"
                                    >
                                        <span
                                            class="size-2 shrink-0 rounded-full bg-primary/70"
                                        />
                                        <span class="truncate">{{
                                            dish.name
                                        }}</span>
                                    </p>
                                    <p
                                        v-if="dish.description"
                                        class="mt-0.5 line-clamp-2 text-xs text-muted-foreground"
                                    >
                                        {{ dish.description }}
                                    </p>
                                </div>
                                <div
                                    class="flex shrink-0 gap-0.5 opacity-0 group-hover:opacity-100"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="size-6 text-muted-foreground hover:bg-muted"
                                        title="Modifica piatto"
                                        @click="openEditDishDialog(dish)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="size-6 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        title="Elimina piatto"
                                        @click="destroyDish(dish)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Sheet
            :open="isDishDialogOpen"
            @update:open="
                (open) => {
                    if (!open) closeDishDialog();
                }
            "
        >
            <SheetContent class="w-full gap-0 overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{{
                        editingDish ? 'Modifica piatto' : 'Nuovo piatto'
                    }}</SheetTitle>
                </SheetHeader>
                <Form
                    :key="editingDish ? `dish-${editingDish.id}` : 'dish-new'"
                    v-bind="
                        editingDish
                            ? DishController.update.form(editingDish.id)
                            : DishController.store.form()
                    "
                    :reset-on-success="!editingDish"
                    class="grid grid-cols-1 gap-4 px-4 pb-4"
                    v-slot="{ errors, processing }"
                    @success="closeDishDialog"
                >
                    <div class="grid gap-2">
                        <Label for="dish-name">Nome</Label>
                        <Input
                            id="dish-name"
                            name="name"
                            placeholder="Es. Pizza"
                            required
                            autofocus
                            :default-value="editingDish?.name"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dish-description"
                            >Descrizione (opzionale)</Label
                        >
                        <textarea
                            id="dish-description"
                            v-model="dishDescriptionForForm"
                            name="description"
                            rows="2"
                            placeholder="Dettagli aggiuntivi..."
                            class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                        ></textarea>
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dish-category">Categoria</Label>
                        <select
                            id="dish-category"
                            name="category"
                            required
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option
                                v-for="(label, key) in dishCategories"
                                :key="key"
                                :value="key"
                                :selected="
                                    editingDish
                                        ? editingDish.category === key
                                        : undefined
                                "
                            >
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="errors.category" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label>Ingredienti (opzionale)</Label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="addIngredientRow()"
                            >
                                <Plus class="size-3.5" />
                                Aggiungi ingrediente
                            </Button>
                        </div>
                        <p
                            v-if="ingredientRows.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            Aggiungi gli ingredienti per generare in automatico
                            la lista della spesa da questo piatto.
                        </p>
                        <div
                            v-for="(row, index) in ingredientRows"
                            :key="row.key"
                            class="flex items-center gap-2"
                        >
                            <Input
                                :name="`ingredients[${index}][name]`"
                                placeholder="Es. Pomodoro"
                                class="flex-1"
                                :default-value="row.name"
                            />
                            <select
                                :name="`ingredients[${index}][category]`"
                                class="h-9 w-36 shrink-0 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option
                                    v-for="(label, key) in groceryCategories"
                                    :key="key"
                                    :value="key"
                                    :selected="row.category === key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="shrink-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                title="Rimuovi ingrediente"
                                @click="removeIngredientRow(row.key)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>

                    <Button type="submit" :disabled="processing">{{
                        editingDish ? 'Salva modifiche' : 'Aggiungi piatto'
                    }}</Button>
                </Form>
            </SheetContent>
        </Sheet>

        <Dialog v-model:open="isAddMealOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle v-if="addMealTarget" class="capitalize"
                        >{{ addMealTarget.label }} ·
                        {{ addMealTarget.typeLabel }}</DialogTitle
                    >
                </DialogHeader>
                <Form
                    v-if="addMealTarget"
                    v-bind="MealController.store.form()"
                    reset-on-success
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="isAddMealOpen = false"
                >
                    <input
                        type="hidden"
                        name="meal_date"
                        :value="addMealTarget.date"
                    />
                    <input
                        type="hidden"
                        name="meal_type"
                        :value="addMealTarget.type"
                    />
                    <div class="grid gap-2">
                        <Label for="title">Titolo</Label>
                        <Input
                            id="title"
                            name="title"
                            placeholder="Es. Pasta al pomodoro"
                            required
                            autofocus
                        />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Descrizione (opzionale)</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            placeholder="Dettagli aggiuntivi..."
                            class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                        ></textarea>
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="meal-category">Categoria (opzionale)</Label>
                        <select
                            id="meal-category"
                            name="category"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Nessuna categoria</option>
                            <option
                                v-for="(label, key) in dishCategories"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="errors.category" />
                    </div>
                    <Button type="submit" :disabled="processing"
                        >Aggiungi pasto</Button
                    >
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
