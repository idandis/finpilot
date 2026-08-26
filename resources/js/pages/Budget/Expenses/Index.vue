<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import budgetExpenses from '@/routes/budget-expenses';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface Subcategory {
    id: number;
    name: string;
}

interface Category {
    id: number;
    name: string;
    color: string;
    subcategories: Subcategory[];
}

const props = defineProps<{
    categories: Category[];
    expenses: Array<{
        id: number;
        budget_subcategory_id: number;
        amount: number;
        description: string;
        recorded_at: string;
    }>;
}>();

const now = new Date();
const currentYear = ref(now.getFullYear());
const currentMonth = ref(now.getMonth() + 1);
const currentDate = ref(now.toISOString().split('T')[0]);
const currentTime = ref(now.toTimeString().slice(0, 5));

const selectedSubcategoryId = ref<number | null>(null);
const amount = ref('');
const description = ref('');

const form = useForm({
    year: currentYear.value,
    month: currentMonth.value,
    budget_subcategory_id: null as number | null,
    amount: '',
    description: '',
    recorded_at: '',
});

const allSubcategories = computed(() => {
    return props.categories.flatMap(cat =>
        cat.subcategories.map(sub => ({
            ...sub,
            categoryId: cat.id,
            categoryName: cat.name,
            categoryColor: cat.color,
        }))
    );
});

const selectedSubcategory = computed(() => {
    if (!selectedSubcategoryId.value) return null;
    return allSubcategories.value.find(sub => sub.id === selectedSubcategoryId.value);
});

const submitExpense = () => {
    if (!selectedSubcategoryId.value || !amount.value) return;

    const recordedDateTime = `${currentDate.value} ${currentTime.value}`;

    form.budget_subcategory_id = selectedSubcategoryId.value;
    form.amount = amount.value;
    form.description = description.value;
    form.recorded_at = recordedDateTime;
    form.year = currentYear.value;
    form.month = currentMonth.value;

    form.post(budgetExpenses.store.url(), {
        onSuccess: () => {
            selectedSubcategoryId.value = null;
            amount.value = '';
            description.value = '';
        },
    });
};

const deleteExpense = (expenseId: number) => {
    if (confirm('Eliminare questa spesa?')) {
        useForm().delete(budgetExpenses.destroy.url(expenseId));
    }
};
</script>

<template>
    <Head title="Registra Spese" />

    <div class="mx-auto max-w-2xl p-4">
        <Heading
            title="Registra Spese"
            description="Registra rapidamente le tue spese mese per mese"
        />

        <!-- Quick Add Form -->
        <div class="mt-6 rounded-lg border bg-card p-4">
            <h3 class="mb-4 font-semibold">Nuova Spesa</h3>

            <div class="space-y-4">
                <!-- Date & Time -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="date">Data</Label>
                        <Input
                            id="date"
                            v-model="currentDate"
                            type="date"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="time">Ora</Label>
                        <Input
                            id="time"
                            v-model="currentTime"
                            type="time"
                        />
                    </div>
                </div>

                <!-- Subcategory Select -->
                <div class="grid gap-2">
                    <Label for="subcategory">Categoria</Label>
                    <Select v-model="selectedSubcategoryId">
                        <SelectTrigger id="subcategory">
                            <SelectValue
                                :placeholder="selectedSubcategory?.name || 'Seleziona una categoria'"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <div v-for="category in categories" :key="category.id">
                                <div
                                    class="px-2 py-1.5 text-xs font-semibold"
                                    :style="{ color: category.color }"
                                >
                                    {{ category.name }}
                                </div>
                                <SelectItem
                                    v-for="sub in category.subcategories"
                                    :key="sub.id"
                                    :value="sub.id"
                                    class="pl-4"
                                >
                                    {{ sub.name }}
                                </SelectItem>
                            </div>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Amount -->
                <div class="grid gap-2">
                    <Label for="amount">Importo</Label>
                    <div class="relative">
                        <Input
                            id="amount"
                            v-model="amount"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="pr-8"
                        />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground">
                            €
                        </span>
                    </div>
                </div>

                <!-- Description -->
                <div class="grid gap-2">
                    <Label for="description">Descrizione (opzionale)</Label>
                    <Input
                        id="description"
                        v-model="description"
                        type="text"
                        placeholder="Es. Spesa al supermercato"
                    />
                </div>

                <!-- Submit Button -->
                <Button
                    @click="submitExpense"
                    :disabled="!selectedSubcategoryId || !amount || form.processing"
                    class="w-full"
                >
                    <Plus class="mr-2 size-4" />
                    Registra Spesa
                </Button>
            </div>
        </div>

        <!-- Recent Expenses -->
        <div v-if="expenses.length" class="mt-8 space-y-3">
            <h3 class="font-semibold">Spese Recenti</h3>
            <div class="space-y-2">
                <div
                    v-for="expense in expenses.slice().reverse()"
                    :key="expense.id"
                    class="flex items-center justify-between rounded-lg border bg-card p-3"
                >
                    <div class="flex-1">
                        <p class="font-medium">
                            {{ allSubcategories.find(s => s.id === expense.budget_subcategory_id)?.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ new Date(expense.recorded_at).toLocaleString('it-IT') }}
                        </p>
                        <p v-if="expense.description" class="text-xs text-muted-foreground">
                            {{ expense.description }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-semibold">{{ expense.amount.toFixed(2) }}€</span>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            @click="deleteExpense(expense.id)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="mt-8 rounded-lg border border-dashed bg-muted/30 p-8 text-center">
            <p class="text-sm text-muted-foreground">
                Nessuna spesa registrata ancora. Aggiungi la prima!
            </p>
        </div>
    </div>
</template>
