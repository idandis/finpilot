<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { KanbanSquare } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import * as taskRoutes from '@/routes/tasks';
import type { Task, TaskStatus } from '@/types';

defineProps<{
    tasks: Pick<Task, 'id' | 'title' | 'status'>[];
}>();

const statusLabel: Record<TaskStatus, string> = {
    todo: 'Da fare',
    in_progress: 'In corso',
    done: 'Fatto',
};

const statusVariant: Record<TaskStatus, 'outline' | 'secondary' | 'default'> = {
    todo: 'outline',
    in_progress: 'secondary',
    done: 'default',
};
</script>

<template>
    <Card class="border-none bg-muted/40 shadow-none">
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <KanbanSquare class="size-4 text-muted-foreground" />
                Task di oggi
            </CardTitle>
        </CardHeader>
        <CardContent>
            <p v-if="tasks.length === 0" class="text-sm text-muted-foreground">
                Nessun task per oggi.
            </p>
            <ul v-else class="space-y-2">
                <li
                    v-for="task in tasks"
                    :key="task.id"
                    class="flex items-center justify-between gap-2 text-sm"
                >
                    <span
                        :class="[
                            'truncate',
                            task.status === 'done' &&
                                'text-muted-foreground line-through',
                        ]"
                    >
                        {{ task.title }}
                    </span>
                    <Badge :variant="statusVariant[task.status]">
                        {{ statusLabel[task.status] }}
                    </Badge>
                </li>
            </ul>
            <Link
                :href="taskRoutes.index()"
                class="mt-3 inline-block text-sm text-primary underline underline-offset-4"
            >
                Vai ai task
            </Link>
        </CardContent>
    </Card>
</template>
