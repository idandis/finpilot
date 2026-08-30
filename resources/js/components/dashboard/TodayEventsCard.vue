<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Plus } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import * as calendarRoutes from '@/routes/calendar';
import type { DashboardEvent } from '@/types';

defineProps<{
    events: DashboardEvent[];
}>();

function timeLabel(event: DashboardEvent): string {
    if (event.all_day) {
        return 'Tutto il giorno';
    }

    return new Date(event.start_at).toLocaleTimeString('it-IT', {
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Card class="border-none bg-muted dark:bg-muted/40 shadow-none">
        <CardHeader>
            <div class="flex items-center justify-between">
                <CardTitle class="flex items-center gap-2 text-base">
                    <CalendarDays class="size-4 text-muted-foreground" />
                    Eventi di oggi
                </CardTitle>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    class="size-6"
                    title="Crea nuovo evento"
                    as-child
                >
                    <Link :href="calendarRoutes.index()">
                        <Plus class="size-4" />
                    </Link>
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <p v-if="events.length === 0" class="text-sm text-muted-foreground">
                Nessun evento per oggi.
            </p>
            <ul v-else class="space-y-2">
                <li
                    v-for="event in events"
                    :key="event.id"
                    class="flex items-center justify-between gap-2 text-sm"
                >
                    <span class="min-w-0 flex-1 truncate">{{ event.title }}</span>
                    <Badge variant="outline">{{ timeLabel(event) }}</Badge>
                </li>
            </ul>
            <Link
                :href="calendarRoutes.index()"
                class="mt-3 inline-block text-sm text-primary underline underline-offset-4"
            >
                Apri calendario
            </Link>
        </CardContent>
    </Card>
</template>
