<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, GripVertical, Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as taskRoutes from '@/routes/tasks';
import type { Task, TaskStatus } from '@/types';

const props = defineProps<{
    date: string;
    today: string;
    tasks: Task[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Task', href: taskRoutes.index() }],
    },
});

const formattedDate = computed(() =>
    new Intl.DateTimeFormat('it-IT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${props.date}T00:00:00`)),
);

const isToday = computed(() => props.date === props.today);

// Calendar-day arithmetic done at UTC midnight so it never drifts a day off
// because of the browser's local timezone/DST.
function shiftDate(date: string, days: number): string {
    const shifted = new Date(`${date}T00:00:00Z`);
    shifted.setUTCDate(shifted.getUTCDate() + days);

    return shifted.toISOString().slice(0, 10);
}

const previousDate = computed(() => shiftDate(props.date, -1));
const nextDate = computed(() => shiftDate(props.date, 1));

function goToDate(date: string) {
    router.get(taskRoutes.index.url({ query: { date } }), {}, { preserveScroll: true });
}

// A local, mutable copy so drag-and-drop can move a card between columns
// instantly (before the server confirms) - resynced whenever fresh props
// arrive (e.g. after the move request completes, or if it was rejected).
const localTasks = reactive<Task[]>([...props.tasks]);

watch(
    () => props.tasks,
    (tasks) => localTasks.splice(0, localTasks.length, ...tasks),
);

const COLUMNS: { status: TaskStatus; label: string }[] = [
    { status: 'todo', label: 'Da fare' },
    { status: 'in_progress', label: 'In corso' },
    { status: 'done', label: 'Fatto' },
];

const columns = computed(() =>
    COLUMNS.map((column) => ({
        ...column,
        tasks: localTasks.filter((task) => task.status === column.status).sort((a, b) => a.position - b.position),
    })),
);

const draggingTaskId = ref<number | null>(null);
const dragOverStatus = ref<TaskStatus | null>(null);

function onDragStart(task: Task, event: DragEvent) {
    draggingTaskId.value = task.id;
    event.dataTransfer?.setData('text/plain', String(task.id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragEnd() {
    draggingTaskId.value = null;
    dragOverStatus.value = null;
}

function onDragOver(status: TaskStatus, event: DragEvent) {
    event.preventDefault();
    dragOverStatus.value = status;
}

function onDrop(status: TaskStatus, event: DragEvent) {
    event.preventDefault();
    const taskId = draggingTaskId.value;
    dragOverStatus.value = null;
    draggingTaskId.value = null;

    if (taskId === null || !isToday.value) {
        return;
    }

    const task = localTasks.find((candidate) => candidate.id === taskId);

    if (!task || task.status === status) {
        return;
    }

    task.status = status;

    router.patch(taskRoutes.move(taskId).url, { status }, { preserveScroll: true, preserveState: true });
}

function destroyTask(task: Task) {
    if (isToday.value && confirm(`Eliminare il task "${task.title}"?`)) {
        router.delete(taskRoutes.destroy(task.id).url, { preserveScroll: true });
    }
}

const isAddTaskOpen = ref(false);
</script>

<template>
    <Head title="Task" />

    <div class="flex flex-col space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h2 class="text-xl font-semibold tracking-tight">Task</h2>
                <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <Button variant="outline" size="icon-sm" title="Giorno precedente" @click="goToDate(previousDate)">
                        <ChevronLeft />
                    </Button>
                    <span class="capitalize">{{ formattedDate }}</span>
                    <Button variant="outline" size="icon-sm" title="Giorno successivo" :disabled="isToday" @click="goToDate(nextDate)">
                        <ChevronRight />
                    </Button>
                    <Button v-if="!isToday" variant="ghost" size="sm" @click="goToDate(today)">Torna a oggi</Button>
                </div>
            </div>
            <Button v-if="isToday" class="shrink-0" @click="isAddTaskOpen = true">
                <Plus />
                Nuovo task
            </Button>
            <Badge v-else variant="secondary" class="shrink-0">Sola lettura</Badge>
        </div>

        <Dialog v-model:open="isAddTaskOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nuovo task</DialogTitle>
                </DialogHeader>
                <Form
                    v-bind="TaskController.store.form()"
                    reset-on-success
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="isAddTaskOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="title">Titolo</Label>
                        <Input id="title" name="title" placeholder="Es. Rispondere alle email" required autofocus />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Descrizione (opzionale)</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            placeholder="Dettagli aggiuntivi..."
                            class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive w-full min-w-0 resize-y rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm"
                        ></textarea>
                        <InputError :message="errors.description" />
                    </div>
                    <Button type="submit" :disabled="processing">Aggiungi task</Button>
                </Form>
            </DialogContent>
        </Dialog>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div
                v-for="column in columns"
                :key="column.status"
                class="flex min-h-[16rem] flex-col gap-3 rounded-lg border bg-muted/30 p-3 transition-colors"
                :class="dragOverStatus === column.status ? 'border-primary bg-muted/60' : ''"
                @dragover="onDragOver(column.status, $event)"
                @dragleave="dragOverStatus = dragOverStatus === column.status ? null : dragOverStatus"
                @drop="onDrop(column.status, $event)"
            >
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-sm font-medium">{{ column.label }}</h3>
                    <Badge variant="secondary">{{ column.tasks.length }}</Badge>
                </div>

                <div v-if="column.tasks.length === 0" class="flex flex-1 items-center justify-center rounded-md border border-dashed p-4 text-center text-xs text-muted-foreground">
                    Nessun task
                </div>

                <div
                    v-for="task in column.tasks"
                    :key="task.id"
                    :draggable="isToday"
                    class="group flex items-start gap-2 rounded-md border bg-background p-3 shadow-sm"
                    :class="draggingTaskId === task.id ? 'opacity-40' : ''"
                    @dragstart="onDragStart(task, $event)"
                    @dragend="onDragEnd"
                >
                    <GripVertical v-if="isToday" class="mt-0.5 size-4 shrink-0 cursor-grab text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ task.title }}</p>
                        <p v-if="task.description" class="mt-1 line-clamp-3 text-xs whitespace-pre-line text-muted-foreground">
                            {{ task.description }}
                        </p>
                    </div>
                    <Button
                        v-if="isToday"
                        variant="ghost"
                        size="icon-sm"
                        class="shrink-0 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive"
                        title="Elimina task"
                        @click="destroyTask(task)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
