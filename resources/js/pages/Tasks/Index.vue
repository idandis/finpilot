<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, ChevronsRight, GripVertical, Pencil, Plus, Trash2, UserRound, Users } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import TaskBoardController from '@/actions/App/Http/Controllers/TaskBoardController';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import InputError from '@/components/InputError.vue';
import SharedWith from '@/components/SharedWith.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import { avatarStyle } from '@/lib/avatar-color';
import * as taskRoutes from '@/routes/tasks';
import type { SharedPerson, Task, TaskBoard, TaskBoardDetail, TaskStatus } from '@/types';

const props = defineProps<{
    date: string;
    today: string;
    boards: TaskBoard[];
    board: TaskBoardDetail | null;
    tasks: Task[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Task', href: taskRoutes.index() }],
    },
});

const page = usePage();

const formattedDate = computed(() =>
    new Intl.DateTimeFormat('it-IT', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${props.date}T00:00:00`)),
);

// The Daily board (board === null) is the day-by-day one: it navigates
// through days and locks the past. A user-created board has no day at all,
// so none of that applies to it - its tasks stay editable forever.
const isDaily = computed(() => props.board === null);

const isToday = computed(() => props.date === props.today);
const isPast = computed(() => isDaily.value && props.date < props.today);

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

function goToBoard(board: TaskBoard | null) {
    router.get(taskRoutes.index.url({ query: board ? { board: board.id } : {} }), {}, { preserveScroll: true });
}

const isAddBoardOpen = ref(false);
const isRenameBoardOpen = ref(false);

function destroyBoard(board: TaskBoardDetail, event?: Event) {
    event?.stopPropagation();

    if (confirm(`Eliminare la board "${board.name}"? Verranno eliminati anche tutti i task al suo interno.`)) {
        router.delete(TaskBoardController.destroy(board.id).url, {
            preserveScroll: true,
        });
    }
}

// --- Sharing: a board is worked on by its owner plus whoever they invited
// by email, everyone with the same powers over its tasks (see SharedWith). ---

function removeMember(person: SharedPerson) {
    if (!props.board) {
        return;
    }

    if (confirm(`Rimuovere ${person.name} dalla board? I task che aveva assegnati restano, senza assegnatario.`)) {
        router.delete(TaskBoardController.destroyMember([props.board.id, person.id]).url, { preserveScroll: true });
    }
}

function leaveBoard(person: SharedPerson) {
    if (!props.board) {
        return;
    }

    if (confirm(`Uscire dalla board "${props.board.name}"? Non la vedrai più finché non ti reinvitano.`)) {
        router.delete(TaskBoardController.destroyMember([props.board.id, person.id]).url);
    }
}

function assignTask(task: Task, personId: number | null) {
    task.assignee = personId === null ? null : (props.board?.people.find((person) => person.id === personId) ?? null);

    router.patch(taskRoutes.assign(task.id).url, { assigned_to_user_id: personId }, { preserveScroll: true, preserveState: true });
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
const dragOverTaskId = ref<number | null>(null);
const dragOverBefore = ref(true);

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
    dragOverTaskId.value = null;
}

function onDragOver(status: TaskStatus, event: DragEvent) {
    event.preventDefault();
    dragOverStatus.value = status;
}

// Moves (or reorders) a task into `status` at `targetIndex`, counting only
// the *other* tasks already in that column - used both for drops on the
// column background (append at the end) and drops on a specific task card
// (insert right before/after it).
function moveTask(taskId: number, status: TaskStatus, targetIndex: number) {
    const task = localTasks.find((candidate) => candidate.id === taskId);

    if (!task) {
        return;
    }

    const columnTasks = localTasks
        .filter((candidate) => candidate.status === status && candidate.id !== taskId)
        .sort((a, b) => a.position - b.position);

    const insertIndex = Math.max(0, Math.min(targetIndex, columnTasks.length));
    columnTasks.splice(insertIndex, 0, task);

    task.status = status;
    columnTasks.forEach((candidate, index) => {
        candidate.position = index;
    });

    router.patch(taskRoutes.move(taskId).url, { status, position: insertIndex }, { preserveScroll: true, preserveState: true });
}

function onDrop(status: TaskStatus, event: DragEvent) {
    event.preventDefault();
    const taskId = draggingTaskId.value;
    dragOverStatus.value = null;
    dragOverTaskId.value = null;
    draggingTaskId.value = null;

    if (taskId === null || isPast.value) {
        return;
    }

    const columnLength = localTasks.filter((candidate) => candidate.status === status && candidate.id !== taskId).length;

    moveTask(taskId, status, columnLength);
}

function onTaskDragOver(task: Task, event: DragEvent) {
    if (isPast.value) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    dragOverStatus.value = task.status;
    dragOverTaskId.value = task.id;

    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    dragOverBefore.value = event.clientY < rect.top + rect.height / 2;
}

function onTaskDrop(task: Task, event: DragEvent) {
    event.preventDefault();
    event.stopPropagation();
    const taskId = draggingTaskId.value;
    const before = dragOverBefore.value;
    dragOverStatus.value = null;
    dragOverTaskId.value = null;
    draggingTaskId.value = null;

    if (taskId === null || isPast.value || taskId === task.id) {
        return;
    }

    const columnTasks = localTasks
        .filter((candidate) => candidate.status === task.status && candidate.id !== taskId)
        .sort((a, b) => a.position - b.position);
    const targetIndex = columnTasks.findIndex((candidate) => candidate.id === task.id);

    moveTask(taskId, task.status, before ? targetIndex : targetIndex + 1);
}

function destroyTask(task: Task, event: Event) {
    event.stopPropagation();

    if (!isPast.value && confirm(`Eliminare il task "${task.title}"?`)) {
        router.delete(taskRoutes.destroy(task.id).url, {
            preserveScroll: true,
        });
    }
}

// Opens the "move to another day" dialog - the only action allowed even on
// an otherwise read-only past day, so leftovers from yesterday can be
// caught up without rewriting history. Defaults to tomorrow, but any day
// from today onward can be picked instead.
const reschedulingTask = ref<Task | null>(null);
const isRescheduleTaskOpen = computed(() => reschedulingTask.value !== null);

function openRescheduleDialog(task: Task, event: Event) {
    event.stopPropagation();
    reschedulingTask.value = task;
}

function closeRescheduleDialog() {
    reschedulingTask.value = null;
}

const isAddTaskOpen = ref(false);

const editingTask = ref<Task | null>(null);
const isEditTaskOpen = computed(() => editingTask.value !== null);

function openEditTaskDialog(task: Task) {
    if (isPast.value) {
        return;
    }

    editingTask.value = task;
}

function closeEditTaskDialog() {
    editingTask.value = null;
}
</script>

<template>
    <Head title="Task" />

    <div class="flex flex-col space-y-6 p-4 md:min-h-0 md:flex-1">
        <div class="flex items-center gap-2 overflow-x-auto pb-1">
            <button
                type="button"
                class="flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm transition"
                :class="isDaily ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground hover:bg-muted/70'"
                @click="goToBoard(null)"
            >
                <CalendarDays class="size-4" />
                Daily
            </button>

            <button
                v-for="taskBoard in boards"
                :key="taskBoard.id"
                type="button"
                class="flex max-w-[14rem] shrink-0 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm transition"
                :class="board?.id === taskBoard.id ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground hover:bg-muted/70'"
                :title="taskBoard.is_shared ? `${taskBoard.name} - condivisa con te` : taskBoard.name"
                @click="goToBoard(taskBoard)"
            >
                <Users v-if="taskBoard.is_shared" class="size-3.5 shrink-0 opacity-70" />
                <span class="truncate">{{ taskBoard.name }}</span>
            </button>

            <Button variant="outline" size="icon-sm" class="shrink-0 rounded-full" title="Nuova board" @click="isAddBoardOpen = true">
                <Plus />
            </Button>
        </div>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <div v-if="isDaily" class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <Button variant="outline" size="icon-sm" title="Giorno precedente" @click="goToDate(previousDate)">
                        <ChevronLeft />
                    </Button>
                    <span class="capitalize">{{ formattedDate }}</span>
                    <Button variant="outline" size="icon-sm" title="Giorno successivo" @click="goToDate(nextDate)">
                        <ChevronRight />
                    </Button>
                    <Button v-if="!isToday" variant="ghost" size="sm" @click="goToDate(today)">Torna a oggi</Button>
                </div>
                <div v-else-if="board" class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-medium">{{ board.name }}</h2>
                    <template v-if="board.is_owner">
                        <Button variant="ghost" size="icon-sm" class="text-muted-foreground" title="Rinomina board" @click="isRenameBoardOpen = true">
                            <Pencil />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            title="Elimina board"
                            @click="destroyBoard(board)"
                        >
                            <Trash2 />
                        </Button>
                    </template>
                    <Badge v-else variant="secondary" class="gap-1">
                        <Users class="size-3" />
                        Condivisa con te
                    </Badge>

                    <SharedWith
                        class="ml-1"
                        :title="`Condividi &quot;${board.name}&quot;`"
                        :people="board.people"
                        :is-owner="board.is_owner"
                        :current-user-id="page.props.auth.user.id"
                        :invite-form="TaskBoardController.storeMember.form(board.id)"
                        permission-hint="Deve essere già registrata sulla piattaforma. Chi entra può creare, spostare ed eliminare i task della board come te."
                        leave-label="Esci dalla board"
                        @remove="removeMember"
                        @leave="leaveBoard"
                    />
                </div>
            </div>
            <Button v-if="!isPast" class="shrink-0" @click="isAddTaskOpen = true">
                <Plus />
                Nuovo task
            </Button>
            <Badge v-else variant="secondary" class="shrink-0">Sola lettura</Badge>
        </div>

        <Dialog v-model:open="isAddBoardOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nuova board</DialogTitle>
                </DialogHeader>
                <Form
                    v-bind="TaskBoardController.store.form()"
                    reset-on-success
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="isAddBoardOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="board-name">Nome</Label>
                        <Input id="board-name" name="name" placeholder="Es. Lavoro" required autofocus />
                        <InputError :message="errors.name" />
                        <p class="text-xs text-muted-foreground">
                            Una board libera: i suoi task non hanno una data e restano lì finché non li sposti o elimini.
                        </p>
                    </div>
                    <Button type="submit" :disabled="processing">Crea board</Button>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-if="board" v-model:open="isRenameBoardOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Rinomina board</DialogTitle>
                </DialogHeader>
                <Form
                    :key="`board-${board.id}`"
                    v-bind="TaskBoardController.update.form(board.id)"
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="isRenameBoardOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="rename-board-name">Nome</Label>
                        <Input id="rename-board-name" name="name" required autofocus :default-value="board.name" />
                        <InputError :message="errors.name" />
                    </div>
                    <Button type="submit" :disabled="processing">Salva</Button>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isAddTaskOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nuovo task{{ board ? ` in "${board.name}"` : '' }}</DialogTitle>
                </DialogHeader>
                <Form
                    v-bind="TaskController.store.form()"
                    reset-on-success
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="isAddTaskOpen = false"
                >
                    <input v-if="board" type="hidden" name="task_board_id" :value="board.id" />
                    <input v-else type="hidden" name="task_date" :value="date" />
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
                            class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                        ></textarea>
                        <InputError :message="errors.description" />
                    </div>
                    <div v-if="board" class="grid gap-2">
                        <Label for="assignee">Assegnato a</Label>
                        <select
                            id="assignee"
                            name="assigned_to_user_id"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Non assegnato</option>
                            <option v-for="person in board.people" :key="person.id" :value="person.id">
                                {{ person.name }}
                            </option>
                        </select>
                        <InputError :message="errors.assigned_to_user_id" />
                    </div>
                    <Button type="submit" :disabled="processing">Aggiungi task{{ isDaily && !isToday ? ` per ${formattedDate}` : '' }}</Button>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="isEditTaskOpen"
            @update:open="
                (open) => {
                    if (!open) closeEditTaskDialog();
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifica task</DialogTitle>
                </DialogHeader>
                <Form
                    v-if="editingTask"
                    :key="`task-${editingTask.id}`"
                    v-bind="TaskController.update.form(editingTask.id)"
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="closeEditTaskDialog"
                >
                    <div class="grid gap-2">
                        <Label for="edit-title">Titolo</Label>
                        <Input id="edit-title" name="title" required autofocus :default-value="editingTask.title" />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-description">Descrizione (opzionale)</Label>
                        <textarea
                            id="edit-description"
                            name="description"
                            rows="3"
                            placeholder="Dettagli aggiuntivi..."
                            class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                            :value="editingTask.description ?? ''"
                        ></textarea>
                        <InputError :message="errors.description" />
                    </div>
                    <Button type="submit" :disabled="processing">Salva modifiche</Button>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="isRescheduleTaskOpen"
            @update:open="
                (open) => {
                    if (!open) closeRescheduleDialog();
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Sposta task</DialogTitle>
                </DialogHeader>
                <Form
                    v-if="reschedulingTask"
                    :key="`reschedule-${reschedulingTask.id}`"
                    v-bind="TaskController.reschedule.form(reschedulingTask.id)"
                    class="grid grid-cols-1 gap-4"
                    v-slot="{ errors, processing }"
                    @success="closeRescheduleDialog"
                >
                    <div class="grid gap-2">
                        <Label for="reschedule-date">Nuova data</Label>
                        <Input id="reschedule-date" type="date" name="task_date" :min="today" :default-value="nextDate" required autofocus />
                        <InputError :message="errors.task_date" />
                    </div>
                    <Button type="submit" :disabled="processing">Sposta task</Button>
                </Form>
            </DialogContent>
        </Dialog>

        <div class="grid grid-cols-1 gap-4 md:min-h-0 md:flex-1 md:grid-cols-3">
            <div
                v-for="column in columns"
                :key="column.status"
                class="flex min-h-[16rem] flex-col gap-3 rounded-lg border bg-muted dark:bg-muted/50 p-3 transition-colors"
                :class="dragOverStatus === column.status ? 'border-primary bg-primary/10' : ''"
                @dragover="onDragOver(column.status, $event)"
                @dragleave="dragOverStatus = dragOverStatus === column.status ? null : dragOverStatus"
                @drop="onDrop(column.status, $event)"
            >
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-sm font-medium">{{ column.label }}</h3>
                    <Badge variant="secondary">{{ column.tasks.length }}</Badge>
                </div>

                <div
                    v-if="column.tasks.length === 0"
                    class="flex flex-1 items-center justify-center rounded-md border border-dashed p-4 text-center text-xs text-muted-foreground"
                >
                    Nessun task
                </div>

                <div
                    v-for="task in column.tasks"
                    :key="task.id"
                    :draggable="!isPast"
                    class="group flex items-start gap-2 rounded-md border bg-background p-3 shadow-sm"
                    :class="[
                        draggingTaskId === task.id ? 'opacity-40' : '',
                        isPast ? '' : 'cursor-pointer',
                        dragOverTaskId === task.id && draggingTaskId !== task.id
                            ? dragOverBefore
                                ? 'border-t-2 border-t-primary'
                                : 'border-b-2 border-b-primary'
                            : '',
                    ]"
                    @dragstart="onDragStart(task, $event)"
                    @dragend="onDragEnd"
                    @dragover="onTaskDragOver(task, $event)"
                    @drop="onTaskDrop(task, $event)"
                    @click="openEditTaskDialog(task)"
                >
                    <GripVertical v-if="!isPast" class="mt-0.5 size-4 shrink-0 cursor-grab text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ task.title }}
                        </p>
                        <p v-if="task.description" class="mt-1 line-clamp-3 text-xs whitespace-pre-line text-muted-foreground">
                            {{ task.description }}
                        </p>
                    </div>
                    <DropdownMenu v-if="board">
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="shrink-0 rounded-full transition hover:opacity-80"
                                :title="task.assignee ? `Assegnato a ${task.assignee.name}` : 'Non assegnato'"
                                @click.stop
                            >
                                <span
                                    v-if="task.assignee"
                                    class="flex size-7 items-center justify-center rounded-full text-[10px] font-semibold text-white"
                                    :style="avatarStyle(task.assignee.name)"
                                >
                                    {{ getInitials(task.assignee.name) }}
                                </span>
                                <span
                                    v-else
                                    class="flex size-7 items-center justify-center rounded-full border border-dashed border-muted-foreground/40 text-muted-foreground"
                                >
                                    <UserRound class="size-3.5" />
                                </span>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="assignTask(task, null)">
                                <span
                                    class="flex size-6 items-center justify-center rounded-full border border-dashed border-muted-foreground/40 text-muted-foreground"
                                >
                                    <UserRound class="size-3" />
                                </span>
                                Non assegnato
                            </DropdownMenuItem>
                            <DropdownMenuItem v-for="person in board.people" :key="person.id" @click="assignTask(task, person.id)">
                                <span
                                    class="flex size-6 items-center justify-center rounded-full text-[10px] font-semibold text-white"
                                    :style="avatarStyle(person.name)"
                                >
                                    {{ getInitials(person.name) }}
                                </span>
                                {{ person.name }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <div class="flex shrink-0 gap-0.5 opacity-0 group-hover:opacity-100">
                        <Button
                            v-if="isDaily"
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground hover:bg-muted"
                            title="Sposta ad un altro giorno"
                            @click="openRescheduleDialog(task, $event)"
                        >
                            <ChevronsRight />
                        </Button>
                        <Button
                            v-if="!isDaily || isToday"
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            title="Elimina task"
                            @click="destroyTask(task, $event)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
