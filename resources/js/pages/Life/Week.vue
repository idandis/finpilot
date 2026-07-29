<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    MapPin,
    Pencil,
    Plus,
    Sparkles,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import MemoryController from '@/actions/App/Http/Controllers/MemoryController';
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
import * as lifeRoutes from '@/routes/life';
import * as memoryRoutes from '@/routes/memories';
import type {
    LifeDay,
    LifeFinanceSummary,
    LifeHealthSummary,
    LifeOrganizationSummary,
    Memory,
    Moods,
} from '@/types';

const props = defineProps<{
    year: number;
    week: number;
    start: string;
    end: string;
    weeksInYear: number;
    previous: { year: number; week: number };
    next: { year: number; week: number };
    finance: LifeFinanceSummary;
    health: LifeHealthSummary;
    organization: LifeOrganizationSummary;
    days: LifeDay[];
    moods: Moods;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Vita', href: lifeRoutes.index() },
            { title: 'Dettaglio settimana', href: lifeRoutes.index() },
        ],
    },
});

const WEEK_RANGE_FORMATTER = new Intl.DateTimeFormat('it-IT', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const formattedRange = computed(() => {
    const start = WEEK_RANGE_FORMATTER.format(new Date(`${props.start}T00:00:00`));
    const end = WEEK_RANGE_FORMATTER.format(new Date(`${props.end}T00:00:00`));

    return `${start} - ${end}`;
});

const boardHref = computed(() => lifeRoutes.index.url({ query: { anno: props.year } }));

function formatCurrency(value: number | null) {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

const DAY_LABEL_FORMATTER = new Intl.DateTimeFormat('it-IT', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

function dayLabel(date: string) {
    return DAY_LABEL_FORMATTER.format(new Date(`${date}T00:00:00`));
}

function moodLabel(mood: string | null) {
    return mood ? (props.moods[mood] ?? mood) : null;
}

function destroyMemory(memory: Memory) {
    if (confirm(`Eliminare il ricordo "${memory.title}"?`)) {
        router.delete(memoryRoutes.destroy(memory.id).url, {
            preserveScroll: true,
        });
    }
}

// null = closed; otherwise the day being added to, plus the Memory being
// edited (null when creating a new one for that day).
const memoryDialogTarget = ref<{ date: string; memory: Memory | null } | null>(
    null,
);
const isMemoryDialogOpen = computed(() => memoryDialogTarget.value !== null);
const editingMemory = computed(() => memoryDialogTarget.value?.memory ?? null);

function openCreateMemoryDialog(date: string) {
    memoryDialogTarget.value = { date, memory: null };
}

function openEditMemoryDialog(date: string, memory: Memory) {
    memoryDialogTarget.value = { date, memory };
}

function closeMemoryDialog() {
    memoryDialogTarget.value = null;
}
</script>

<template>
    <Head title="Vita" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <Button variant="ghost" size="sm" as-child class="-ml-2">
                    <Link :href="boardHref">
                        <ChevronLeft />
                        Torna all'anno
                    </Link>
                </Button>
                <h2 class="px-2 text-xl font-semibold capitalize">
                    Settimana {{ week }} · {{ formattedRange }}
                </h2>
            </div>
            <div class="flex shrink-0 gap-2">
                <Button variant="outline" size="icon-sm" as-child title="Settimana precedente">
                    <Link :href="lifeRoutes.week([previous.year, previous.week])">
                        <ChevronLeft />
                    </Link>
                </Button>
                <Button variant="outline" size="icon-sm" as-child title="Settimana successiva">
                    <Link :href="lifeRoutes.week([next.year, next.week])">
                        <ChevronRight />
                    </Link>
                </Button>
            </div>
        </div>

        <div class="space-y-3">
            <h3 class="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                Ricordi
            </h3>
            <div class="relative">
                <div
                    class="absolute top-0 bottom-0 left-1/2 hidden w-px -translate-x-1/2 bg-border sm:block"
                />
                <div class="flex flex-col gap-6">
                    <div
                        v-for="(day, index) in days"
                        :key="day.date"
                        class="relative grid grid-cols-1 gap-3 sm:grid-cols-[1fr_2rem_1fr] sm:items-start"
                    >
                        <div
                            class="hidden size-3 shrink-0 self-start justify-self-center rounded-full bg-primary ring-4 ring-background sm:col-start-2 sm:mt-4 sm:block"
                        />
                        <div
                            class="rounded-xl bg-muted/40 p-4"
                            :class="index % 2 === 0 ? 'sm:col-start-1' : 'sm:col-start-3 sm:row-start-1'"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <h4 class="text-sm font-medium capitalize">
                                    {{ dayLabel(day.date) }}
                                </h4>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="size-6"
                                    title="Aggiungi ricordo"
                                    @click="openCreateMemoryDialog(day.date)"
                                >
                                    <Plus class="size-3.5" />
                                </Button>
                            </div>

                            <div
                                v-if="day.events.length > 0"
                                class="mt-2 flex flex-wrap gap-1.5"
                            >
                                <Badge
                                    v-for="(event, eventIndex) in day.events"
                                    :key="eventIndex"
                                    variant="outline"
                                    class="gap-1"
                                >
                                    <Sparkles class="size-3" />
                                    {{ event }}
                                </Badge>
                            </div>

                            <p
                                v-if="day.memories.length === 0 && day.events.length === 0"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                Nessun ricordo
                            </p>

                            <div v-else class="mt-2 space-y-2">
                                <div
                                    v-for="memory in day.memories"
                                    :key="memory.id"
                                    class="group flex gap-3 rounded-lg bg-background/60 p-3"
                                >
                                    <img
                                        v-if="memory.photo_url"
                                        :src="memory.photo_url"
                                        :alt="memory.title"
                                        class="size-14 shrink-0 rounded-md object-cover"
                                    />
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="truncate text-sm font-medium">
                                                {{ memory.title }}
                                            </p>
                                            <div
                                                class="flex shrink-0 gap-0.5 opacity-0 group-hover:opacity-100"
                                            >
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="size-6 text-muted-foreground hover:bg-muted"
                                                    title="Modifica ricordo"
                                                    @click="openEditMemoryDialog(day.date, memory)"
                                                >
                                                    <Pencil class="size-3.5" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="size-6 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                    title="Elimina ricordo"
                                                    @click="destroyMemory(memory)"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </Button>
                                            </div>
                                        </div>
                                        <p
                                            v-if="memory.description"
                                            class="line-clamp-2 text-xs whitespace-pre-line text-muted-foreground"
                                        >
                                            {{ memory.description }}
                                        </p>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Badge v-if="memory.mood" variant="secondary">
                                                {{ moodLabel(memory.mood) }}
                                            </Badge>
                                            <span
                                                v-if="memory.location"
                                                class="flex items-center gap-1 text-xs text-muted-foreground"
                                            >
                                                <MapPin class="size-3" />{{ memory.location }}
                                            </span>
                                            <span
                                                v-if="memory.people"
                                                class="flex items-center gap-1 text-xs text-muted-foreground"
                                            >
                                                <Users class="size-3" />{{ memory.people }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <h3 class="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                Finanza
            </h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Entrate</p>
                    <p class="text-lg font-semibold text-green-600">
                        {{ formatCurrency(finance.income) }}
                    </p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Uscite</p>
                    <p class="text-lg font-semibold text-red-600">
                        {{ formatCurrency(finance.expenses) }}
                    </p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Risparmio</p>
                    <p
                        class="text-lg font-semibold"
                        :class="finance.savings < 0 ? 'text-red-600' : 'text-green-600'"
                    >
                        {{ formatCurrency(finance.savings) }}
                    </p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Investito</p>
                    <p class="text-lg font-semibold">
                        {{ formatCurrency(finance.invested) }}
                    </p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Patrimonio</p>
                    <p class="text-lg font-semibold">
                        {{ formatCurrency(finance.net_worth) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <h3 class="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                Salute
            </h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Allenamenti completati</p>
                    <p class="text-lg font-semibold">
                        {{ health.workouts_completed }} / {{ health.workouts_total }}
                    </p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Pasti pianificati</p>
                    <p class="text-lg font-semibold">{{ health.meals_planned }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <h3 class="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                Organizzazione
            </h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Task completati</p>
                    <p class="text-lg font-semibold">
                        {{ organization.tasks_completed }} / {{ organization.tasks_total }}
                    </p>
                </div>
            </div>
        </div>

        <Dialog
            :open="isMemoryDialogOpen"
            @update:open="
                (open) => {
                    if (!open) closeMemoryDialog();
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle v-if="memoryDialogTarget" class="capitalize">{{
                        editingMemory ? 'Modifica ricordo' : dayLabel(memoryDialogTarget.date)
                    }}</DialogTitle>
                </DialogHeader>
                <Form
                    v-if="memoryDialogTarget"
                    :key="editingMemory ? `memory-${editingMemory.id}` : 'memory-new'"
                    v-bind="
                        editingMemory
                            ? MemoryController.update.form(editingMemory.id)
                            : MemoryController.store.form()
                    "
                    :reset-on-success="!editingMemory"
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="closeMemoryDialog"
                >
                    <input
                        type="hidden"
                        name="memory_date"
                        :value="memoryDialogTarget.date"
                    />
                    <div class="grid gap-2">
                        <Label for="memory-title">Titolo</Label>
                        <Input
                            id="memory-title"
                            name="title"
                            placeholder="Es. Weekend in montagna"
                            required
                            autofocus
                            :default-value="editingMemory?.title"
                        />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="memory-description">Descrizione (opzionale)</Label>
                        <textarea
                            id="memory-description"
                            name="description"
                            rows="3"
                            placeholder="Dettagli aggiuntivi..."
                            class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                            :value="editingMemory?.description ?? ''"
                        ></textarea>
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="memory-location">Luogo (opzionale)</Label>
                            <Input
                                id="memory-location"
                                name="location"
                                placeholder="Es. Dolomiti"
                                :default-value="editingMemory?.location ?? undefined"
                            />
                            <InputError :message="errors.location" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="memory-people">Persone (opzionale)</Label>
                            <Input
                                id="memory-people"
                                name="people"
                                placeholder="Es. Marco, Giulia"
                                :default-value="editingMemory?.people ?? undefined"
                            />
                            <InputError :message="errors.people" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="memory-mood">Mood (opzionale)</Label>
                        <select
                            id="memory-mood"
                            name="mood"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Nessun mood</option>
                            <option
                                v-for="(label, key) in moods"
                                :key="key"
                                :value="key"
                                :selected="
                                    editingMemory ? editingMemory.mood === key : undefined
                                "
                            >
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="errors.mood" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="memory-photo">Foto (opzionale)</Label>
                        <img
                            v-if="editingMemory?.photo_url"
                            :src="editingMemory.photo_url"
                            :alt="editingMemory.title"
                            class="size-16 rounded-md object-cover"
                        />
                        <input
                            id="memory-photo"
                            type="file"
                            name="photo"
                            accept="image/*"
                            class="text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium"
                        />
                        <p v-if="editingMemory?.photo_url" class="text-xs text-muted-foreground">
                            Carica una foto solo se vuoi sostituire quella attuale.
                        </p>
                        <InputError :message="errors.photo" />
                    </div>
                    <Button type="submit" :disabled="processing">{{
                        editingMemory ? 'Salva modifiche' : 'Aggiungi ricordo'
                    }}</Button>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
